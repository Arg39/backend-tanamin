<?php

namespace Tests\Unit\Controller\CouponController;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\Coupon;
use App\Models\Course;
use App\Models\User;
use App\Models\CouponUsage;
use App\Models\Category;
use App\Http\Controllers\Api\CouponController;
use Tymon\JWTAuth\Facades\JWTAuth;

class CouponUseCouponTest extends TestCase
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

    public function test_use_coupon_successfully_applies_and_records_usage()
    {
        // create user (student)
        $user = User::create([
            'id' => Str::uuid()->toString(),
            'first_name' => 'Test',
            'last_name' => 'User',
            'username' => 'testuser_' . Str::random(4),
            'email' => 'test_' . Str::random(6) . '@example.com',
            'password' => bcrypt('secret123'),
            'role' => 'student',
            'status' => 'active',
        ]);

        // create related category and instructor required by Course
        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Test Category ' . Str::random(4),
            'image' => null,
        ]);

        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'first_name' => 'Instr',
            'last_name' => 'User',
            'username' => 'instr_' . Str::random(4),
            'email' => 'instr_' . Str::random(6) . '@example.com',
            'password' => bcrypt('secret123'),
            'role' => 'instructor',
            'status' => 'active',
        ]);

        // create course (include required fields)
        $course = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Test Course ' . Str::random(4),
            'slug' => 'test-course-' . Str::random(6),
            'price' => 0,
            'level' => 'beginner',
            'status' => 'new',
            'detail' => '<p>Test course</p>',
        ]);

        // create coupon valid now
        $code = 'CPN_' . Str::random(8);
        $coupon = Coupon::create([
            'id' => Str::uuid()->toString(),
            'title' => 'Promo Test',
            'code' => $code,
            'type' => 'percent',
            'value' => 10,
            'start_at' => Carbon::now()->subDay(),
            'end_at' => Carbon::now()->addDay(),
            'is_active' => true,
            'max_usage' => null,
            'used_count' => 0,
        ]);

        // ensure no prior usage
        $this->assertDatabaseMissing('coupon_usages', [
            'user_id' => $user->id,
            'course_id' => $course->id,
            'coupon_id' => $coupon->id,
        ]);

        // mock authenticated user
        JWTAuth::shouldReceive('user')->once()->andReturn($user);

        $request = new Request(['coupon_code' => $coupon->code]);
        $controller = new CouponController();
        $response = $controller->useCoupon($request, $course->id);

        $data = $this->resolveResponseData($response, $request);

        // status may be boolean true or string 'success'
        $this->assertArrayHasKey('status', $data);
        if (is_bool($data['status'])) {
            $this->assertTrue($data['status']);
        } else {
            $this->assertEquals('success', $data['status']);
        }

        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('Coupon applied successfully', $data['message']);

        // check coupon usage recorded
        $this->assertDatabaseHas('coupon_usages', [
            'user_id' => $user->id,
            'course_id' => $course->id,
            'coupon_id' => $coupon->id,
        ]);
    }

    public function test_use_coupon_fails_when_already_used()
    {
        // create user (student)
        $user = User::create([
            'id' => Str::uuid()->toString(),
            'first_name' => 'Test2',
            'last_name' => 'User2',
            'username' => 'testuser2_' . Str::random(4),
            'email' => 'test2_' . Str::random(6) . '@example.com',
            'password' => bcrypt('secret123'),
            'role' => 'student',
            'status' => 'active',
        ]);

        // create related category and instructor required by Course
        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Test Category 2 ' . Str::random(4),
            'image' => null,
        ]);

        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'first_name' => 'Instr2',
            'last_name' => 'User2',
            'username' => 'instr2_' . Str::random(4),
            'email' => 'instr2_' . Str::random(6) . '@example.com',
            'password' => bcrypt('secret123'),
            'role' => 'instructor',
            'status' => 'active',
        ]);

        // create course (include required fields)
        $course = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Test Course 2 ' . Str::random(4),
            'slug' => 'test-course-2-' . Str::random(6),
            'price' => 0,
            'level' => 'beginner',
            'status' => 'new',
            'detail' => '<p>Test course 2</p>',
        ]);

        // create coupon valid now
        $code = 'CPN_' . Str::random(8);
        $coupon = Coupon::create([
            'id' => Str::uuid()->toString(),
            'title' => 'Promo Used',
            'code' => $code,
            'type' => 'nominal',
            'value' => 5000,
            'start_at' => Carbon::now()->subDay(),
            'end_at' => Carbon::now()->addDay(),
            'is_active' => true,
            'max_usage' => null,
            'used_count' => 0,
        ]);

        // create existing usage
        CouponUsage::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $user->id,
            'course_id' => $course->id,
            'coupon_id' => $coupon->id,
            'used_at' => now(),
        ]);

        // mock authenticated user
        JWTAuth::shouldReceive('user')->once()->andReturn($user);

        $request = new Request(['coupon_code' => $coupon->code]);
        $controller = new CouponController();
        $response = $controller->useCoupon($request, $course->id);

        $data = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('You have already used this coupon', $data['message']);
    }

    public function test_use_coupon_fails_when_not_in_valid_timeframe()
    {
        // create user (student)
        $user = User::create([
            'id' => Str::uuid()->toString(),
            'first_name' => 'Test3',
            'last_name' => 'User3',
            'username' => 'testuser3_' . Str::random(4),
            'email' => 'test3_' . Str::random(6) . '@example.com',
            'password' => bcrypt('secret123'),
            'role' => 'student',
            'status' => 'active',
        ]);

        // create related category and instructor required by Course
        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Test Category 3 ' . Str::random(4),
            'image' => null,
        ]);

        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'first_name' => 'Instr3',
            'last_name' => 'User3',
            'username' => 'instr3_' . Str::random(4),
            'email' => 'instr3_' . Str::random(6) . '@example.com',
            'password' => bcrypt('secret123'),
            'role' => 'instructor',
            'status' => 'active',
        ]);

        // create course (include required fields)
        $course = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Test Course 3 ' . Str::random(4),
            'slug' => 'test-course-3-' . Str::random(6),
            'price' => 0,
            'level' => 'beginner',
            'status' => 'new',
            'detail' => '<p>Test course 3</p>',
        ]);

        // create coupon not yet active
        $code = 'CPN_' . Str::random(8);
        $coupon = Coupon::create([
            'id' => Str::uuid()->toString(),
            'title' => 'Promo Future',
            'code' => $code,
            'type' => 'percent',
            'value' => 20,
            'start_at' => Carbon::now()->addDay(),
            'end_at' => Carbon::now()->addDays(2),
            'is_active' => true,
            'max_usage' => null,
            'used_count' => 0,
        ]);

        // mock authenticated user
        JWTAuth::shouldReceive('user')->once()->andReturn($user);

        $request = new Request(['coupon_code' => $coupon->code]);
        $controller = new CouponController();
        $response = $controller->useCoupon($request, $course->id);

        $data = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('Coupon is not valid at this time', $data['message']);
    }
}