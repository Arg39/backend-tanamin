<?php

namespace Tests\Unit\Controller\CheckoutCourseController;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\Category;
use App\Models\Course;
use App\Models\CourseAttribute;
use App\Models\CourseCheckoutSession;
use App\Models\CourseEnrollment;
use App\Http\Controllers\Api\CheckoutCourseController;

class CheckoutCourseCheckoutCartContentTest extends TestCase
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

    public function test_checkout_cart_returns_empty_when_no_cart_session_or_no_enrollments()
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

        $request = new Request();
        $request->setUserResolver(function () use ($student) {
            return $student;
        });

        $controller = new CheckoutCourseController();
        $response = $controller->checkoutCartContent($request);

        $data = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $data);
        if (is_bool($data['status'])) {
            $this->assertTrue($data['status']);
        } else {
            $this->assertEquals('success', $data['status']);
        }

        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('Cart kosong.', $data['message']);

        $this->assertArrayHasKey('data', $data);
        $payload = $data['data'];
        $this->assertEquals([], $payload['benefit']);
        $this->assertEquals([], $payload['courses']);
        $this->assertEquals(0, $payload['total']);
        $this->assertEquals(0, $payload['ppn']);
        $this->assertEquals(0, $payload['grand_total']);
    }

    public function test_checkout_cart_returns_cart_with_courses_and_totals()
    {
        // create supporting records
        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instr_' . Str::random(6),
            'first_name' => 'Instructor',
            'last_name' => 'Cart',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $student = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'stud_cart_' . Str::random(6),
            'first_name' => 'Student',
            'last_name' => 'Cart',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Cart Category',
        ]);

        $courseA = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Cart Course A',
            'price' => 100000,
            'image' => null,
            'discount_value' => 10,
            'discount_type' => 'percent',
            'discount_start_at' => now()->subDay(),
            'discount_end_at' => now()->addDay(),
            'is_discount_active' => true,
        ]);

        $courseB = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Cart Course B',
            'price' => 50000,
            'image' => null,
            'is_discount_active' => false,
        ]);

        // benefits
        CourseAttribute::create([
            'id' => Str::uuid()->toString(),
            'course_id' => $courseA->id,
            'type' => 'benefit',
            'content' => 'Benefit A1',
        ]);
        CourseAttribute::create([
            'id' => Str::uuid()->toString(),
            'course_id' => $courseB->id,
            'type' => 'benefit',
            'content' => 'Benefit B1',
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

        $controller = new CheckoutCourseController();
        $response = $controller->checkoutCartContent($request);

        $data = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $data);
        if (is_bool($data['status'])) {
            $this->assertTrue($data['status']);
        } else {
            $this->assertEquals('success', $data['status']);
        }

        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('Checkout cart berhasil diambil.', $data['message']);

        $this->assertArrayHasKey('data', $data);
        $payload = $data['data'];

        // benefits collected (unique)
        $this->assertArrayHasKey('benefit', $payload);
        $this->assertIsArray($payload['benefit']);
        $this->assertContains('Benefit A1', $payload['benefit']);
        $this->assertContains('Benefit B1', $payload['benefit']);

        // courses details
        $this->assertArrayHasKey('courses', $payload);
        $this->assertIsArray($payload['courses']);
        $this->assertCount(2, $payload['courses']);

        // calculate expected values
        $baseA = 100000;
        $discountA = intval($baseA * 10 / 100); // 10000
        $afterA = max(0, $baseA - $discountA); // 90000

        $baseB = 50000;
        $discountB = 0;
        $afterB = $baseB; // 50000

        $expectedTotal = $afterA + $afterB; // 140000
        $expectedPpn = intval(round($expectedTotal * 0.12)); // 16800
        $expectedGrand = $expectedTotal + $expectedPpn; // 156800

        $this->assertEquals($expectedTotal, $payload['total']);
        $this->assertEquals($expectedPpn, $payload['ppn']);
        $this->assertEquals($expectedGrand, $payload['grand_total']);

        // assert courses contain expected entries by id mapping
        $foundA = null;
        $foundB = null;
        foreach ($payload['courses'] as $c) {
            if ($c['id'] === $courseA->id) {
                $foundA = $c;
            }
            if ($c['id'] === $courseB->id) {
                $foundB = $c;
            }
        }
        $this->assertNotNull($foundA);
        $this->assertEquals('Cart Course A', $foundA['title']);
        $this->assertEquals($baseA, $foundA['base_price']);
        $this->assertEquals($discountA, $foundA['discount']);
        $this->assertEquals($afterA, $foundA['price_after_discount']);

        $this->assertNotNull($foundB);
        $this->assertEquals('Cart Course B', $foundB['title']);
        $this->assertEquals($baseB, $foundB['base_price']);
        $this->assertEquals($discountB, $foundB['discount']);
        $this->assertEquals($afterB, $foundB['price_after_discount']);
    }

    public function test_checkout_cart_ignores_active_enrollments_and_returns_empty_when_none_inactive()
    {
        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instr_act_' . Str::random(6),
            'first_name' => 'Instructor',
            'last_name' => 'Act',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $student = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'stud_act_' . Str::random(6),
            'first_name' => 'Student',
            'last_name' => 'Act',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Active Cat',
        ]);

        $course = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Active Course',
            'price' => 20000,
            'is_discount_active' => false,
        ]);

        $cartSession = CourseCheckoutSession::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $student->id,
            'checkout_type' => 'cart',
            'payment_status' => 'pending',
        ]);

        // enrollment is active -> should be ignored by checkoutCartContent (it queries inactive)
        CourseEnrollment::create([
            'id' => Str::uuid()->toString(),
            'checkout_session_id' => $cartSession->id,
            'user_id' => $student->id,
            'course_id' => $course->id,
            'price' => 20000,
            'payment_type' => 'midtrans',
            'access_status' => 'active',
        ]);

        $request = new Request();
        $request->setUserResolver(function () use ($student) {
            return $student;
        });

        $controller = new CheckoutCourseController();
        $response = $controller->checkoutCartContent($request);

        $data = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $data);
        if (is_bool($data['status'])) {
            $this->assertTrue($data['status']);
        } else {
            $this->assertEquals('success', $data['status']);
        }

        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('Cart kosong.', $data['message']);

        $this->assertArrayHasKey('data', $data);
        $payload = $data['data'];
        $this->assertEquals([], $payload['benefit']);
        $this->assertEquals([], $payload['courses']);
        $this->assertEquals(0, $payload['total']);
        $this->assertEquals(0, $payload['ppn']);
        $this->assertEquals(0, $payload['grand_total']);
    }
}