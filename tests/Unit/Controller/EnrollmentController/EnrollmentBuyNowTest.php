<?php

namespace Tests\Unit\Controller\EnrollmentController;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\Category;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseCheckoutSession;
use App\Http\Controllers\Api\EnrollmentController;

class EnrollmentBuyNowTest extends TestCase
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

    protected function tearDown(): void
    {
        if (class_exists(\Mockery::class)) {
            \Mockery::close();
        }
        parent::tearDown();
    }

    public function test_buyNow_grants_free_access_for_free_course()
    {
        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instr_' . Str::random(6),
            'first_name' => 'Inst',
            'last_name' => 'Free',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'FreeCourseCat',
            'image' => null,
        ]);

        $course = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Free Course',
            'price' => 0,
            'is_discount_active' => false,
        ]);

        $student = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'stud_free_' . Str::random(6),
            'first_name' => 'Student',
            'last_name' => 'Free',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        $request = new Request();
        $request->setUserResolver(function () use ($student) {
            return $student;
        });

        $controller = new EnrollmentController();
        $response = $controller->buyNow($request, $course->id);

        $data = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $data);
        if (is_bool($data['status'])) {
            $this->assertTrue($data['status']);
        } else {
            $this->assertEquals('success', $data['status']);
        }

        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('Kursus berhasil diakses secara gratis.', $data['message']);

        // Assert enrollment created and active
        $this->assertTrue(
            CourseEnrollment::where('user_id', $student->id)
                ->where('course_id', $course->id)
                ->where('access_status', 'active')
                ->exists()
        );

        // Assert checkout session created and paid
        $this->assertTrue(
            CourseCheckoutSession::where('user_id', $student->id)
                ->where('payment_status', 'paid')
                ->exists()
        );
    }

    public function test_buyNow_creates_midtrans_session_for_paid_course()
    {
        // mock MidtransService static call to avoid external dependency
        if (class_exists(\Mockery::class)) {
            \Mockery::mock('alias:App\Services\MidtransService')
                ->shouldReceive('createTransaction')
                ->andReturn((object)['redirect_url' => 'https://midtrans.test/checkout']);
        }

        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instr_' . Str::random(6),
            'first_name' => 'Inst',
            'last_name' => 'Paid',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'PaidCourseCat',
            'image' => null,
        ]);

        $course = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Paid Course',
            'price' => 100000,
            'is_discount_active' => false,
        ]);

        $student = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'stud_paid_' . Str::random(6),
            'first_name' => 'Student',
            'last_name' => 'Paid',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        $request = new Request();
        $request->setUserResolver(function () use ($student) {
            return $student;
        });

        $controller = new EnrollmentController();
        $response = $controller->buyNow($request, $course->id);

        $data = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $data);
        if (is_bool($data['status'])) {
            $this->assertTrue($data['status']);
        } else {
            $this->assertEquals('success', $data['status']);
        }

        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('Silakan lanjutkan pembayaran.', $data['message']);

        $this->assertArrayHasKey('data', $data);
        $this->assertIsArray($data['data']);
        $this->assertArrayHasKey('redirect_url', $data['data']);
        $this->assertEquals('https://midtrans.test/checkout', $data['data']['redirect_url']);

        // Assert checkout session pending exists
        $this->assertTrue(
            CourseCheckoutSession::where('user_id', $student->id)
                ->where('payment_status', 'pending')
                ->exists()
        );

        // Assert enrollment exists and is inactive (pending)
        $this->assertTrue(
            CourseEnrollment::where('user_id', $student->id)
                ->where('course_id', $course->id)
                ->where('access_status', 'inactive')
                ->exists()
        );
    }

    public function test_buyNow_returns_error_if_already_enrolled()
    {
        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instr_' . Str::random(6),
            'first_name' => 'Inst',
            'last_name' => 'Enrolled',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'EnrolledCat',
            'image' => null,
        ]);

        $course = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Already Enrolled Course',
            'price' => 50000,
            'is_discount_active' => false,
        ]);

        $student = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'stud_en_' . Str::random(6),
            'first_name' => 'Student',
            'last_name' => 'Enrolled',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        // create a paid checkout session and enrollment to simulate already bought
        $session = CourseCheckoutSession::create([
            'user_id' => $student->id,
            'checkout_type' => 'direct',
            'payment_status' => 'paid',
            'payment_type' => 'midtrans',
            'midtrans_order_id' => 'ORD-EXISTING',
            'paid_at' => now(),
        ]);

        CourseEnrollment::create([
            'checkout_session_id' => $session->id,
            'user_id' => $student->id,
            'course_id' => $course->id,
            'price' => 50000,
            'payment_type' => 'midtrans',
            'access_status' => 'active',
        ]);

        $request = new Request();
        $request->setUserResolver(function () use ($student) {
            return $student;
        });

        $controller = new EnrollmentController();
        $response = $controller->buyNow($request, $course->id);

        $data = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $data);
        if (is_bool($data['status'])) {
            $this->assertFalse($data['status']);
        } else {
            $this->assertNotEquals('success', $data['status']);
        }

        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('Kursus ini sudah dibeli.', $data['message']);
    }
}