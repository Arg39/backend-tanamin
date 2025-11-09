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

class CartGetCartCoursesTest extends TestCase
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

    public function test_get_cart_courses_returns_empty_when_no_session()
    {
        $user = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'user_no_session_' . Str::random(6),
            'first_name' => 'No',
            'last_name' => 'Session',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        $request = new Request();
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        $controller = new CartController();
        $response = $controller->getCartCourses($request);

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
        $this->assertIsArray($data['data']);
        $this->assertCount(0, $data['data']);
    }

    public function test_get_cart_courses_returns_empty_when_no_enrollments()
    {
        $user = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'user_empty_enr_' . Str::random(6),
            'first_name' => 'Empty',
            'last_name' => 'Enroll',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'CartEmptyCat',
        ]);

        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instr_' . Str::random(6),
            'first_name' => 'Instr',
            'last_name' => 'Cart',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $course = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Course Not Enrolled',
            'price' => null,
            'is_discount_active' => false,
        ]);

        // create cart session but no enrollments
        CourseCheckoutSession::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $user->id,
            'checkout_type' => 'cart',
            'payment_status' => 'pending',
        ]);

        $request = new Request();
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        $controller = new CartController();
        $response = $controller->getCartCourses($request);

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
        $this->assertIsArray($data['data']);
        $this->assertCount(0, $data['data']);
    }

    public function test_get_cart_courses_returns_courses_in_cart()
    {
        $user = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'user_cart_' . Str::random(6),
            'first_name' => 'Cart',
            'last_name' => 'User',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'CartCat',
        ]);

        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instr_cart_' . Str::random(6),
            'first_name' => 'Instr',
            'last_name' => 'Cart',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $courseA = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Course A',
            'price' => 100,
            'is_discount_active' => false,
        ]);

        $courseB = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Course B',
            'price' => 200,
            'is_discount_active' => false,
        ]);

        $session = CourseCheckoutSession::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $user->id,
            'checkout_type' => 'cart',
            'payment_status' => 'pending',
        ]);

        CourseEnrollment::create([
            'id' => Str::uuid()->toString(),
            'checkout_session_id' => $session->id,
            'user_id' => $user->id,
            'course_id' => $courseA->id,
            'price' => 100,
            'payment_type' => 'midtrans',
            'access_status' => 'inactive',
        ]);

        CourseEnrollment::create([
            'id' => Str::uuid()->toString(),
            'checkout_session_id' => $session->id,
            'user_id' => $user->id,
            'course_id' => $courseB->id,
            'price' => 200,
            'payment_type' => 'midtrans',
            'access_status' => 'inactive',
        ]);

        $request = new Request();
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        $controller = new CartController();
        $response = $controller->getCartCourses($request);

        $data = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $data);
        if (is_bool($data['status'])) {
            $this->assertTrue($data['status']);
        } else {
            $this->assertEquals('success', $data['status']);
        }

        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('Daftar kursus di cart berhasil diambil.', $data['message']);

        $this->assertArrayHasKey('data', $data);
        $returned = $data['data'];
        if (is_array($returned) && isset($returned['data'])) {
            $returned = $returned['data'];
        }

        $this->assertIsArray($returned);
        // If resource collection wrapped, handle accordingly
        if (isset($returned['items'])) {
            $courses = $returned['items'];
        } elseif (isset($returned[0]) || array_keys($returned) === range(0, count($returned) - 1)) {
            $courses = $returned;
        } elseif (isset($returned['data'])) {
            $courses = $returned['data'];
        } else {
            $this->fail('Unexpected data structure for cart courses: ' . json_encode($returned));
        }

        $this->assertCount(2, $courses);

        $ids = array_column($courses, 'id');
        $this->assertContains($courseA->id, $ids);
        $this->assertContains($courseB->id, $ids);

        // ensure each returned course corresponds to an inactive enrollment in the session
        foreach ($courses as $c) {
            $this->assertArrayHasKey('id', $c);
            $this->assertTrue(
                CourseEnrollment::where('checkout_session_id', $session->id)
                    ->where('user_id', $user->id)
                    ->where('course_id', $c['id'])
                    ->where('access_status', 'inactive')
                    ->exists(),
                'Expected an inactive enrollment for course id ' . ($c['id'] ?? 'null')
            );
        }
    }

    public function test_get_cart_courses_ignores_active_enrollments()
    {
        $user = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'user_cart_active_' . Str::random(6),
            'first_name' => 'CartActive',
            'last_name' => 'User',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'CartActiveCat',
        ]);

        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instr_cart_act_' . Str::random(6),
            'first_name' => 'Instr',
            'last_name' => 'Act',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $courseActive = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Course Active',
            'price' => 50,
            'is_discount_active' => false,
        ]);

        $courseInactive = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Course Inactive',
            'price' => 75,
            'is_discount_active' => false,
        ]);

        $session = CourseCheckoutSession::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $user->id,
            'checkout_type' => 'cart',
            'payment_status' => 'pending',
        ]);

        // active enrollment should be ignored
        CourseEnrollment::create([
            'id' => Str::uuid()->toString(),
            'checkout_session_id' => $session->id,
            'user_id' => $user->id,
            'course_id' => $courseActive->id,
            'price' => 50,
            'payment_type' => 'midtrans',
            'access_status' => 'active',
        ]);

        // inactive enrollment should be returned
        CourseEnrollment::create([
            'id' => Str::uuid()->toString(),
            'checkout_session_id' => $session->id,
            'user_id' => $user->id,
            'course_id' => $courseInactive->id,
            'price' => 75,
            'payment_type' => 'midtrans',
            'access_status' => 'inactive',
        ]);

        $request = new Request();
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        $controller = new CartController();
        $response = $controller->getCartCourses($request);

        $data = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $data);
        if (is_bool($data['status'])) {
            $this->assertTrue($data['status']);
        } else {
            $this->assertEquals('success', $data['status']);
        }

        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('Daftar kursus di cart berhasil diambil.', $data['message']);

        $this->assertArrayHasKey('data', $data);
        $returned = $data['data'];
        if (is_array($returned) && isset($returned['data'])) {
            $returned = $returned['data'];
        }

        if (isset($returned['items'])) {
            $courses = $returned['items'];
        } elseif (isset($returned[0]) || array_keys($returned) === range(0, count($returned) - 1)) {
            $courses = $returned;
        } elseif (isset($returned['data'])) {
            $courses = $returned['data'];
        } else {
            $this->fail('Unexpected data structure for cart courses: ' . json_encode($returned));
        }

        $this->assertIsArray($courses);
        $this->assertCount(1, $courses);
        $this->assertEquals($courseInactive->id, $courses[0]['id']);

        // Instead of asserting an 'in_cart' key, ensure the returned course corresponds to an inactive enrollment
        $this->assertTrue(
            CourseEnrollment::where('checkout_session_id', $session->id)
                ->where('user_id', $user->id)
                ->where('course_id', $courses[0]['id'])
                ->where('access_status', 'inactive')
                ->exists(),
            'Expected the returned course to have an inactive enrollment in the session'
        );
    }
}