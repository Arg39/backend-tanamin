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
use App\Models\CourseAttribute;
use App\Http\Controllers\Api\DetailCourseController;

class DetailCourseGetDetailAttributeTest extends TestCase
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

    public function test_no_attributes_returns_expected_message()
    {
        $controller = new DetailCourseController();
        $request = new Request();

        $randomId = Str::uuid()->toString();
        $response = $controller->getDetailAttribute($randomId);

        $responseData = $this->resolveResponseData($response, $request);

        // dd($responseData);

        $this->assertArrayHasKey('status', $responseData);
        $this->assertEquals('success', $responseData['status']);
        $this->assertEquals('No attributes found for this course.', $responseData['message']);

        // PostResource may omit 'data' when it's null; accept either missing or null
        $this->assertTrue(
            !array_key_exists('data', $responseData) || is_null($responseData['data']),
            'Expected "data" to be either absent or null when no attributes exist.'
        );
    }

    public function test_get_detail_attribute_groups_attributes_by_type()
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
            'title' => 'Test Course Attributes',
            'price' => 0,
            'level' => 'beginner',
            'image' => null,
            'status' => 'published',
            'detail' => null,
        ]);

        // Create attributes with types allowed by DB enum: 'benefit' and 'prerequisite'
        $attr1 = CourseAttribute::create([
            'id' => Str::uuid()->toString(),
            'course_id' => $course->id,
            'type' => 'benefit',
            'content' => 'Learn to plant seeds',
        ]);

        $attr2 = CourseAttribute::create([
            'id' => Str::uuid()->toString(),
            'course_id' => $course->id,
            'type' => 'benefit',
            'content' => 'Grow healthy vegetables',
        ]);

        // Use 'prerequisite' (singular) to match DB enum
        $attr3 = CourseAttribute::create([
            'id' => Str::uuid()->toString(),
            'course_id' => $course->id,
            'type' => 'prerequisite',
            'content' => 'Basic gardening tools',
        ]);

        $controller = new DetailCourseController();
        $request = new Request();
        $response = $controller->getDetailAttribute($course->id);

        $responseData = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $responseData);
        $this->assertEquals('success', $responseData['status']);
        $this->assertEquals('Course attributes retrieved successfully.', $responseData['message']);
        $this->assertArrayHasKey('data', $responseData);

        $data = $responseData['data'];
        $this->assertIsArray($data);
        $this->assertArrayHasKey('benefit', $data);
        $this->assertArrayHasKey('prerequisite', $data);

        // Check contents exist in respective groups
        $this->assertContains('Learn to plant seeds', $data['benefit']);
        $this->assertContains('Grow healthy vegetables', $data['benefit']);
        $this->assertContains('Basic gardening tools', $data['prerequisite']);

        // Ensure counts match what we inserted
        $this->assertCount(2, $data['benefit']);
        $this->assertCount(1, $data['prerequisite']);
    }
}
