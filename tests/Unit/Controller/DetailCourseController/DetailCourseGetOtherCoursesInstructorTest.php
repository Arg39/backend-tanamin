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

class DetailCourseGetOtherCoursesInstructorTest extends TestCase
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
        $response = $controller->getOtherCoursesInstructor($randomId);

        $responseData = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $responseData);
        // Controller returns 'success' for "Course not found." in this method
        $this->assertEquals('success', $responseData['status']);
        $this->assertEquals('Course not found.', $responseData['message']);

        // Accept either missing 'data' or null
        $this->assertTrue(
            !array_key_exists('data', $responseData) || is_null($responseData['data']),
            'Expected "data" to be either absent or null when course does not exist.'
        );
    }

    public function test_get_other_courses_instructor_returns_other_courses_excluding_current()
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

        // Main course that we'll request other courses for
        $mainCourse = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Main Course',
            'price' => 50,
            'level' => 'beginner',
            'image' => null,
            'status' => 'published',
            'detail' => null,
        ]);

        // Create other published courses for same instructor
        $otherCourses = [];
        for ($i = 0; $i < 3; $i++) {
            $otherCourses[] = Course::create([
                'id' => Str::uuid()->toString(),
                'category_id' => $category->id,
                'instructor_id' => $instructor->id,
                'title' => 'Other Course ' . $i,
                'price' => 20 + $i,
                'level' => 'beginner',
                'image' => null,
                'status' => 'published',
                'detail' => null,
            ]);
        }

        // Create a course by another instructor which should not be returned
        $otherInstructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instructor_' . Str::random(6),
            'first_name' => 'Other',
            'last_name' => 'Instructor',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $otherInstructor->id,
            'title' => 'Unrelated Course',
            'price' => 10,
            'level' => 'beginner',
            'image' => null,
            'status' => 'published',
            'detail' => null,
        ]);

        $controller = new DetailCourseController();
        $request = new Request();
        $response = $controller->getOtherCoursesInstructor($mainCourse->id);

        $responseData = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $responseData);
        $this->assertEquals('success', $responseData['status']);
        $this->assertEquals('Other courses retrieved successfully.', $responseData['message']);
        $this->assertArrayHasKey('data', $responseData);

        $data = $responseData['data'];
        $this->assertIsArray($data);

        // Expect the number of returned items to match number of other published courses (3)
        $this->assertCount(count($otherCourses), $data);

        // Ensure none of returned items reference the main course id (if id present in resource)
        foreach ($data as $item) {
            if (is_array($item) && array_key_exists('id', $item)) {
                $this->assertNotEquals($mainCourse->id, $item['id']);
            }
        }
    }
}
