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
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminCourseDestroyTest extends TestCase
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
     * Test that destroy deletes the course and its image from storage.
     */
    public function test_destroy_deletes_course_and_image()
    {
        // Fake the public disk so file operations are isolated
        Storage::fake('public');

        // Create category
        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Gardening',
            'image' => null,
        ]);

        // Create instructor user
        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'jane_' . Str::random(6),
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        // Prepare image path and put a fake file
        $imagePath = 'courses/test-image.jpg';
        Storage::disk('public')->put($imagePath, 'dummy content');

        // Create a course with the image
        $course = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Intro to Gardening',
            'price' => 0,
            'level' => 'beginner',
            'image' => $imagePath,
            'status' => 'new',
            'detail' => null,
        ]);

        // Ensure file exists before deletion
        $this->assertTrue(Storage::disk('public')->exists($imagePath), 'Expected image file to exist before destroy');

        $controller = new AdminCourseController();
        $request = new Request();
        $response = $controller->destroy($course->id);

        $responseData = $this->resolveResponseData($response, $request);

        // Assert response indicates success
        if (isset($responseData['status'])) {
            $this->assertEquals('success', $responseData['status']);
        }
        $this->assertEquals('Course deleted successfully', $responseData['message']);

        // Assert course is deleted from database
        $this->assertNull(Course::find($course->id), 'Expected course to be deleted from database');

        // Assert image file was deleted from storage
        $this->assertFalse(Storage::disk('public')->exists($imagePath), 'Expected image file to be deleted from storage');
    }
}
