<?php

namespace Tests\Unit\Controller\CardCourseController;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\Category;
use App\Models\Course;
use App\Models\User;
use App\Models\CourseEnrollment;
use App\Models\ModuleCourse;
use App\Models\LessonCourse;
use App\Models\LessonProgress;
use App\Http\Controllers\Api\CardCourseController;
use App\Models\CourseCheckoutSession;

class CardCourseMyCoursesTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Normalize controller response to array similar to other tests.
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

    public function test_my_courses_unauthorized_returns_unauthorized_message()
    {
        $controller = new CardCourseController();
        $request = new Request();

        $response = $controller->myCourses($request);
        $responseData = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Unauthorized', $responseData['message']);

        // Accept either missing data or null
        $this->assertTrue(
            !array_key_exists('data', $responseData) || is_null($responseData['data']),
            'Expected "data" to be either absent or null when unauthorized.'
        );
    }

    public function test_my_courses_returns_enrolled_courses_with_progress_and_filters()
    {
        // Arrange: create category, instructor and a student
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

        // Create two courses
        $courseActive = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Active Course',
            'price' => 50,
            'level' => 'beginner',
            'image' => null,
            'status' => 'published',
            'detail' => null,
        ]);

        $courseCompleted = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Completed Course',
            'price' => 50,
            'level' => 'beginner',
            'image' => null,
            'status' => 'published',
            'detail' => null,
        ]);

        // Insert course_checkout_sessions directly to satisfy whereHas('checkoutSession', ...) in controller
        $checkoutActiveId = Str::uuid()->toString();
        $checkoutCompletedId = Str::uuid()->toString();

        CourseCheckoutSession::create([
            'id' => $checkoutActiveId,
            'user_id' => $student->id,
            'payment_status' => 'paid',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        CourseCheckoutSession::create([
            'id' => $checkoutCompletedId,
            'user_id' => $student->id,
            'payment_status' => 'paid',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        // Create enrollments referencing the checkout sessions with different access_status
        CourseEnrollment::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $student->id,
            'course_id' => $courseActive->id,
            'checkout_session_id' => $checkoutActiveId,
            'access_status' => 'active',
            'price' => 50,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        CourseEnrollment::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $student->id,
            'course_id' => $courseCompleted->id,
            'checkout_session_id' => $checkoutCompletedId,
            'access_status' => 'completed',
            'price' => 50,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        // Create modules and lessons for the active course, and lessons for completed course
        $moduleA1 = ModuleCourse::create([
            'id' => Str::uuid()->toString(),
            'course_id' => $courseActive->id,
            'title' => 'Module A1',
        ]);

        $lessonA1 = LessonCourse::create([
            'id' => Str::uuid()->toString(),
            'module_id' => $moduleA1->id,
            'title' => 'Lesson A1',
        ]);
        $lessonA2 = LessonCourse::create([
            'id' => Str::uuid()->toString(),
            'module_id' => $moduleA1->id,
            'title' => 'Lesson A2',
        ]);
        $lessonA3 = LessonCourse::create([
            'id' => Str::uuid()->toString(),
            'module_id' => $moduleA1->id,
            'title' => 'Lesson A3',
        ]);

        // For completed course create one module and one lesson (to ensure non-zero total)
        $moduleC1 = ModuleCourse::create([
            'id' => Str::uuid()->toString(),
            'course_id' => $courseCompleted->id,
            'title' => 'Module C1',
        ]);

        $lessonC1 = LessonCourse::create([
            'id' => Str::uuid()->toString(),
            'module_id' => $moduleC1->id,
            'title' => 'Lesson C1',
        ]);

        // Mark some lessons as completed for the student for courseActive
        LessonProgress::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $student->id,
            'lesson_id' => $lessonA1->id,
            'completed_at' => Carbon::now(),
        ]);
        LessonProgress::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $student->id,
            'lesson_id' => $lessonA2->id,
            'completed_at' => Carbon::now(),
        ]);
        // Do not mark lessonA3 so progress is 2/3

        // For completed course mark its lesson completed
        LessonProgress::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $student->id,
            'lesson_id' => $lessonC1->id,
            'completed_at' => Carbon::now(),
        ]);

        // Act: call controller with authenticated student (user resolver)
        $controller = new CardCourseController();

        // Default filter ('enrolled' => all paid enrollments)
        $requestAll = new Request();
        $requestAll->setUserResolver(function () use ($student) {
            return $student;
        });

        $responseAll = $controller->myCourses($requestAll);
        $responseDataAll = $this->resolveResponseData($responseAll, $requestAll);

        $this->assertArrayHasKey('message', $responseDataAll);
        $this->assertEquals('My courses retrieved successfully', $responseDataAll['message'] ?? $responseDataAll['message']);

        $this->assertArrayHasKey('data', $responseDataAll);
        $dataAll = $responseDataAll['data'] ?? $responseDataAll; // accept both shapes
        $this->assertArrayHasKey('courses', $dataAll);

        $coursesList = $dataAll['courses'];
        $this->assertNotEmpty($coursesList);

        // Find the active course entry and assert progress matches "2/3"
        $foundActive = null;
        foreach ($coursesList as $it) {
            $id = $it['id'] ?? ($it['course_id'] ?? null);
            if ($id === $courseActive->id) {
                $foundActive = $it;
                break;
            }
        }
        $this->assertNotNull($foundActive, 'Active course should be present in enrolled list');

        $this->assertArrayHasKey('progress', $foundActive);
        $this->assertEquals('2/3', $foundActive['progress']);

        // Filter: ongoing (should include only access_status = active)
        $requestOngoing = new Request(['filter' => 'ongoing']);
        $requestOngoing->setUserResolver(function () use ($student) {
            return $student;
        });
        $responseOngoing = $controller->myCourses($requestOngoing);
        $responseDataOngoing = $this->resolveResponseData($responseOngoing, $requestOngoing);
        $dataOngoing = $responseDataOngoing['data'] ?? $responseDataOngoing;
        $this->assertArrayHasKey('courses', $dataOngoing);
        $this->assertCount(1, $dataOngoing['courses'], 'Ongoing filter should return only active enrollment');
        $this->assertEquals($courseActive->id, $dataOngoing['courses'][0]['id'] ?? $dataOngoing['courses'][0]['course_id']);

        // Filter: completed
        $requestCompleted = new Request(['filter' => 'completed']);
        $requestCompleted->setUserResolver(function () use ($student) {
            return $student;
        });
        $responseCompleted = $controller->myCourses($requestCompleted);
        $responseDataCompleted = $this->resolveResponseData($responseCompleted, $requestCompleted);
        $dataCompleted = $responseDataCompleted['data'] ?? $responseDataCompleted;
        $this->assertArrayHasKey('courses', $dataCompleted);
        $this->assertCount(1, $dataCompleted['courses'], 'Completed filter should return only completed enrollment');
        $this->assertEquals($courseCompleted->id, $dataCompleted['courses'][0]['id'] ?? $dataCompleted['courses'][0]['course_id']);
    }
}
