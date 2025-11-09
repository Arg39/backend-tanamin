<?php

namespace Tests\Unit\Controller\ModuleCourseController;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use App\Models\Course;
use App\Models\ModuleCourse;
use App\Models\User;
use App\Models\Category;
use App\Http\Controllers\Api\Course\Material\ModuleCourseController;

class ModuleCourseStoreTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Normalize controller/resource response to array.
     */
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

    public function test_store_creates_module_and_assigns_order()
    {
        // create required related records to satisfy FK constraints
        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'StoreTest Category',
            'image' => null,
        ]);

        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instr_' . Str::random(6),
            'first_name' => 'Instr',
            'last_name' => 'Store',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $course = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Course For Store Test',
            'price' => null,
            'is_discount_active' => false,
        ]);

        // existing module to influence order
        $existing = ModuleCourse::create([
            'id' => Str::uuid()->toString(),
            'course_id' => $course->id,
            'title' => 'Existing Module',
            'order' => 0,
        ]);

        $controller = new ModuleCourseController();
        $request = new Request();
        $newTitle = 'New Module Title';
        $request->merge(['title' => $newTitle]);

        $response = $controller->store($request, $course->id);

        $responseData = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $responseData);
        if (is_bool($responseData['status'])) {
            $this->assertTrue($responseData['status']);
        } else {
            $this->assertEquals('success', $responseData['status']);
        }

        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Module created successfully', $responseData['message']);

        $this->assertArrayHasKey('data', $responseData);
        $data = $responseData['data'];
        $this->assertIsArray($data);

        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('title', $data);
        $this->assertEquals($newTitle, $data['title']);

        // order should be equal to previous module count (1)
        $this->assertArrayHasKey('order', $data);
        $this->assertEquals(1, $data['order']);

        // verify persisted in DB
        $created = ModuleCourse::find($data['id']);
        $this->assertNotNull($created);
        $this->assertEquals($newTitle, $created->title);
        $this->assertEquals(1, $created->order);
        $this->assertEquals($course->id, $created->course_id);
    }

    public function test_store_handles_missing_title_returns_failure()
    {
        // create records to satisfy FK
        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'StoreFail Category',
            'image' => null,
        ]);

        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instr_' . Str::random(6),
            'first_name' => 'Instr',
            'last_name' => 'Fail',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $course = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Course For Store Fail Test',
            'price' => null,
            'is_discount_active' => false,
        ]);

        $controller = new ModuleCourseController();
        $request = new Request();
        // do not provide title to trigger validation failure
        $request->merge([]);

        $response = $controller->store($request, $course->id);

        $responseData = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $responseData);
        if (is_bool($responseData['status'])) {
            $this->assertFalse($responseData['status']);
        } else {
            $this->assertNotEquals('success', $responseData['status']);
        }

        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Failed to create module', $responseData['message']);
    }
}
