<?php

namespace Tests\Unit\Controller\CardCourseController;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use App\Models\Category;
use App\Models\Course;
use App\Models\User;
use App\Models\Bookmark;
use App\Models\CourseEnrollment;
use App\Models\CourseCheckoutSession;
use App\Http\Controllers\Api\CardCourseController;

class CardCourseBookmarkedCoursesTest extends TestCase
{
    use DatabaseTransactions;

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

    public function test_bookmarked_courses_unauthorized_returns_unauthorized_message()
    {
        $controller = new CardCourseController();
        $request = new Request();

        $response = $controller->bookmarkedCourses($request);
        $responseData = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Unauthorized', $responseData['message']);

        // Accept either missing data or null
        $this->assertTrue(
            !array_key_exists('data', $responseData) || is_null($responseData['data']),
            'Expected "data" to be either absent or null when unauthorized.'
        );
    }

    public function test_bookmarked_courses_returns_only_non_enrolled_bookmarks()
    {
        // Arrange: create category, instructor and student
        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Gardening',
            'image' => null,
        ]);

        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instr_' . Str::random(6),
            'first_name' => 'Inst',
            'last_name' => 'Teacher',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $student = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'student_' . Str::random(6),
            'first_name' => 'Stud',
            'last_name' => 'User',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        // Courses
        $enrolledCourse = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Enrolled Course',
            'price' => 50,
            'level' => 'beginner',
            'image' => null,
            'status' => 'published',
            'detail' => null,
        ]);

        $bookmarkedCourse = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Bookmarked Only Course',
            'price' => 0,
            'level' => 'beginner',
            'image' => null,
            'status' => 'published',
            'detail' => null,
        ]);

        $bookmarkedEnrolledCourse = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Bookmarked But Enrolled',
            'price' => 75,
            'level' => 'intermediate',
            'image' => null,
            'status' => 'published',
            'detail' => null,
        ]);

        // Create paid checkout sessions
        $checkoutEnrolledId = Str::uuid()->toString();
        $checkoutBookmarkedEnrolledId = Str::uuid()->toString();

        CourseCheckoutSession::create([
            'id' => $checkoutEnrolledId,
            'user_id' => $student->id,
            'payment_status' => 'paid',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        CourseCheckoutSession::create([
            'id' => $checkoutBookmarkedEnrolledId,
            'user_id' => $student->id,
            'payment_status' => 'paid',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Enroll student to enrolledCourse and bookmarkedEnrolledCourse
        CourseEnrollment::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $student->id,
            'course_id' => $enrolledCourse->id,
            'checkout_session_id' => $checkoutEnrolledId,
            'access_status' => 'active',
            'price' => $enrolledCourse->price,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        CourseEnrollment::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $student->id,
            'course_id' => $bookmarkedEnrolledCourse->id,
            'checkout_session_id' => $checkoutBookmarkedEnrolledId,
            'access_status' => 'active',
            'price' => $bookmarkedEnrolledCourse->price,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create bookmarks for the student
        Bookmark::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $student->id,
            'course_id' => $bookmarkedCourse->id,
        ]);

        Bookmark::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $student->id,
            'course_id' => $bookmarkedEnrolledCourse->id,
        ]);

        // Create another user's bookmark to ensure filtering by user
        $otherUser = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'other_' . Str::random(6),
            'first_name' => 'Other',
            'last_name' => 'User',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        Bookmark::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $otherUser->id,
            'course_id' => $enrolledCourse->id,
        ]);

        // Act: call controller with authenticated student
        $controller = new CardCourseController();
        $request = new Request();
        $request->setUserResolver(function () use ($student) {
            return $student;
        });

        $response = $controller->bookmarkedCourses($request);
        $responseData = $this->resolveResponseData($response, $request);

        // Assert
        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Bookmarked courses retrieved successfully.', $responseData['message']);

        // PostResource may wrap data under 'data' key; handle both shapes
        $payload = $responseData['data'] ?? $responseData;
        $this->assertArrayHasKey('courses', $payload);

        $courses = $payload['courses'];
        $this->assertIsArray($courses);
        $this->assertCount(1, $courses, 'Only the non-enrolled bookmarked course should be returned');

        $returnedIds = array_map(function ($it) {
            return $it['id'] ?? ($it['course_id'] ?? null);
        }, $courses);

        $this->assertContains($bookmarkedCourse->id, $returnedIds);
        $this->assertNotContains($enrolledCourse->id, $returnedIds);
        $this->assertNotContains($bookmarkedEnrolledCourse->id, $returnedIds);
    }
}
