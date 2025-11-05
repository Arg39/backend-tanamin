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
use App\Models\ModuleCourse;
use App\Models\LessonCourse;
use App\Models\LessonMaterial;
use App\Http\Controllers\Api\DetailCourseController;

class DetailCourseGetMaterialPublicTest extends TestCase
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

    public function test_no_modules_returns_expected_message()
    {
        $controller = new DetailCourseController();
        $request = new Request();

        $randomId = Str::uuid()->toString();
        $response = $controller->getMaterialPublic($randomId);

        $responseData = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $responseData);
        $this->assertEquals('success', $responseData['status']);
        $this->assertEquals('No modules found for this course.', $responseData['message']);

        // Accept either missing data or null
        $this->assertTrue(
            !array_key_exists('data', $responseData) || is_null($responseData['data']),
            'Expected "data" to be either absent or null when no modules exist.'
        );
    }

    public function test_get_material_public_returns_visible_materials()
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
            'title' => 'Test Course Materials',
            'price' => 0,
            'level' => 'beginner',
            'image' => null,
            'status' => 'published',
            'detail' => null,
        ]);

        $module = ModuleCourse::create([
            'id' => Str::uuid()->toString(),
            'course_id' => $course->id,
            'title' => 'Module 1',
        ]);

        $lesson = LessonCourse::create([
            'id' => Str::uuid()->toString(),
            'module_id' => $module->id,
            'title' => 'Lesson A',
        ]);

        $visibleMaterial = LessonMaterial::create([
            'id' => Str::uuid()->toString(),
            'lesson_id' => $lesson->id,
            'content' => 'Visible content here',
            'visible' => true,
        ]);

        $hiddenMaterial = LessonMaterial::create([
            'id' => Str::uuid()->toString(),
            'lesson_id' => $lesson->id,
            'content' => 'Hidden content here',
            'visible' => false,
        ]);

        $controller = new DetailCourseController();
        $request = new Request();
        $response = $controller->getMaterialPublic($course->id);

        $responseData = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $responseData);
        $this->assertEquals('success', $responseData['status']);
        $this->assertEquals('Visible materials retrieved successfully.', $responseData['message']);
        $this->assertArrayHasKey('data', $responseData);

        $data = $responseData['data'];
        $this->assertIsArray($data);

        // Only one visible material should be returned
        $this->assertCount(1, $data);

        $item = $data[0];
        $this->assertArrayHasKey('id', $item);
        $this->assertArrayHasKey('title', $item);
        $this->assertArrayHasKey('content', $item);

        $this->assertEquals($lesson->title, $item['title']);
        $this->assertEquals('Visible content here', $item['content']);
        $this->assertEquals($visibleMaterial->id, $item['id']);
    }
}
