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

class EnrollmentCheckoutCartTest extends TestCase
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

    public function test_checkout_cart_returns_error_when_cart_empty()
    {
        $student = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'stud_empty_' . Str::random(6),
            'first_name' => 'Student',
            'last_name' => 'Empty',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        // No cart session created for this user (or create one without enrollments)
        $request = new Request();
        $request->setUserResolver(function () use ($student) {
            return $student;
        });

        $controller = new EnrollmentController();
        $response = $controller->checkoutCart($request);

        $data = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $data);
        if (is_bool($data['status'])) {
            $this->assertFalse($data['status']);
        } else {
            $this->assertNotEquals('success', $data['status']);
        }

        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('Cart kosong.', $data['message']);
    }

    public function test_checkout_cart_all_free_courses_activates_and_marks_session_paid()
    {
        $student = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'stud_free_' . Str::random(6),
            'first_name' => 'Student',
            'last_name' => 'Free',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instr_free_' . Str::random(6),
            'first_name' => 'Instructor',
            'last_name' => 'Free',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Free Cart Category',
            'image' => null,
        ]);

        $courseA = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Free Course A',
            'price' => 0,
            'is_discount_active' => false,
        ]);

        $courseB = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Free Course B',
            'price' => 0,
            'is_discount_active' => false,
        ]);

        $cartSession = CourseCheckoutSession::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $student->id,
            'checkout_type' => 'cart',
            'payment_status' => 'pending',
        ]);

        $enrollmentA = CourseEnrollment::create([
            'id' => Str::uuid()->toString(),
            'checkout_session_id' => $cartSession->id,
            'user_id' => $student->id,
            'course_id' => $courseA->id,
            'price' => null,
            'payment_type' => 'pending',
            'access_status' => 'inactive',
        ]);

        $enrollmentB = CourseEnrollment::create([
            'id' => Str::uuid()->toString(),
            'checkout_session_id' => $cartSession->id,
            'user_id' => $student->id,
            'course_id' => $courseB->id,
            'price' => null,
            'payment_type' => 'pending',
            'access_status' => 'inactive',
        ]);

        $request = new Request();
        $request->setUserResolver(function () use ($student) {
            return $student;
        });

        $controller = new EnrollmentController();
        $response = $controller->checkoutCart($request);

        $data = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $data);
        if (is_bool($data['status'])) {
            $this->assertTrue($data['status']);
        } else {
            $this->assertEquals('success', $data['status']);
        }

        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('Semua kursus gratis berhasil diakses.', $data['message']);

        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('enrollment_ids', $data['data']);

        $returnedIds = $data['data']['enrollment_ids'];
        $this->assertContains($enrollmentA->id, $returnedIds);
        $this->assertContains($enrollmentB->id, $returnedIds);

        // Reload from DB and assert statuses updated
        $enrollmentA->refresh();
        $enrollmentB->refresh();
        $cartSession->refresh();

        $this->assertEquals('active', $enrollmentA->access_status);
        $this->assertEquals('free', $enrollmentA->payment_type);

        $this->assertEquals('active', $enrollmentB->access_status);
        $this->assertEquals('free', $enrollmentB->payment_type);

        $this->assertEquals('paid', $cartSession->payment_status);
        $this->assertEquals('free', $cartSession->payment_type);
        $this->assertNotNull($cartSession->paid_at);
    }
}