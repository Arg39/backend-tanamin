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

class BookmarkRemoveBookmarkTest extends TestCase
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

    public function test_remove_bookmark_success_by_student()
    {
        $student = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'stud_rm_' . Str::random(6),
            'first_name' => 'Student',
            'last_name' => 'Remover',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'RemoveBookmark Cat',
            'image' => null,
        ]);

        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instr_rm_' . Str::random(6),
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
            'title' => 'Course To Unbookmark',
            'price' => null,
            'is_discount_active' => false,
        ]);

        // create bookmark to be removed
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
        $response = $controller->removeBookmark($request, $course->id);

        $data = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $data);
        if (is_bool($data['status'])) {
            $this->assertTrue($data['status']);
        } else {
            $this->assertEquals('success', $data['status']);
        }

        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('Bookmark removed successfully.', $data['message']);

        $this->assertFalse(Bookmark::where('user_id', $student->id)->where('course_id', $course->id)->exists());
    }

    public function test_remove_bookmark_fails_for_non_student()
    {
        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instr_rm_user_' . Str::random(6),
            'first_name' => 'Instructor',
            'last_name' => 'Attempt',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'NonStudentRemove Cat',
            'image' => null,
        ]);

        $courseOwner = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'course_owner_' . Str::random(6),
            'first_name' => 'Course',
            'last_name' => 'Owner',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $course = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $courseOwner->id,
            'title' => 'Course Not For Students',
            'price' => null,
            'is_discount_active' => false,
        ]);

        $request = new Request();
        $request->setUserResolver(function () use ($instructor) {
            return $instructor;
        });

        $controller = new BookmarkController();
        $response = $controller->removeBookmark($request, $course->id);

        $data = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $data);
        if (is_bool($data['status'])) {
            $this->assertFalse($data['status']);
        } else {
            $this->assertNotEquals('success', $data['status']);
        }

        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('Only students can remove bookmarks.', $data['message']);

        $this->assertFalse(Bookmark::where('user_id', $instructor->id)->where('course_id', $course->id)->exists());
    }

    public function test_remove_bookmark_not_found()
    {
        $student = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'stud_rm_nf_' . Str::random(6),
            'first_name' => 'Student',
            'last_name' => 'NF',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'RemoveNotFound Cat',
            'image' => null,
        ]);

        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instr_rm_nf_' . Str::random(6),
            'first_name' => 'Instructor',
            'last_name' => 'NF',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $course = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Course With No Bookmark',
            'price' => null,
            'is_discount_active' => false,
        ]);

        // ensure no bookmark exists
        Bookmark::where('user_id', $student->id)->where('course_id', $course->id)->delete();

        $request = new Request();
        $request->setUserResolver(function () use ($student) {
            return $student;
        });

        $controller = new BookmarkController();
        $response = $controller->removeBookmark($request, $course->id);

        $data = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $data);
        if (is_bool($data['status'])) {
            $this->assertFalse($data['status']);
        } else {
            $this->assertNotEquals('success', $data['status']);
        }

        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('Bookmark not found.', $data['message']);
    }
}