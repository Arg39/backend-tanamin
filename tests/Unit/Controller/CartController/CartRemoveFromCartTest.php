<?php

namespace Tests\Unit\Controller\CartController;

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
use App\Http\Controllers\Api\Course\CartController;

class CartRemoveFromCartTest extends TestCase
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

    public function test_remove_from_cart_success()
    {
        $student = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'stud_remove_' . Str::random(6),
            'first_name' => 'Student',
            'last_name' => 'Remove',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'RemoveCat',
            'image' => null,
        ]);

        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instr_remove_' . Str::random(6),
            'first_name' => 'Instructor',
            'last_name' => 'Remove',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $course = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Course To Remove',
            'price' => 120,
            'active_discount' => false,
            'discount_value' => null,
            'discount_type' => null,
        ]);

        $session = CourseCheckoutSession::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $student->id,
            'checkout_type' => 'cart',
            'payment_status' => 'pending',
        ]);

        $enrollment = CourseEnrollment::create([
            'id' => Str::uuid()->toString(),
            'checkout_session_id' => $session->id,
            'user_id' => $student->id,
            'course_id' => $course->id,
            'price' => 120,
            'payment_type' => 'midtrans',
            'access_status' => 'inactive',
        ]);

        $request = new Request();
        $request->setUserResolver(function () use ($student) {
            return $student;
        });

        $controller = new CartController();
        $response = $controller->removeFromCart($course->id, $request);

        $data = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $data);
        if (is_bool($data['status'])) {
            $this->assertTrue($data['status']);
        } else {
            $this->assertEquals('success', $data['status']);
        }

        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('Course berhasil dihapus dari cart.', $data['message']);

        $this->assertFalse(
            CourseEnrollment::where('user_id', $student->id)
                ->where('course_id', $course->id)
                ->exists()
        );
    }

    public function test_remove_from_cart_no_session()
    {
        $student = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'stud_no_sess_' . Str::random(6),
            'first_name' => 'Student',
            'last_name' => 'NoSession',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'NoSessionCat',
        ]);

        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instr_no_sess_' . Str::random(6),
            'first_name' => 'Instructor',
            'last_name' => 'NoSession',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $course = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Course No Session',
            'price' => 30,
            'active_discount' => false,
            'discount_value' => null,
            'discount_type' => null,
        ]);

        $request = new Request();
        $request->setUserResolver(function () use ($student) {
            return $student;
        });

        $controller = new CartController();
        $response = $controller->removeFromCart($course->id, $request);

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

    public function test_remove_from_cart_course_not_in_cart()
    {
        $student = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'stud_not_in_cart_' . Str::random(6),
            'first_name' => 'Student',
            'last_name' => 'NotInCart',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'NotInCartCat',
        ]);

        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instr_not_in_cart_' . Str::random(6),
            'first_name' => 'Instructor',
            'last_name' => 'NotInCart',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $course = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Course Missing In Cart',
            'price' => 45,
            'active_discount' => false,
            'discount_value' => null,
            'discount_type' => null,
        ]);

        // create cart session but NO enrollment for this course
        $session = CourseCheckoutSession::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $student->id,
            'checkout_type' => 'cart',
            'payment_status' => 'pending',
        ]);

        $request = new Request();
        $request->setUserResolver(function () use ($student) {
            return $student;
        });

        $controller = new CartController();
        $response = $controller->removeFromCart($course->id, $request);

        $data = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $data);
        if (is_bool($data['status'])) {
            $this->assertFalse($data['status']);
        } else {
            $this->assertNotEquals('success', $data['status']);
        }

        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('Course tidak ada di cart.', $data['message']);
    }

    public function test_remove_from_cart_ignores_active_enrollment()
    {
        $student = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'stud_active_' . Str::random(6),
            'first_name' => 'Student',
            'last_name' => 'Active',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'ActiveCat',
        ]);

        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instr_active_' . Str::random(6),
            'first_name' => 'Instructor',
            'last_name' => 'Active',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $course = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Course Active Enrollment',
            'price' => 60,
            'active_discount' => false,
            'discount_value' => null,
            'discount_type' => null,
        ]);

        $session = CourseCheckoutSession::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $student->id,
            'checkout_type' => 'cart',
            'payment_status' => 'pending',
        ]);

        // create an active enrollment which should be ignored by removeFromCart
        $enrollment = CourseEnrollment::create([
            'id' => Str::uuid()->toString(),
            'checkout_session_id' => $session->id,
            'user_id' => $student->id,
            'course_id' => $course->id,
            'price' => 60,
            'payment_type' => 'midtrans',
            'access_status' => 'active',
        ]);

        $request = new Request();
        $request->setUserResolver(function () use ($student) {
            return $student;
        });

        $controller = new CartController();
        $response = $controller->removeFromCart($course->id, $request);

        $data = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $data);
        if (is_bool($data['status'])) {
            $this->assertFalse($data['status']);
        } else {
            $this->assertNotEquals('success', $data['status']);
        }

        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('Course tidak ada di cart.', $data['message']);

        // active enrollment should still exist
        $this->assertTrue(
            CourseEnrollment::where('id', $enrollment->id)
                ->where('access_status', 'active')
                ->exists()
        );
    }
}