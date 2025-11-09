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
use App\Models\CourseCheckoutSession;
use App\Models\CourseEnrollment;
use App\Http\Controllers\Api\EnrollmentController;

class EnrollmentMidtransCallbackTest extends TestCase
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

    public function test_callback_returns_order_id_missing()
    {
        $controller = new EnrollmentController();
        $request = new Request([]);
        $response = $controller->midtransCallback($request);

        $data = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $data);
        $this->assertFalse($data['status']);
        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('order_id missing', $data['message']);
    }

    public function test_callback_returns_not_found_for_unknown_order()
    {
        $controller = new EnrollmentController();
        $request = new Request(['order_id' => 'NON_EXISTENT_ORDER']);
        $response = $controller->midtransCallback($request);

        $data = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $data);
        $this->assertFalse($data['status']);
        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('Not Found', $data['message']);
    }

    public function test_settlement_marks_paid_and_activates_enrollments()
    {
        // setup minimal related records
        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instr_settle_' . Str::random(6),
            'first_name' => 'Instr',
            'last_name' => 'Settle',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'SettleCat',
            'image' => null,
        ]);

        $course1 = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Settle Course 1',
            'price' => 10000,
            'is_discount_active' => false,
        ]);

        $course2 = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Settle Course 2',
            'price' => 10000,
            'is_discount_active' => false,
        ]);

        $student = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'stud_settle_' . Str::random(6),
            'first_name' => 'Student',
            'last_name' => 'Settle',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        $session = CourseCheckoutSession::create([
            'user_id' => $student->id,
            'checkout_type' => 'direct',
            'payment_status' => 'pending',
            'payment_type' => 'midtrans',
            'midtrans_order_id' => 'ORD-SETTLE',
        ]);

        $en1 = CourseEnrollment::create([
            'id' => Str::uuid()->toString(),
            'checkout_session_id' => $session->id,
            'user_id' => $student->id,
            'course_id' => $course1->id,
            'price' => 10000,
            'payment_type' => 'midtrans',
            'access_status' => 'inactive',
        ]);

        $en2 = CourseEnrollment::create([
            'id' => Str::uuid()->toString(),
            'checkout_session_id' => $session->id,
            'user_id' => $student->id,
            'course_id' => $course2->id,
            'price' => 10000,
            'payment_type' => 'midtrans',
            'access_status' => 'inactive',
        ]);

        $controller = new EnrollmentController();
        $payload = [
            'order_id' => 'ORD-SETTLE',
            'transaction_status' => 'settlement',
            'transaction_id' => 'TX-SETTLE-1',
        ];
        $request = new Request($payload);
        $response = $controller->midtransCallback($request);

        $data = $this->resolveResponseData($response, $request);
        $this->assertArrayHasKey('status', $data);
        $this->assertTrue($data['status']);
        $this->assertEquals('OK', $data['message']);

        $sessionFresh = CourseCheckoutSession::find($session->id);
        $this->assertEquals('paid', $sessionFresh->payment_status);
        $this->assertEquals('settlement', $sessionFresh->transaction_status);
        $this->assertEquals('TX-SETTLE-1', $sessionFresh->midtrans_transaction_id);
        $this->assertNotNull($sessionFresh->paid_at);

        $en1Fresh = CourseEnrollment::find($en1->id);
        $en2Fresh = CourseEnrollment::find($en2->id);
        $this->assertEquals('active', $en1Fresh->access_status);
        $this->assertEquals('active', $en2Fresh->access_status);
    }
}