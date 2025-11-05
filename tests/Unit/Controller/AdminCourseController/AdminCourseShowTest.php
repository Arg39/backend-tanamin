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

class AdminCourseShowTest extends TestCase
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
     * Test that show returns the specific course and structure is correct.
     */
    public function test_show_returns_course()
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

        // Create a course
        $course = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Intro to Gardening - Show Test',
            'price' => 0,
            'level' => 'beginner',
            'image' => null,
            'status' => 'new',
            'detail' => null,
        ]);

        $controller = new AdminCourseController();
        $request = new Request();
        $response = $controller->show($course->id);

        $responseData = $this->resolveResponseData($response, $request);

        $this->assertEquals('success', $responseData['status']);
        $this->assertEquals('Course retrieved successfully', $responseData['message']);
        $this->assertArrayHasKey('data', $responseData);

        $returned = $responseData['data'];

        // If the resource wrapped the model inside another 'data' key, handle that
        if (is_array($returned) && isset($returned['data'])) {
            $returned = $returned['data'];
        }

        $this->assertIsArray($returned, 'Expected returned course data to be an array');
        $this->assertEquals($course->id, $returned['id']);
        $this->assertEquals($course->title, $returned['title']);
    }
}
