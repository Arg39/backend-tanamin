<?php

namespace Tests\Unit\Controller\DetailCourseController;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use App\Models\Category;
use App\Models\Course;
use App\Models\User;
use App\Http\Controllers\Api\DetailCourseController;

class DetailCourseShowDetailTest extends TestCase
{
    use DatabaseTransactions;

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

    public function test_course_not_found()
    {
        $controller = new DetailCourseController();
        $request = new Request();

        $randomId = Str::uuid()->toString();
        $response = $controller->showDetail($randomId);

        $responseData = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $responseData);
        // Controller returns status strings ('failed' / 'success'), not boolean
        $this->assertEquals('failed', $responseData['status']);
        $this->assertEquals('Course not found.', $responseData['message']);
    }

    public function test_show_detail_published_course_returns_expected_structure()
    {
        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Gardening',
            'image' => null,
        ]);

        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instructor_' . Str::random(6),
            'first_name' => 'Instructor',
            'last_name' => 'Test',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $course = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Test Course',
            'price' => 100,
            'level' => 'beginner',
            'image' => null,
            'status' => 'published',
            'detail' => null,
        ]);

        $controller = new DetailCourseController();
        $request = new Request();
        $response = $controller->showDetail($course->id);

        $responseData = $this->resolveResponseData($response, $request);

        // Controller returns status strings ('failed' / 'success'), not boolean
        $this->assertEquals('success', $responseData['status']);
        $this->assertEquals('Course retrieved successfully.', $responseData['message']);
        $this->assertArrayHasKey('data', $responseData);

        $data = $responseData['data'];

        $this->assertArrayHasKey('students_count', $data);
        $this->assertEquals(0, $data['students_count']);

        $this->assertArrayHasKey('access', $data);
        $this->assertFalse($data['access']);

        $this->assertArrayHasKey('in_cart', $data);
        $this->assertFalse($data['in_cart']);

        $this->assertArrayHasKey('coupon', $data);
        $this->assertEquals(['coupon' => false], $data['coupon']);
    }
}
