<?php

namespace Tests\Unit\Controller\CheckoutCourseController;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Category;
use App\Models\Course;
use App\Models\CourseAttribute;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\CourseCheckoutSession;
use App\Models\CourseEnrollment;
use App\Http\Controllers\Api\CheckoutCourseController;

class CheckoutCourseCheckoutBuyNowContentTest extends TestCase
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

    public function test_checkout_buy_now_content_returns_course_details_with_discount_coupon_and_totals()
    {
        Carbon::setTestNow(now());

        // setup users and supporting records
        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instr_' . Str::random(6),
            'first_name' => 'Instructor',
            'last_name' => 'Disc',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $student = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'stud_' . Str::random(6),
            'first_name' => 'Student',
            'last_name' => 'Disc',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Disc Category',
        ]);

        // create course with percent discount active now
        $course = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Discounted Course',
            'price' => 100000,
            'image' => null,
            'discount_value' => 10,
            'discount_type' => 'percent',
            'discount_start_at' => now()->subDay(),
            'discount_end_at' => now()->addDay(),
            'is_discount_active' => true,
        ]);

        // benefits
        CourseAttribute::create([
            'id' => Str::uuid()->toString(),
            'course_id' => $course->id,
            'type' => 'benefit',
            'content' => 'Benefit A',
        ]);
        CourseAttribute::create([
            'id' => Str::uuid()->toString(),
            'course_id' => $course->id,
            'type' => 'benefit',
            'content' => 'Benefit B',
        ]);

        // coupon nominal 5000 valid now, and usage by student for this course
        $coupon = Coupon::create([
            'id' => Str::uuid()->toString(),
            'title' => 'Nominal Coupon',
            'code' => 'TESTCPN_' . Str::random(6),
            'type' => 'nominal',
            'value' => 5000,
            'start_at' => now()->subDay(),
            'end_at' => now()->addDay(),
            'is_active' => true,
            'max_usage' => null,
            'used_count' => 0,
        ]);

        CouponUsage::create([
            'id' => Str::uuid()->toString(),
            'coupon_id' => $coupon->id,
            'user_id' => $student->id,
            'course_id' => $course->id,
        ]);

        $request = new Request();
        $request->setUserResolver(function () use ($student) {
            return $student;
        });

        $controller = new CheckoutCourseController();
        $response = $controller->checkoutBuyNowContent($course->id, $request);
        $data = $this->resolveResponseData($response, $request);

        // status & message
        $this->assertArrayHasKey('status', $data);
        if (is_bool($data['status'])) {
            $this->assertTrue($data['status']);
        } else {
            $this->assertEquals('success', $data['status']);
        }
        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('Benefits retrieved successfully', $data['message']);

        $this->assertArrayHasKey('data', $data);
        $payload = $data['data'];

        // benefit list
        $this->assertArrayHasKey('benefit', $payload);
        $this->assertIsArray($payload['benefit']);
        $this->assertContains('Benefit A', $payload['benefit']);
        $this->assertContains('Benefit B', $payload['benefit']);

        // detail_course_checkout present and title correct
        $this->assertArrayHasKey('detail_course_checkout', $payload);
        $this->assertIsArray($payload['detail_course_checkout'] ?? $payload['detail_course_checkout']->toArray());
        $detail = is_array($payload['detail_course_checkout']) ? $payload['detail_course_checkout'] : (array) $payload['detail_course_checkout'];
        $this->assertEquals('Discounted Course', $detail['title']);

        // pricing assertions
        $basePrice = 100000;
        $discount = intval($basePrice * 10 / 100); // 10000
        $priceAfterDiscount = max(0, $basePrice - $discount); // 90000
        $couponDiscount = 5000;
        $expectedTotal = max(0, $priceAfterDiscount - $couponDiscount); // 85000
        $expectedPpn = intval(round($expectedTotal * 0.12)); // 10200
        $expectedGrand = $expectedTotal + $expectedPpn; // 95200

        $this->assertArrayHasKey('total', $payload);
        $this->assertEquals($expectedTotal, $payload['total']);

        $this->assertArrayHasKey('ppn', $payload);
        $this->assertEquals($expectedPpn, $payload['ppn']);

        $this->assertArrayHasKey('grand_total', $payload);
        $this->assertEquals($expectedGrand, $payload['grand_total']);

        // coupon_usage present and already_enrolled false
        $this->assertArrayHasKey('coupon_usage', $payload);
        $this->assertNotNull($payload['coupon_usage']);
        $this->assertArrayHasKey('already_enrolled', $payload);
        $this->assertFalse($payload['already_enrolled']);
    }

    public function test_checkout_buy_now_content_returns_404_when_course_not_found()
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

        $request = new Request();
        $request->setUserResolver(function () use ($student) {
            return $student;
        });

        $controller = new CheckoutCourseController();
        $fakeId = Str::uuid()->toString();
        $response = $controller->checkoutBuyNowContent($fakeId, $request);

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

    public function test_checkout_buy_now_content_detects_already_enrolled()
    {
        Carbon::setTestNow(now());

        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instr_enr_' . Str::random(6),
            'first_name' => 'Instructor',
            'last_name' => 'Enr',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $student = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'stud_enr_' . Str::random(6),
            'first_name' => 'Student',
            'last_name' => 'Enr',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Enroll Cat',
        ]);

        $course = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Enrolled Course',
            'price' => 50000,
            'is_discount_active' => false,
        ]);

        $session = CourseCheckoutSession::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $student->id,
            'checkout_type' => 'direct',
            'payment_status' => 'paid',
            'payment_type' => 'midtrans',
        ]);

        $enrollment = CourseEnrollment::create([
            'id' => Str::uuid()->toString(),
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

        $controller = new CheckoutCourseController();
        $response = $controller->checkoutBuyNowContent($course->id, $request);
        $data = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $data);
        if (is_bool($data['status'])) {
            $this->assertTrue($data['status']);
        } else {
            $this->assertEquals('success', $data['status']);
        }

        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('Benefits retrieved successfully', $data['message']);

        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('already_enrolled', $data['data']);
        $this->assertTrue($data['data']['already_enrolled']);
    }
}