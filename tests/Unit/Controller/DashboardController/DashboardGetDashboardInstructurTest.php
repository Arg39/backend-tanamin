<?php

namespace Tests\Unit\Controller\DashboardController;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\Category;
use App\Models\Course;
use App\Models\Coupon;
use App\Http\Controllers\Api\DashboardController;

class DashboardGetDashboardInstructurTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * A basic unit test example.
     */
    public function test_example(): void
    {
        $this->assertTrue(true);
    }

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

    public function test_getDashboardInstructur_returns_counts_and_progress()
    {
        // create an instructor (will be the authenticated user)
        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instr_' . Str::random(6),
            'first_name' => 'Instructor',
            'last_name' => 'One',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        // create other users
        $other = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'other_' . Str::random(6),
            'first_name' => 'Other',
            'last_name' => 'User',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        // create categories
        $cat1 = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Cat One',
        ]);
        $cat2 = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Cat Two',
        ]);

        // create a coupon
        Coupon::create([
            'id' => Str::uuid()->toString(),
            'title' => 'Coupon A',
            'code' => 'CUPA',
            'value' => 5000,
            'start_at' => now(),
            'end_at' => now()->addMonth(),
        ]);

        // Courses for instructor with different statuses
        $cNew = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $cat1->id,
            'instructor_id' => $instructor->id,
            'title' => 'Instructor Course New',
            'price' => 10000,
            'is_discount_active' => false,
            'status' => 'new',
        ]);
        $cEdited = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $cat1->id,
            'instructor_id' => $instructor->id,
            'title' => 'Instructor Course Edited',
            'price' => 15000,
            'is_discount_active' => false,
            'status' => 'edited',
        ]);
        $cAwait = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $cat2->id,
            'instructor_id' => $instructor->id,
            'title' => 'Instructor Course Await',
            'price' => 20000,
            'is_discount_active' => false,
            'status' => 'awaiting_approval',
        ]);
        $cPub = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $cat2->id,
            'instructor_id' => $instructor->id,
            'title' => 'Instructor Course Pub',
            'price' => 25000,
            'is_discount_active' => false,
            'status' => 'published',
        ]);

        // Courses for other instructor/user to increase total_courses count
        $otherCourse = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $cat1->id,
            'instructor_id' => $other->id,
            'title' => 'Other Course',
            'price' => 12000,
            'is_discount_active' => false,
            'status' => 'published',
        ]);

        $request = new Request();
        // set authenticated user for the request
        $request->setUserResolver(function () use ($instructor) {
            return $instructor;
        });

        $controller = new DashboardController();
        $response = $controller->getDashboardInstructur($request);

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

        // total courses should be 5 (4 instructor + 1 other)
        $this->assertEquals(5, $payload['total_courses']);
        // total_my_courses should be 4 (only instructor's courses)
        $this->assertEquals(4, $payload['total_my_courses']);
        // total categories and coupons
        $this->assertEquals(2, $payload['total_categories']);
        $this->assertEquals(1, $payload['total_coupon']);

        // progress counts by status for instructor
        $this->assertArrayHasKey('progress', $payload);
        $this->assertEquals(1, $payload['progress']['new']);
        $this->assertEquals(1, $payload['progress']['edited']);
        $this->assertEquals(1, $payload['progress']['awaiting_approval']);
        $this->assertEquals(1, $payload['progress']['published']);
    }
}
