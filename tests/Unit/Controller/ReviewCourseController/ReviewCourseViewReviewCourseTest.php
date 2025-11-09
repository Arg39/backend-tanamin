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

class ReviewCourseViewReviewCourseTest extends TestCase
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

    public function test_view_review_success()
    {
        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'ViewRev Cat',
            'image' => null,
        ]);

        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instr_' . Str::random(6),
            'first_name' => 'Instr',
            'last_name' => 'Viewer',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $course = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Course For View Review',
            'price' => null,
            'is_discount_active' => false,
        ]);

        $student = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'stud_vw_' . Str::random(6),
            'first_name' => 'Student',
            'last_name' => 'Viewer',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        $review = CourseReview::create([
            'id' => Str::uuid()->toString(),
            'course_id' => $course->id,
            'user_id' => $student->id,
            'rating' => 5,
            'comment' => 'Fantastic course',
        ]);

        JWTAuth::shouldReceive('user')->andReturn($student);

        $controller = new ReviewCourseController();
        $request = new Request();
        $response = $controller->viewReviewCourse($course->id);

        $data = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $data);
        if (is_bool($data['status'])) {
            $this->assertTrue($data['status']);
        } else {
            $this->assertEquals('success', $data['status']);
        }

        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('Reviews fetched successfully', $data['message']);

        $this->assertArrayHasKey('data', $data);
        $this->assertIsArray($data['data']);
        $this->assertCount(1, $data['data']);
        $first = $data['data'][0];
        $this->assertEquals($review->id, $first['id']);
        $this->assertEquals($review->rating, $first['rating']);
        $this->assertEquals($review->comment, $first['comment']);
    }

    public function test_view_review_course_not_found()
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

        $controller = new ReviewCourseController();
        $request = new Request();
        $response = $controller->viewReviewCourse($fakeCourseId);

        $data = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $data);
        if (is_bool($data['status'])) {
            $this->assertFalse($data['status']);
        } else {
            $this->assertNotEquals('success', $data['status']);
        }

        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('Course not found', $data['message']);
    }

    public function test_view_review_no_reviews()
    {
        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'EmptyViewRev Cat',
            'image' => null,
        ]);

        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instr_ev_' . Str::random(6),
            'first_name' => 'Instr',
            'last_name' => 'EmptyView',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $course = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Course With No Reviews',
            'price' => null,
            'is_discount_active' => false,
        ]);

        $student = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'stud_ev_' . Str::random(6),
            'first_name' => 'Student',
            'last_name' => 'EmptyView',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        JWTAuth::shouldReceive('user')->andReturn($student);

        $controller = new ReviewCourseController();
        $request = new Request();
        $response = $controller->viewReviewCourse($course->id);

        $data = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $data);
        if (is_bool($data['status'])) {
            $this->assertTrue($data['status']);
        } else {
            $this->assertEquals('success', $data['status']);
        }

        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('No reviews found for this course', $data['message']);
    }
}