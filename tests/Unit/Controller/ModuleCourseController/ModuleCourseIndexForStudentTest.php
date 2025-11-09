<?php

namespace Tests\Unit\Controller\ModuleCourseController;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use App\Models\Course;
use App\Models\ModuleCourse;
use App\Models\LessonCourse;
use App\Models\LessonMaterial;
use App\Models\LessonProgress;
use App\Models\User;
use App\Models\Category;
use App\Models\CourseEnrollment;
use App\Models\CourseCheckoutSession; // added to create a checkout session for FK
use App\Http\Controllers\Api\Course\Material\ModuleCourseController;
use Tymon\JWTAuth\Facades\JWTAuth;

class ModuleCourseIndexForStudentTest extends TestCase
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

    public function test_index_for_student_returns_403_when_not_authenticated()
    {
        JWTAuth::shouldReceive('user')->andReturn(null);

        $controller = new ModuleCourseController();
        $request = new Request();
        // create a dummy course to pass an id (no DB lookups required by auth check)
        $fakeCourseId = Str::uuid()->toString();
        $response = $controller->indexForStudent($fakeCourseId);

        $responseData = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $responseData);
        // expect failure status
        if (is_bool($responseData['status'])) {
            $this->assertFalse($responseData['status']);
        } else {
            $this->assertNotEquals('success', $responseData['status']);
        }

        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Forbidden: User not authenticated', $responseData['message']);
    }

    public function test_index_for_student_returns_403_when_enrollment_missing()
    {
        // create a student user
        $user = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'stud_' . Str::random(6),
            'first_name' => 'Stud',
            'last_name' => 'NoEnroll',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);
        JWTAuth::shouldReceive('user')->andReturn($user);

        // create course (no enrollment)
        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'NoEnroll Cat',
            'image' => null,
        ]);
        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instr_' . Str::random(6),
            'first_name' => 'Instr',
            'last_name' => 'NoEnroll',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);
        $course = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Course Without Enrollment',
            'price' => null,
            'is_discount_active' => false,
        ]);

        $controller = new ModuleCourseController();
        $request = new Request();
        $response = $controller->indexForStudent($course->id);

        $responseData = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $responseData);
        if (is_bool($responseData['status'])) {
            $this->assertFalse($responseData['status']);
        } else {
            $this->assertNotEquals('success', $responseData['status']);
        }

        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Forbidden: Enrollment not found', $responseData['message']);
    }

    public function test_index_for_student_with_active_free_enrollment_returns_modules_and_completed_flags()
    {
        // create student and mock auth
        $user = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'stud_' . Str::random(6),
            'first_name' => 'Stud',
            'last_name' => 'Active',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);
        JWTAuth::shouldReceive('user')->andReturn($user);

        // create related records required by DB constraints
        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Active Cat',
            'image' => null,
        ]);
        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instr_' . Str::random(6),
            'first_name' => 'Instr',
            'last_name' => 'Active',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);
        $course = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Course For Student Index',
            'price' => null,
            'is_discount_active' => false,
        ]);

        // create modules and lessons
        $module = ModuleCourse::create([
            'id' => Str::uuid()->toString(),
            'course_id' => $course->id,
            'title' => 'Student Module',
            'order' => 0,
        ]);

        $lesson1 = LessonCourse::create([
            'id' => Str::uuid()->toString(),
            'module_id' => $module->id,
            'title' => 'Material One',
            'type' => 'material',
            'order' => 0,
        ]);
        LessonMaterial::create([
            'id' => Str::uuid()->toString(),
            'lesson_id' => $lesson1->id,
            'content' => '',
            'visible' => true,
        ]);

        $lesson2 = LessonCourse::create([
            'id' => Str::uuid()->toString(),
            'module_id' => $module->id,
            'title' => 'Material Two',
            'type' => 'material',
            'order' => 1,
        ]);
        LessonMaterial::create([
            'id' => Str::uuid()->toString(),
            'lesson_id' => $lesson2->id,
            'content' => '',
            'visible' => true,
        ]);

        // create a checkout session to satisfy DB foreign key (checkout_session_id is not nullable)
        $checkout = CourseCheckoutSession::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $user->id,
            'payment_status' => 'pending',
        ]);

        // create active free enrollment for the student (attach checkout_session_id)
        CourseEnrollment::create([
            'id' => Str::uuid()->toString(),
            'checkout_session_id' => $checkout->id,
            'user_id' => $user->id,
            'course_id' => $course->id,
            'payment_type' => 'free',
            'access_status' => 'active',
        ]);

        // mark lesson1 as completed for user
        LessonProgress::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $user->id,
            'lesson_id' => $lesson1->id,
            'completed_at' => now(),
        ]);

        $controller = new ModuleCourseController();
        $request = new Request();
        $response = $controller->indexForStudent($course->id);

        $responseData = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $responseData);
        if (is_bool($responseData['status'])) {
            $this->assertTrue($responseData['status']);
        } else {
            $this->assertEquals('success', $responseData['status']);
        }

        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Modules fetched successfully', $responseData['message']);

        $this->assertArrayHasKey('data', $responseData);
        $data = $responseData['data'];
        $this->assertIsArray($data);

        // find our module and verify lessons
        $found = null;
        foreach ($data as $m) {
            if ($m['id'] === $module->id) {
                $found = $m;
                break;
            }
        }
        $this->assertNotNull($found, 'Module should be present in response');
        $this->assertIsArray($found['lessons']);

        $map = [];
        foreach ($found['lessons'] as $l) {
            $map[$l['id']] = $l;
        }

        $this->assertArrayHasKey($lesson1->id, $map);
        $this->assertArrayHasKey('visible', $map[$lesson1->id]);
        $this->assertTrue($map[$lesson1->id]['visible']);
        $this->assertTrue($map[$lesson1->id]['completed']);

        $this->assertArrayHasKey($lesson2->id, $map);
        $this->assertArrayHasKey('visible', $map[$lesson2->id]);
        $this->assertTrue($map[$lesson2->id]['visible']);
        $this->assertFalse($map[$lesson2->id]['completed']);
    }
}
