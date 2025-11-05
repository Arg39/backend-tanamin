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

class DetailCourseGetCoursesFromInstructorIdTest extends TestCase
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

    public function test_no_courses_found_returns_expected_message()
    {
        $controller = new DetailCourseController();
        $request = new Request();

        $randomInstructorId = Str::uuid()->toString();
        $response = $controller->getCoursesFromInstructorId($randomInstructorId);

        $responseData = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $responseData);
        $this->assertEquals('success', $responseData['status']);
        $this->assertEquals('No courses found for this instructor.', $responseData['message']);

        // Accept either absent or null data when no courses exist
        $this->assertTrue(
            !array_key_exists('data', $responseData) || is_null($responseData['data']),
            'Expected "data" to be either absent or null when no courses exist.'
        );
    }

    public function test_get_courses_from_instructor_returns_cards()
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

        // Create 3 published courses for this instructor
        $coursesCreated = [];
        for ($i = 0; $i < 3; $i++) {
            $coursesCreated[] = Course::create([
                'id' => Str::uuid()->toString(),
                'category_id' => $category->id,
                'instructor_id' => $instructor->id,
                'title' => 'Test Course ' . ($i + 1),
                'price' => 100,
                'level' => 'beginner',
                'image' => null,
                'status' => 'published',
                'detail' => null,
            ]);
        }

        $controller = new DetailCourseController();
        $request = new Request();
        $response = $controller->getCoursesFromInstructorId($instructor->id);

        $responseData = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $responseData);
        $this->assertEquals('success', $responseData['status']);
        $this->assertEquals('Courses retrieved successfully.', $responseData['message']);
        $this->assertArrayHasKey('data', $responseData);

        $data = $responseData['data'];
        $this->assertIsArray($data);

        // Controller limits to 8, we created 3 so expect 3
        $this->assertCount(count($coursesCreated), $data);

        // Ensure each returned card has expected keys like 'id' and 'title'
        foreach ($data as $card) {
            $this->assertIsArray($card);
            $this->assertArrayHasKey('id', $card);
            $this->assertArrayHasKey('title', $card);
        }
    }
}
