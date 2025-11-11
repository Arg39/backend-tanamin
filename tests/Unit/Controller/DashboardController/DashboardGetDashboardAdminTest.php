<?php

namespace Tests\Unit\Controller\DashboardController;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Category;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Faq;
use App\Models\ContactUsMessage;
use App\Models\Coupon;
use App\Http\Controllers\Api\DashboardController;

class DashboardGetDashboardAdminTest extends TestCase
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

    public function test_getDashboardAdmin_returns_counts_and_revenue()
    {
        // create an admin (should be excluded from total_users)
        $admin = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'admin_' . Str::random(6),
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        // create non-admin users (students/instructors)
        $u1 = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'user1_' . Str::random(6),
            'first_name' => 'User',
            'last_name' => 'One',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);
        $u2 = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'user2_' . Str::random(6),
            'first_name' => 'User',
            'last_name' => 'Two',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);
        $u3 = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'user3_' . Str::random(6),
            'first_name' => 'User',
            'last_name' => 'Three',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        // create categories, faqs, messages, coupons
        $cat1 = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Cat One',
        ]);
        $cat2 = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Cat Two',
        ]);

        Faq::create([
            'id' => Str::uuid()->toString(),
            'question' => 'Q1',
            'answer' => 'A1',
        ]);
        Faq::create([
            'id' => Str::uuid()->toString(),
            'question' => 'Q2',
            'answer' => 'A2',
        ]);

        ContactUsMessage::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Contact One',
            'email' => 'contact1@example.test',
            'message' => 'Hello',
        ]);

        Coupon::create([
            'id' => Str::uuid()->toString(),
            'title' => 'Coupon 1',
            'code' => 'COUPON1',
            'value' => 10000,
            'start_at' => now(),
            'end_at' => now()->addMonth(),
        ]);

        // create courses with various statuses
        $cNew = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $cat1->id,
            'instructor_id' => $u2->id,
            'title' => 'Course New',
            'price' => 10000,
            'is_discount_active' => false,
            'status' => 'new',
        ]);
        $cEdited = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $cat1->id,
            'instructor_id' => $u2->id,
            'title' => 'Course Edited',
            'price' => 20000,
            'is_discount_active' => false,
            'status' => 'edited',
        ]);
        $cAwait = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $cat2->id,
            'instructor_id' => $u2->id,
            'title' => 'Course Await',
            'price' => 30000,
            'is_discount_active' => false,
            'status' => 'awaiting_approval',
        ]);
        $cPub = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $cat2->id,
            'instructor_id' => $u2->id,
            'title' => 'Course Pub',
            'price' => 40000,
            'is_discount_active' => false,
            'status' => 'published',
        ]);

        // enrollments: two within last month (today), one older than 1 month
        $today = now();
        $old = now()->subMonths(2);

        // Temporarily disable FK checks so orphan checkout_session_id values won't fail.
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        // Insert directly to ensure created_at/updated_at are set exactly as intended (bypass model mass-assignment)
        DB::table('course_enrollments')->insert([
            'id' => Str::uuid()->toString(),
            'checkout_session_id' => Str::uuid()->toString(),
            'user_id' => $u1->id,
            'course_id' => $cNew->id,
            'price' => 50000,
            'payment_type' => 'midtrans',
            'access_status' => 'active',
            'created_at' => $today->toDateTimeString(),
            'updated_at' => $today->toDateTimeString(),
        ]);

        DB::table('course_enrollments')->insert([
            'id' => Str::uuid()->toString(),
            'checkout_session_id' => Str::uuid()->toString(),
            'user_id' => $u3->id,
            'course_id' => $cPub->id,
            'price' => 30000,
            'payment_type' => 'midtrans',
            'access_status' => 'active',
            'created_at' => $today->toDateTimeString(),
            'updated_at' => $today->toDateTimeString(),
        ]);

        // old enrollment should not be counted in revenue
        DB::table('course_enrollments')->insert([
            'id' => Str::uuid()->toString(),
            'checkout_session_id' => Str::uuid()->toString(),
            'user_id' => $u3->id,
            'course_id' => $cEdited->id,
            'price' => 100000,
            'payment_type' => 'midtrans',
            'access_status' => 'active',
            'created_at' => $old->toDateTimeString(),
            'updated_at' => $old->toDateTimeString(),
        ]);

        // Re-enable FK checks.
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $request = new Request();

        $controller = new DashboardController();
        $response = $controller->getDashboardAdmin($request);

        $data = $this->resolveResponseData($response, $request);

        // status and message
        $this->assertArrayHasKey('status', $data);
        if (is_bool($data['status'])) {
            $this->assertTrue($data['status']);
        } else {
            $this->assertEquals('success', $data['status']);
        }
        $this->assertArrayHasKey('message', $data);

        // data payload checks
        $this->assertArrayHasKey('data', $data);
        $payload = $data['data'];

        $this->assertEquals(4, $payload['total_courses']); // 4 courses created
        $this->assertEquals(3, $payload['total_users']); // admin excluded, 3 non-admin users
        $this->assertEquals(1, $payload['total_messages']);
        $this->assertEquals(2, $payload['total_faq']);
        $this->assertEquals(2, $payload['total_categories']);
        $this->assertEquals(1, $payload['total_coupon']);

        // total revenue should be sum of today's enrollments: 50000 + 30000 = 80000
        $this->assertEquals(80000, $payload['total_revenue']);

        // progress counts by status
        $this->assertArrayHasKey('progress', $payload);
        $this->assertEquals(1, $payload['progress']['new']);
        $this->assertEquals(1, $payload['progress']['edited']);
        $this->assertEquals(1, $payload['progress']['awaiting_approval']);
        $this->assertEquals(1, $payload['progress']['published']);

        // revenue_chart contains today's aggregated total
        $this->assertArrayHasKey('revenue_chart', $payload);
        $found = false;
        $todayStr = $today->format('Y-m-d');
        foreach ($payload['revenue_chart'] as $row) {
            if ($row['day'] === $todayStr) {
                $found = true;
                $this->assertEquals(80000, $row['total']);
            }
        }
        $this->assertTrue($found, 'Expected revenue_chart to contain an entry for today.');

        // filter keys exist
        $this->assertArrayHasKey('filter', $payload);
        $this->assertArrayHasKey('start_date', $payload['filter']);
        $this->assertArrayHasKey('end_date', $payload['filter']);
    }
}
