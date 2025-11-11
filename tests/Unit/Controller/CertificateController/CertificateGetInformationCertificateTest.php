<?php

namespace Tests\Unit\Controller\CertificateController;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Models\User;
use App\Models\Course;
use App\Models\Category;
use App\Models\ModuleCourse;
use App\Models\LessonCourse;
use App\Models\LessonProgress;
use App\Models\Certificate;
use App\Models\CourseEnrollment;
use App\Models\CourseCheckoutSession;
use App\Http\Controllers\Api\Course\CertificateController;

class CertificateGetInformationCertificateTest extends TestCase
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

    public function test_returns_error_when_not_all_lessons_completed()
    {
        $user = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'stud_nc_' . Str::random(6),
            'first_name' => 'Student',
            'last_name' => 'NotComplete',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        // create required category and instructor to satisfy DB constraints
        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Test Category',
            'image' => null,
        ]);

        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instr_nc_' . Str::random(6),
            'first_name' => 'Instructor',
            'last_name' => 'NC',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $course = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Incomplete Course',
            'price' => 0,
            'is_discount_active' => false,
        ]);

        $module = ModuleCourse::create([
            'id' => Str::uuid()->toString(),
            'course_id' => $course->id,
            'title' => 'Module 1',
        ]);

        // create two lessons but mark progress for only one
        $lesson1 = LessonCourse::create([
            'id' => Str::uuid()->toString(),
            'module_id' => $module->id,
            'title' => 'Lesson 1',
        ]);
        $lesson2 = LessonCourse::create([
            'id' => Str::uuid()->toString(),
            'module_id' => $module->id,
            'title' => 'Lesson 2',
        ]);

        LessonProgress::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $user->id,
            'lesson_id' => $lesson1->id,
            'completed_at' => now(),
        ]);

        // mock JWTAuth user
        JWTAuth::shouldReceive('user')->andReturn($user);

        $controller = new CertificateController();
        $response = $controller->getInformationCertificate($course->id);

        $data = $this->resolveResponseData($response, new Request());

        $this->assertArrayHasKey('status', $data);
        if (is_bool($data['status'])) {
            $this->assertFalse($data['status']);
        } else {
            $this->assertNotEquals('success', $data['status']);
        }

        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('Anda belum menyelesaikan semua pelajaran pada kursus ini.', $data['message']);
    }

    public function test_creates_certificate_when_all_lessons_completed()
    {
        $user = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'stud_complete_' . Str::random(6),
            'first_name' => 'Student',
            'last_name' => 'Complete',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instr_' . Str::random(6),
            'first_name' => 'Instructor',
            'last_name' => 'One',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Complete Category',
            'image' => null,
        ]);

        $course = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Complete Course',
            'price' => 0,
            'is_discount_active' => false,
        ]);

        $module = ModuleCourse::create([
            'id' => Str::uuid()->toString(),
            'course_id' => $course->id,
            'title' => 'Module 1',
        ]);

        // create two lessons and mark progress for both
        $lesson1 = LessonCourse::create([
            'id' => Str::uuid()->toString(),
            'module_id' => $module->id,
            'title' => 'Lesson 1',
        ]);
        $lesson2 = LessonCourse::create([
            'id' => Str::uuid()->toString(),
            'module_id' => $module->id,
            'title' => 'Lesson 2',
        ]);

        LessonProgress::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $user->id,
            'lesson_id' => $lesson1->id,
            'completed_at' => now(),
        ]);
        LessonProgress::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $user->id,
            'lesson_id' => $lesson2->id,
            'completed_at' => now(),
        ]);

        // create a checkout session and an enrollment so controller can update access_status to 'completed'
        $session = CourseCheckoutSession::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $user->id,
            'checkout_type' => 'direct',
            'payment_status' => 'paid',
            'payment_type' => 'free',
            'paid_at' => now(),
        ]);

        $enrollment = CourseEnrollment::create([
            'id' => Str::uuid()->toString(),
            'checkout_session_id' => $session->id,
            'user_id' => $user->id,
            'course_id' => $course->id,
            'price' => 0,
            'access_status' => 'inactive',
        ]);

        // ensure no certificate exists
        $this->assertNull(Certificate::where('user_id', $user->id)->where('course_id', $course->id)->first());

        // mock JWTAuth user
        JWTAuth::shouldReceive('user')->andReturn($user);

        $controller = new CertificateController();
        $response = $controller->getInformationCertificate($course->id);

        $data = $this->resolveResponseData($response, new Request());

        $this->assertArrayHasKey('status', $data);
        if (is_bool($data['status'])) {
            $this->assertTrue($data['status']);
        } else {
            $this->assertEquals('success', $data['status']);
        }

        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('Sertifikat berhasil dibuat.', $data['message']);

        $this->assertArrayHasKey('data', $data);
        $payload = $data['data'];

        $this->assertArrayHasKey('certificate_code', $payload);
        $this->assertArrayHasKey('user_fullname', $payload);
        $this->assertEquals($course->title, $payload['course_title']);

        // assert certificate exists in DB
        $cert = Certificate::where('user_id', $user->id)->where('course_id', $course->id)->first();
        $this->assertNotNull($cert);

        // enrollment should be updated to completed
        $enrollment->refresh();
        $this->assertEquals('completed', $enrollment->access_status);
    }

    public function test_returns_existing_certificate_when_already_created()
    {
        $user = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'stud_existing_' . Str::random(6),
            'first_name' => 'Student',
            'last_name' => 'Existing',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instr_exist_' . Str::random(6),
            'first_name' => 'Instructor',
            'last_name' => 'Exist',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Existing Category',
            'image' => null,
        ]);

        $course = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Existing Certificate Course',
            'price' => 0,
            'is_discount_active' => false,
        ]);

        $module = ModuleCourse::create([
            'id' => Str::uuid()->toString(),
            'course_id' => $course->id,
            'title' => 'Module 1',
        ]);

        $lesson = LessonCourse::create([
            'id' => Str::uuid()->toString(),
            'module_id' => $module->id,
            'title' => 'Lesson 1',
        ]);

        // mark lesson completed
        LessonProgress::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $user->id,
            'lesson_id' => $lesson->id,
            'completed_at' => now(),
        ]);

        // create existing certificate
        $existing = Certificate::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $user->id,
            'course_id' => $course->id,
            'certificate_code' => 'TC-EXIST-' . Str::upper(Str::random(6)),
            'issued_at' => now(),
        ]);

        // mock JWTAuth user
        JWTAuth::shouldReceive('user')->andReturn($user);

        $controller = new CertificateController();
        $response = $controller->getInformationCertificate($course->id);

        $data = $this->resolveResponseData($response, new Request());

        $this->assertArrayHasKey('status', $data);
        if (is_bool($data['status'])) {
            $this->assertTrue($data['status']);
        } else {
            $this->assertEquals('success', $data['status']);
        }

        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('Sertifikat sudah tersedia.', $data['message']);

        $this->assertArrayHasKey('data', $data);
        $payload = $data['data'];
        $this->assertEquals($existing->certificate_code, $payload['certificate_code']);
        $this->assertEquals($course->title, $payload['course_title']);
    }
}
