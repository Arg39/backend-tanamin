<?php

namespace Tests\Unit\Controller\AdminCourseController;

use App\Http\Controllers\Api\Course\AdminCourseController;
use App\Models\Category;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminCourseIndexTest extends TestCase
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

    /**
     * Test that admin index returns courses and response structure is correct.
     */
    public function test_index_returns_courses()
    {
        // Create required related records
        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Gardening',
            'image' => null,
        ]);

        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'jane_' . Str::random(6),
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        // Create courses
        $course1 = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Intro to Gardening',
            'price' => 0,
            'level' => 'beginner',
            'image' => null,
            'status' => 'new',
            'detail' => null,
        ]);

        $course2 = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Advanced Gardening',
            'price' => 50,
            // changed to safe default to avoid DB truncation warnings
            'level' => 'beginner',
            'image' => null,
            'status' => 'published',
            'detail' => null,
        ]);

        $controller = new AdminCourseController();
        $request = new Request();
        $response = $controller->index($request);

        $responseData = $this->resolveResponseData($response, $request);

        $this->assertEquals('success', $responseData['status']);
        $this->assertEquals('Courses retrieved successfully', $responseData['message']);
        $this->assertArrayHasKey('data', $responseData);

        $items = [];
        if (isset($responseData['data']['data'])) {
            $items = $responseData['data']['data'];
        } elseif (is_array($responseData['data'])) {
            $items = $responseData['data'];
        }

        $this->assertNotEmpty($items, 'Expected courses list not to be empty');
        $this->assertGreaterThanOrEqual(2, count($items));
    }
}
