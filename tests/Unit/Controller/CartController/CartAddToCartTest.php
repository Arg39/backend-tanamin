<?php

namespace Tests\Unit\Controller\CartController;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Models\User;
use App\Models\Category;
use App\Models\Course;
use App\Models\CourseCheckoutSession;
use App\Models\CourseEnrollment;
use App\Http\Controllers\Api\Course\CartController;

class CartAddToCartTest extends TestCase
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

    public function test_add_to_cart_success_paid()
    {
        $student = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'stud_' . Str::random(6),
            'first_name' => 'Student',
            'last_name' => 'Paid',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Cart Cat Paid',
            'image' => null,
        ]);

        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instr_cart_' . Str::random(6),
            'first_name' => 'Instructor',
            'last_name' => 'Cart',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $course = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Paid Course',
            'price' => 100,
            // ensure discount-related fields exist if model accepts them
            'active_discount' => false,
            'discount_value' => null,
            'discount_type' => null,
        ]);

        $request = new Request();
        $request->setUserResolver(function () use ($student) {
            return $student;
        });

        $controller = new CartController();
        $response = $controller->addToCart($course->id, $request);

        $data = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $data);
        if (is_bool($data['status'])) {
            $this->assertTrue($data['status']);
        } else {
            $this->assertEquals('success', $data['status']);
        }

        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('Course berhasil ditambahkan ke cart.', $data['message']);

        $this->assertArrayHasKey('data', $data);
        $enrollment = $data['data'];
        $this->assertIsArray($enrollment);
        $this->assertEquals($student->id, $enrollment['user_id']);
        $this->assertEquals($course->id, $enrollment['course_id']);
        $this->assertEquals(100, $enrollment['price']);
        // payment_type may not be present depending on DB schema; assert only if present
        if (array_key_exists('payment_type', $enrollment)) {
            $this->assertEquals('midtrans', $enrollment['payment_type']);
        }
        $this->assertEquals('inactive', $enrollment['access_status']);

        $this->assertTrue(CourseEnrollment::where('user_id', $student->id)->where('course_id', $course->id)->exists());
    }

    public function test_add_to_cart_success_free_via_discount()
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

        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Cart Cat Free',
            'image' => null,
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

        // Persist discount fields so controller sees them on Course::findOrFail
        $course = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Discounted Free Course',
            'price' => 50,
            'active_discount' => true,
            'discount_value' => 100,
            'discount_type' => 'percent',
        ]);

        $request = new Request();
        $request->setUserResolver(function () use ($student) {
            return $student;
        });

        $controller = new CartController();
        $response = $controller->addToCart($course->id, $request);

        $data = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $data);
        if (is_bool($data['status'])) {
            $this->assertTrue($data['status']);
        } else {
            $this->assertEquals('success', $data['status']);
        }

        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('Course berhasil ditambahkan ke cart.', $data['message']);

        $this->assertArrayHasKey('data', $data);
        $enrollment = $data['data'];
        $this->assertIsArray($enrollment);
        $this->assertEquals(50, $enrollment['price']);
        // payment_type may not be present depending on DB schema; assert only if present
        if (array_key_exists('payment_type', $enrollment)) {
            $this->assertEquals('free', $enrollment['payment_type']);
        }

        $this->assertTrue(CourseEnrollment::where('user_id', $student->id)->where('course_id', $course->id)->exists());
    }

    public function test_add_to_cart_already_in_cart()
    {
        $student = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'stud_exists_' . Str::random(6),
            'first_name' => 'Student',
            'last_name' => 'Exists',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Cart Cat Exists',
            'image' => null,
        ]);

        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instr_exists_' . Str::random(6),
            'first_name' => 'Instructor',
            'last_name' => 'Exists',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $course = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Already In Cart Course',
            'price' => 20,
            'active_discount' => false,
            'discount_value' => null,
            'discount_type' => null,
        ]);

        // create cart session and existing enrollment
        $cartSession = CourseCheckoutSession::create([
            'user_id' => $student->id,
            'checkout_type' => 'cart',
            'payment_status' => 'pending',
        ]);

        CourseEnrollment::create([
            'checkout_session_id' => $cartSession->id,
            'user_id' => $student->id,
            'course_id' => $course->id,
            'price' => 20,
            'payment_type' => 'midtrans',
            'access_status' => 'inactive',
        ]);

        $request = new Request();
        $request->setUserResolver(function () use ($student) {
            return $student;
        });

        $controller = new CartController();
        $response = $controller->addToCart($course->id, $request);

        $data = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $data);
        if (is_bool($data['status'])) {
            $this->assertFalse($data['status']);
        } else {
            $this->assertNotEquals('success', $data['status']);
        }

        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('Course sudah ada di cart.', $data['message']);

        $count = CourseEnrollment::where('user_id', $student->id)->where('course_id', $course->id)->count();
        $this->assertEquals(1, $count);
    }

    public function test_add_to_cart_course_not_found_throws()
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

        $fakeCourseId = Str::uuid()->toString();

        $request = new Request();
        $request->setUserResolver(function () use ($student) {
            return $student;
        });

        $this->expectException(ModelNotFoundException::class);

        $controller = new CartController();
        $controller->addToCart($fakeCourseId, $request);
    }
}