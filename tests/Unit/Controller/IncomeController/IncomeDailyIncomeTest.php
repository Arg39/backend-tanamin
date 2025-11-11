<?php

namespace Tests\Unit\Controller\IncomeController;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Category;
use App\Models\Course;
use App\Models\CourseCheckoutSession;
use App\Models\CourseEnrollment;
use App\Http\Controllers\Api\IncomeController;

class IncomeDailyIncomeTest extends TestCase
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

    public function test_dailyIncome_groups_by_date_and_returns_totals()
    {
        // create a student (not strictly required by controller but keep consistent)
        $student = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'stud_income_' . Str::random(6),
            'first_name' => 'Student',
            'last_name' => 'Income',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        // create an instructor and category so we can create Course records for FK constraints
        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instr_income_' . Str::random(6),
            'first_name' => 'Instr',
            'last_name' => 'Income',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'IncomeCategory',
            'image' => null,
        ]);

        // Create courses to satisfy course_enrollments.course_id FK
        $courseA = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Income Course A',
            'price' => 100000,
            'is_discount_active' => false,
        ]);

        $courseB = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Income Course B',
            'price' => 50000,
            'is_discount_active' => false,
        ]);

        $courseC = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Income Course C',
            'price' => 60000,
            'is_discount_active' => false,
        ]);

        // Prepare dates
        $today = Carbon::now()->startOfDay();
        $yesterday = Carbon::now()->subDay()->startOfDay();

        // Two paid sessions on the same date (today)
        $sessionToday1 = CourseCheckoutSession::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $student->id,
            'checkout_type' => 'direct',
            'payment_status' => 'paid',
            'payment_type' => 'midtrans',
            'midtrans_order_id' => 'ORD-TODAY-1',
            'paid_at' => $today->copy()->addHours(10), // same date
        ]);

        $sessionToday2 = CourseCheckoutSession::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $student->id,
            'checkout_type' => 'direct',
            'payment_status' => 'paid',
            'payment_type' => 'midtrans',
            'midtrans_order_id' => 'ORD-TODAY-2',
            'paid_at' => $today->copy()->addHours(15), // same date
        ]);

        // One paid session yesterday
        $sessionYesterday = CourseCheckoutSession::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $student->id,
            'checkout_type' => 'direct',
            'payment_status' => 'paid',
            'payment_type' => 'midtrans',
            'midtrans_order_id' => 'ORD-YEST',
            'paid_at' => $yesterday->copy()->addHours(12),
        ]);

        // Enrollments tied to sessions with prices
        // Use existing course ids to satisfy FK constraints

        // Today's enrollments (two)
        CourseEnrollment::create([
            'id' => Str::uuid()->toString(),
            'checkout_session_id' => $sessionToday1->id,
            'user_id' => $student->id,
            'course_id' => $courseA->id,
            'price' => 100000,
            'payment_type' => 'midtrans',
            'access_status' => 'active',
        ]);

        CourseEnrollment::create([
            'id' => Str::uuid()->toString(),
            'checkout_session_id' => $sessionToday2->id,
            'user_id' => $student->id,
            'course_id' => $courseB->id,
            'price' => 50000,
            'payment_type' => 'midtrans',
            'access_status' => 'active',
        ]);

        // Yesterday's enrollment (one)
        CourseEnrollment::create([
            'id' => Str::uuid()->toString(),
            'checkout_session_id' => $sessionYesterday->id,
            'user_id' => $student->id,
            'course_id' => $courseC->id,
            'price' => 60000,
            'payment_type' => 'midtrans',
            'access_status' => 'active',
        ]);

        // Build request and call controller
        $request = new Request();
        $request->setUserResolver(function () use ($student) {
            return $student;
        });

        $controller = new IncomeController();
        $response = $controller->dailyIncome($request);

        $data = $this->resolveResponseData($response, $request);

        // Basic response assertions
        $this->assertArrayHasKey('status', $data);
        if (is_bool($data['status'])) {
            $this->assertTrue($data['status']);
        } else {
            $this->assertEquals('success', $data['status']);
        }

        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('Daily income retrieved successfully.', $data['message']);

        // Drill into returned paginated payload
        $this->assertArrayHasKey('data', $data);
        $this->assertIsArray($data['data']);
        $this->assertArrayHasKey('items', $data['data']);
        $this->assertArrayHasKey('pagination', $data['data']);

        $items = $data['data']['items'];

        // Ensure items is an array of groups
        $this->assertIsArray($items);

        // Expect two grouped days (today and yesterday)
        $this->assertCount(2, $items);

        // First item should be the most recent date (today) because default sortOrder desc
        $first = $items[0];
        $second = $items[1];

        // Compute expected formatted dates using same logic as controller
        $expectedTodayLabel = Carbon::parse($today)->locale('id')->translatedFormat('d F Y');
        $expectedYesterdayLabel = Carbon::parse($yesterday)->locale('id')->translatedFormat('d F Y');

        // Totals expected
        $expectedTodayTotal = 100000 + 50000; // 150000
        $expectedTodayCount = 2;
        $expectedYesterdayTotal = 60000;
        $expectedYesterdayCount = 1;

        $this->assertEquals($expectedTodayLabel, $first['date']);
        $this->assertEquals($expectedTodayTotal, intval($first['total_income']));
        $this->assertEquals($expectedTodayCount, intval($first['total_paid_enrollments']));

        $this->assertEquals($expectedYesterdayLabel, $second['date']);
        $this->assertEquals($expectedYesterdayTotal, intval($second['total_income']));
        $this->assertEquals($expectedYesterdayCount, intval($second['total_paid_enrollments']));
    }
}
