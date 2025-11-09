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

class ModuleCourseUpdateTest extends TestCase
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

    public function test_update_updates_title_only()
    {
        // create related records required by DB constraints
        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Update Category',
            'image' => null,
        ]);

        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instr_' . Str::random(6),
            'first_name' => 'Inst',
            'last_name' => 'Update',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $course = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Course For Module Update',
            'price' => null,
            'is_discount_active' => false,
        ]);

        $module = ModuleCourse::create([
            'id' => Str::uuid()->toString(),
            'course_id' => $course->id,
            'title' => 'Original Module Title',
            'order' => 0,
        ]);

        $newTitle = 'Updated Module Title';

        $request = new Request();
        $request->merge([
            'title' => $newTitle,
            // no 'order' provided to test title-only update
        ]);

        $controller = new ModuleCourseController();
        $response = $controller->update($request, $course->id, $module->id);

        $responseData = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $responseData);
        if (is_bool($responseData['status'])) {
            $this->assertTrue($responseData['status']);
        } else {
            $this->assertEquals('success', $responseData['status']);
        }

        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Module updated successfully', $responseData['message']);

        $this->assertArrayHasKey('data', $responseData);
        $data = $responseData['data'];
        $this->assertIsArray($data);
        $this->assertArrayHasKey('id', $data);
        $this->assertEquals($module->id, $data['id']);
        $this->assertArrayHasKey('title', $data);
        $this->assertEquals($newTitle, $data['title']);

        // reload and assert persisted
        $updated = ModuleCourse::find($module->id);
        $this->assertNotNull($updated);
        $this->assertEquals($newTitle, $updated->title);
        $this->assertEquals(0, $updated->order);
    }

    public function test_update_moves_module_to_end_when_order_high()
    {
        // create related records required by DB constraints
        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Reorder Category',
            'image' => null,
        ]);

        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instr_' . Str::random(6),
            'first_name' => 'Inst',
            'last_name' => 'Reorder',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $course = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Course For Reorder Test',
            'price' => null,
            'is_discount_active' => false,
        ]);

        $moduleA = ModuleCourse::create([
            'id' => Str::uuid()->toString(),
            'course_id' => $course->id,
            'title' => 'Module A',
            'order' => 0,
        ]);

        $moduleB = ModuleCourse::create([
            'id' => Str::uuid()->toString(),
            'course_id' => $course->id,
            'title' => 'Module B',
            'order' => 1,
        ]);

        $newTitleA = 'Module A Moved';
        $request = new Request();
        $request->merge([
            'title' => $newTitleA,
            'order' => 5, // intentionally large to force it to become last after normalization
        ]);

        $controller = new ModuleCourseController();
        $response = $controller->update($request, $course->id, $moduleA->id);

        $responseData = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $responseData);
        if (is_bool($responseData['status'])) {
            $this->assertTrue($responseData['status']);
        } else {
            $this->assertEquals('success', $responseData['status']);
        }

        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Module updated successfully', $responseData['message']);

        $this->assertArrayHasKey('data', $responseData);
        $data = $responseData['data'];
        $this->assertIsArray($data);
        $this->assertEquals($moduleA->id, $data['id']);
        $this->assertEquals($newTitleA, $data['title']);

        // reload modules and assert ordering normalized: moduleB should be 0, moduleA should be 1 (last)
        $reloaded = ModuleCourse::where('course_id', $course->id)->orderBy('order', 'asc')->get();
        $this->assertCount(2, $reloaded);
        $this->assertEquals($moduleB->id, $reloaded[0]->id);
        $this->assertEquals(0, $reloaded[0]->order);
        $this->assertEquals($moduleA->id, $reloaded[1]->id);
        $this->assertEquals(1, $reloaded[1]->order);
    }
}
