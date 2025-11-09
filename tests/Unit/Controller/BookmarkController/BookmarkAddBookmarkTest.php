<?php

namespace Tests\Unit\Controller\BookmarkController;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\Category;
use App\Models\Course;
use App\Models\Bookmark;
use App\Http\Controllers\Api\Course\BookmarkController;

class BookmarkAddBookmarkTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Normalize controller/resource response to array.
     */
    private function resolveResponseData($response, Request $request)
    {
        if (is_array($response)) {
            return $response;
        }

        if ($response instanceof JsonResponse) {
            return $response->getData(true);
        }

        if (is_object($response) && method_exists($response, 'toResponse')) {
            $httpResponse = $response->toResponse($request);
            if ($httpResponse instanceof JsonResponse) {
                return $httpResponse->getData(true);
            }
            if (method_exists($httpResponse, 'getData')) {
                return $httpResponse->getData(true);
            }
        }

        if (is_object($response) && method_exists($response, 'getData')) {
            return $response->getData(true);
        }

        throw new \RuntimeException('Unable to resolve response data in test. Response type: ' . gettype($response));
    }

    public function test_add_bookmark_success_by_student()
    {
        $student = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'stud_' . Str::random(6),
            'first_name' => 'Student',
            'last_name' => 'One',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        // create category and instructor to satisfy NOT NULL FK constraints
        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Bookmark Cat',
            'image' => null,
        ]);

        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instr_' . Str::random(6),
            'first_name' => 'Instructor',
            'last_name' => 'ForCourse',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $course = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Course To Bookmark',
            'price' => null,
            'is_discount_active' => false,
        ]);

        $request = new Request();
        $request->setUserResolver(function () use ($student) {
            return $student;
        });

        $controller = new BookmarkController();
        $response = $controller->addBookmark($request, $course->id);

        $data = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $data);
        if (is_bool($data['status'])) {
            $this->assertTrue($data['status']);
        } else {
            $this->assertEquals('success', $data['status']);
        }

        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('Course bookmarked successfully.', $data['message']);

        $this->assertArrayHasKey('data', $data);
        $bookmarkData = $data['data'];
        $this->assertIsArray($bookmarkData);
        $this->assertEquals($student->id, $bookmarkData['user_id']);
        $this->assertEquals($course->id, $bookmarkData['course_id']);

        $this->assertTrue(Bookmark::where('user_id', $student->id)->where('course_id', $course->id)->exists());
    }

    public function test_add_bookmark_fails_for_non_student()
    {
        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instr_user_' . Str::random(6),
            'first_name' => 'Instructor',
            'last_name' => 'One',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        // create category and another instructor to attach to course
        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Instructor Course Cat',
            'image' => null,
        ]);

        $courseInstructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instr_course_' . Str::random(6),
            'first_name' => 'Course',
            'last_name' => 'Owner',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $course = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $courseInstructor->id,
            'title' => 'Course For Instructor',
            'price' => null,
            'is_discount_active' => false,
        ]);

        $request = new Request();
        $request->setUserResolver(function () use ($instructor) {
            return $instructor;
        });

        $controller = new BookmarkController();
        $response = $controller->addBookmark($request, $course->id);

        $data = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $data);
        if (is_bool($data['status'])) {
            $this->assertFalse($data['status']);
        } else {
            $this->assertNotEquals('success', $data['status']);
        }

        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('Only students can bookmark courses.', $data['message']);

        $this->assertFalse(Bookmark::where('user_id', $instructor->id)->where('course_id', $course->id)->exists());
    }

    public function test_add_bookmark_course_not_found()
    {
        $student = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'stud_nf_' . Str::random(6),
            'first_name' => 'Student',
            'last_name' => 'NF',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        $fakeCourseId = Str::uuid()->toString();

        $request = new Request();
        $request->setUserResolver(function () use ($student) {
            return $student;
        });

        $controller = new BookmarkController();
        $response = $controller->addBookmark($request, $fakeCourseId);

        $data = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $data);
        if (is_bool($data['status'])) {
            $this->assertFalse($data['status']);
        } else {
            $this->assertNotEquals('success', $data['status']);
        }

        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('Course not found.', $data['message']);

        $this->assertFalse(Bookmark::where('user_id', $student->id)->where('course_id', $fakeCourseId)->exists());
    }

    public function test_add_bookmark_already_bookmarked()
    {
        $student = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'stud_ab_' . Str::random(6),
            'first_name' => 'Student',
            'last_name' => 'AB',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        // create category and instructor to satisfy FK constraints
        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Already Bookmarked Cat',
            'image' => null,
        ]);

        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instr_ab_' . Str::random(6),
            'first_name' => 'Instr',
            'last_name' => 'AB',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $course = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Already Bookmarked Course',
            'price' => null,
            'is_discount_active' => false,
        ]);

        // create existing bookmark
        Bookmark::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $student->id,
            'course_id' => $course->id,
        ]);

        $request = new Request();
        $request->setUserResolver(function () use ($student) {
            return $student;
        });

        $controller = new BookmarkController();
        $response = $controller->addBookmark($request, $course->id);

        $data = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $data);
        if (is_bool($data['status'])) {
            $this->assertFalse($data['status']);
        } else {
            $this->assertNotEquals('success', $data['status']);
        }

        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('Course already bookmarked.', $data['message']);

        $count = Bookmark::where('user_id', $student->id)->where('course_id', $course->id)->count();
        $this->assertEquals(1, $count, 'Expected only one bookmark record to exist');
    }
}