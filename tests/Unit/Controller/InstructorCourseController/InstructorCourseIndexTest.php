<?php

namespace Tests\Unit\Controller\InstructorCourseController;

use App\Http\Controllers\Api\Course\InstructorCourseController;
use App\Models\Category;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class InstructorCourseIndexTest extends TestCase
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
     * Test that instructor index returns courses for the authenticated instructor.
     */
    public function test_index_returns_courses_for_instructor()
    {
        // Create required related records
        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Gardening',
            'image' => null,
        ]);

        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'john_' . Str::random(6),
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        // Create courses for this instructor
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
            'level' => 'beginner',
            'image' => null,
            'status' => 'published',
            'detail' => null,
        ]);

        // Mock JWTAuth to return the created instructor as the authenticated user
        JWTAuth::shouldReceive('user')->once()->andReturn($instructor);

        $controller = new InstructorCourseController();
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
