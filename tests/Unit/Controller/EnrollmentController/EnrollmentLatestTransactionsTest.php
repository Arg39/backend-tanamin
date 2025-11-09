<?php

namespace Tests\Unit\Controller\EnrollmentController;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\Category;
use App\Models\Course;
use App\Models\User;
use App\Models\CourseCheckoutSession;
use App\Models\CourseEnrollment;
use App\Http\Controllers\Api\EnrollmentController;

class EnrollmentLatestTransactionsTest extends TestCase
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

    public function test_latest_transactions_returns_sessions_sorted_and_transformed()
    {
        Carbon::setTestNow(now());

        // create supporting records
        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Trans Cat',
        ]);

        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instr_' . Str::random(6),
            'first_name' => 'Instructor',
            'last_name' => 'Trans',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $studentA = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'stud_a_' . Str::random(6),
            'first_name' => 'Alice',
            'last_name' => 'Buyer',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        $studentB = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'stud_b_' . Str::random(6),
            'first_name' => 'Bob',
            'last_name' => 'Buyer',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        $course1 = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Course One',
            'price' => 100000,
            'is_discount_active' => false,
        ]);

        $course2 = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Course Two',
            'price' => 50000,
            'is_discount_active' => false,
        ]);

        // older session (created earlier)
        $sessionOld = CourseCheckoutSession::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $studentA->id,
            'checkout_type' => 'direct',
            'payment_status' => 'paid',
            'payment_type' => 'midtrans',
            'midtrans_order_id' => 'ORD-OLD',
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);

        // newer session (created later)
        $sessionNew = CourseCheckoutSession::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $studentB->id,
            'checkout_type' => 'direct',
            'payment_status' => 'pending',
            'payment_type' => 'midtrans',
            'midtrans_order_id' => 'ORD-NEW',
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        // enrollments for sessions (capture enrollment records to assert ids)
        $enrollOld = CourseEnrollment::create([
            'id' => Str::uuid()->toString(),
            'checkout_session_id' => $sessionOld->id,
            'user_id' => $studentA->id,
            'course_id' => $course1->id,
            'price' => 90000,
            'payment_type' => 'midtrans',
            'access_status' => 'active',
        ]);

        $enrollNew = CourseEnrollment::create([
            'id' => Str::uuid()->toString(),
            'checkout_session_id' => $sessionNew->id,
            'user_id' => $studentB->id,
            'course_id' => $course2->id,
            'price' => 50000,
            'payment_type' => 'midtrans',
            'access_status' => 'inactive',
        ]);

        $controller = new EnrollmentController();
        $request = new Request(['perPage' => 10, 'page' => 1, 'sortBy' => 'created_at', 'sortOrder' => 'desc']);
        $response = $controller->latestTransactions($request);

        $data = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $data);
        if (is_bool($data['status'])) {
            $this->assertTrue($data['status']);
        } else {
            $this->assertEquals('success', $data['status']);
        }

        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('Latest transactions retrieved successfully', $data['message']);

        $this->assertArrayHasKey('data', $data);
        $returned = $data['data'];

        // Paginated resource may nest items under data or items
        if (is_array($returned) && isset($returned['data'])) {
            $returned = $returned['data'];
        }

        if (isset($returned['items'])) {
            $items = $returned['items'];
        } elseif (isset($returned['data'])) {
            $items = $returned['data'];
        } else {
            // if it's a LengthAwarePaginator converted to array
            $items = $returned;
        }

        $this->assertIsArray($items);
        $this->assertCount(2, $items);

        // newest first (sessionNew corresponds to first item)
        $first = $items[0];
        $second = $items[1];

        // controller returns checkout session id in transformed data, assert against session ids
        $this->assertEquals($sessionNew->id, $first['id']);
        $this->assertEquals($studentB->first_name . ' ' . $studentB->last_name, $first['user']);
        $this->assertEquals(50000, $first['price']);
        $this->assertEquals(['Course Two'], $first['courses']);
        $this->assertEquals('pending', $first['payment_status']);

        $this->assertEquals($sessionOld->id, $second['id']);
        $this->assertEquals($studentA->first_name . ' ' . $studentA->last_name, $second['user']);
        $this->assertEquals(90000, $second['price']);
        $this->assertEquals(['Course One'], $second['courses']);
        $this->assertEquals('paid', $second['payment_status']);
    }

    public function test_latest_transactions_filters_by_user_search_and_sorts_by_payment_status()
    {
        // create supporting records
        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Filter Cat',
        ]);

        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instr2_' . Str::random(6),
            'first_name' => 'Instructor',
            'last_name' => 'Filter',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $matchUser = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'match_' . Str::random(6),
            'first_name' => 'Match',
            'last_name' => 'User',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        $otherUser = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'other_' . Str::random(6),
            'first_name' => 'Other',
            'last_name' => 'User',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        $courseA = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Filter Course A',
            'price' => 20000,
            'is_discount_active' => false,
        ]);

        // sessions
        $sessMatch = CourseCheckoutSession::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $matchUser->id,
            'checkout_type' => 'direct',
            'payment_status' => 'expired',
            'payment_type' => 'midtrans',
            'midtrans_order_id' => 'ORD-MATCH',
            'created_at' => now()->subHours(3),
        ]);

        $sessOther = CourseCheckoutSession::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $otherUser->id,
            'checkout_type' => 'direct',
            'payment_status' => 'paid',
            'payment_type' => 'midtrans',
            'midtrans_order_id' => 'ORD-OTHER',
            'created_at' => now()->subHours(2),
        ]);

        $enrollMatch = CourseEnrollment::create([
            'id' => Str::uuid()->toString(),
            'checkout_session_id' => $sessMatch->id,
            'user_id' => $matchUser->id,
            'course_id' => $courseA->id,
            'price' => 20000,
            'payment_type' => 'midtrans',
            'access_status' => 'inactive',
        ]);

        $enrollOther = CourseEnrollment::create([
            'id' => Str::uuid()->toString(),
            'checkout_session_id' => $sessOther->id,
            'user_id' => $otherUser->id,
            'course_id' => $courseA->id,
            'price' => 20000,
            'payment_type' => 'midtrans',
            'access_status' => 'active',
        ]);

        $controller = new EnrollmentController();
        // search for 'Match' and sort by payment_status ascending
        $request = new Request(['user' => 'Match', 'sortBy' => 'payment_status', 'sortOrder' => 'asc']);
        $response = $controller->latestTransactions($request);

        $data = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $data);
        if (is_bool($data['status'])) {
            $this->assertTrue($data['status']);
        } else {
            $this->assertEquals('success', $data['status']);
        }

        $this->assertArrayHasKey('data', $data);
        $returned = $data['data'];
        if (is_array($returned) && isset($returned['data'])) {
            $returned = $returned['data'];
        }
        if (isset($returned['items'])) {
            $items = $returned['items'];
        } elseif (isset($returned['data'])) {
            $items = $returned['data'];
        } else {
            $items = $returned;
        }

        $this->assertIsArray($items);
        // only one matching session (matchUser)
        $this->assertCount(1, $items);
        $only = $items[0];
        // controller returns checkout session id in transformed data
        $this->assertEquals($sessMatch->id, $only['id']);
        $this->assertStringContainsString('Match', $only['user']);
        $this->assertEquals('expired', $only['payment_status']);
    }
}