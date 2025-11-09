<?php

namespace Tests\Unit\Controller\ReviewCourseController;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use App\Models\Category;
use App\Models\Course;
use App\Models\User;
use App\Models\CourseReview;
use App\Http\Controllers\Api\Course\ReviewCourseController;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Validation\ValidationException;

class ReviewCourseMakeReviewCourseTest extends TestCase
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

    public function test_make_review_success()
    {
        // setup instructor & course
        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Review Cat',
            'image' => null,
        ]);

        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instr_' . Str::random(6),
            'first_name' => 'Instr',
            'last_name' => 'Reviewer',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $course = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Course For Review',
            'price' => null,
            'is_discount_active' => false,
        ]);

        // student who will review
        $student = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'stud_' . Str::random(6),
            'first_name' => 'Student',
            'last_name' => 'Reviewer',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        // mock JWTAuth facade to return our student
        JWTAuth::shouldReceive('user')->andReturn($student);

        $request = new Request([
            'rating' => 5,
            'comment' => 'Great course!',
        ]);

        $controller = new ReviewCourseController();
        $response = $controller->makeReviewCourse($request, $course->id);

        $data = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $data);
        if (is_bool($data['status'])) {
            $this->assertTrue($data['status']);
        } else {
            $this->assertEquals('success', $data['status']);
        }

        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('Review created successfully', $data['message']);

        $this->assertArrayHasKey('data', $data);
        $review = $data['data'];
        $this->assertIsArray($review);
        $this->assertEquals(5, $review['rating']);
        $this->assertEquals('Great course!', $review['comment']);
        $this->assertEquals($student->id, $review['user_id']);
        $this->assertEquals($course->id, $review['course_id']);

        $this->assertTrue(CourseReview::where('user_id', $student->id)->where('course_id', $course->id)->exists());
    }

    public function test_make_review_course_not_found()
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

        JWTAuth::shouldReceive('user')->andReturn($student);

        $fakeCourseId = Str::uuid()->toString();

        $request = new Request([
            'rating' => 4,
            'comment' => 'Nice',
        ]);

        $controller = new ReviewCourseController();
        $response = $controller->makeReviewCourse($request, $fakeCourseId);

        $data = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $data);
        if (is_bool($data['status'])) {
            $this->assertFalse($data['status']);
        } else {
            $this->assertNotEquals('success', $data['status']);
        }

        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('Course not found', $data['message']);

        $this->assertFalse(CourseReview::where('user_id', $student->id)->where('course_id', $fakeCourseId)->exists());
    }

    public function test_make_review_already_reviewed()
    {
        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'AlreadyRev Cat',
            'image' => null,
        ]);

        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instr_' . Str::random(6),
            'first_name' => 'Instr',
            'last_name' => 'AR',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $course = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Course Already Reviewed',
            'price' => null,
            'is_discount_active' => false,
        ]);

        $student = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'stud_ar_' . Str::random(6),
            'first_name' => 'Student',
            'last_name' => 'AR',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        // existing review
        CourseReview::create([
            'id' => Str::uuid()->toString(),
            'course_id' => $course->id,
            'user_id' => $student->id,
            'rating' => 4,
            'comment' => 'Good',
        ]);

        JWTAuth::shouldReceive('user')->andReturn($student);

        $request = new Request([
            'rating' => 5,
            'comment' => 'Trying to review again',
        ]);

        $controller = new ReviewCourseController();
        $response = $controller->makeReviewCourse($request, $course->id);

        $data = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $data);
        if (is_bool($data['status'])) {
            $this->assertFalse($data['status']);
        } else {
            $this->assertNotEquals('success', $data['status']);
        }

        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('You have already reviewed this course', $data['message']);

        $count = CourseReview::where('user_id', $student->id)->where('course_id', $course->id)->count();
        $this->assertEquals(1, $count, 'Expected only the original review to exist');
    }

    public function test_make_review_validation_failure()
    {
        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'ValFail Cat',
            'image' => null,
        ]);

        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instr_val_' . Str::random(6),
            'first_name' => 'Instr',
            'last_name' => 'Val',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $course = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Course Val Test',
            'price' => null,
            'is_discount_active' => false,
        ]);

        $student = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'stud_val_' . Str::random(6),
            'first_name' => 'Student',
            'last_name' => 'Val',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        JWTAuth::shouldReceive('user')->andReturn($student);

        // missing rating should trigger validation exception
        $request = new Request([
            'comment' => 'No rating provided',
        ]);

        $this->expectException(ValidationException::class);

        $controller = new ReviewCourseController();
        $controller->makeReviewCourse($request, $course->id);
    }
}