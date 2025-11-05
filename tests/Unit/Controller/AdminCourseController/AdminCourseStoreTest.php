<?php

namespace Tests\Unit\Controller\AdminCourseController;

use App\Http\Controllers\Api\Course\AdminCourseController;
use App\Http\Requests\StoreCourseRequest;
use App\Models\Category;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminCourseStoreTest extends TestCase
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
     * Test that store() creates a new course and returns expected response structure.
     */
    public function test_store_creates_course()
    {
        // Create related records
        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Gardening',
            'image' => null,
        ]);

        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instructor_' . Str::random(6),
            'first_name' => 'John',
            'last_name' => 'Smith',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        // Prepare payload for storing a course
        $payload = [
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Test Course ' . Str::random(6),
            'price' => 100,
            'level' => 'beginner',
            'status' => 'new',
            'detail' => '<p>Course detail</p>',
        ];

        // Create a StoreCourseRequest and merge payload (FormRequest extends Request)
        $storeRequest = new StoreCourseRequest();
        $storeRequest->setMethod('POST');
        $storeRequest->merge($payload);

        $controller = new AdminCourseController();
        $response = $controller->store($storeRequest);

        $responseData = $this->resolveResponseData($response, $storeRequest);

        $this->assertEquals('success', $responseData['status']);
        $this->assertEquals('Course created successfully', $responseData['message']);
        $this->assertNotEmpty($responseData['data']);

        // Verify the course exists in the database
        $this->assertDatabaseHas('courses', [
            'title' => $payload['title'],
            'category_id' => $payload['category_id'],
            'instructor_id' => $payload['instructor_id'],
        ]);
    }
}