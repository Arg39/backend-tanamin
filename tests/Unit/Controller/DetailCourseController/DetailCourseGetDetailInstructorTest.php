<?php

namespace Tests\Unit\Controller\AdminCourseController;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use App\Models\Category;
use App\Models\Course;
use App\Models\User;
use App\Http\Controllers\Api\DetailCourseController;

class DetailCourseGetDetailInstructorTest extends TestCase
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

    public function test_course_not_found_returns_expected_message()
    {
        $controller = new DetailCourseController();
        $request = new Request();

        $randomId = Str::uuid()->toString();
        $response = $controller->getDetailInstructor($randomId);

        $responseData = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $responseData);
        $this->assertEquals('failed', $responseData['status']);
        $this->assertEquals('Course not found.', $responseData['message']);
    }

    public function test_get_detail_instructor_returns_user_profile()
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
            'title' => 'Test Course Instructor',
            'price' => 0,
            'level' => 'beginner',
            'image' => null,
            'status' => 'published',
            'detail' => null,
        ]);

        $controller = new DetailCourseController();
        $request = new Request();
        $response = $controller->getDetailInstructor($course->id);

        $responseData = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $responseData);
        $this->assertEquals('success', $responseData['status']);
        $this->assertEquals('Profil pengguna berhasil diambil.', $responseData['message']);
        $this->assertArrayHasKey('data', $responseData);

        $data = $responseData['data'];
        $this->assertIsArray($data);

        // Basic checks that resource contains instructor identity fields
        $this->assertArrayHasKey('id', $data);
        $this->assertEquals($instructor->id, $data['id']);

        $this->assertArrayHasKey('username', $data);
        $this->assertEquals($instructor->username, $data['username']);

        $this->assertArrayHasKey('first_name', $data);
        $this->assertEquals($instructor->first_name, $data['first_name']);

        $this->assertArrayHasKey('last_name', $data);
        $this->assertEquals($instructor->last_name, $data['last_name']);
    }
}
