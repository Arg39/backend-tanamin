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

class ModuleCourseDestroyTest extends TestCase
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

    public function test_destroy_deletes_module_and_reorders()
    {
        // create related records required by DB constraints
        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Destroy Cat',
            'image' => null,
        ]);

        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instr_' . Str::random(6),
            'first_name' => 'Instr',
            'last_name' => 'Destroy',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $course = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Course For Destroy Test',
            'price' => null,
            'is_discount_active' => false,
        ]);

        // create three modules with distinct orders
        $module1 = ModuleCourse::create([
            'id' => Str::uuid()->toString(),
            'course_id' => $course->id,
            'title' => 'Module A',
            'order' => 0,
        ]);

        $module2 = ModuleCourse::create([
            'id' => Str::uuid()->toString(),
            'course_id' => $course->id,
            'title' => 'Module B',
            'order' => 1,
        ]);

        $module3 = ModuleCourse::create([
            'id' => Str::uuid()->toString(),
            'course_id' => $course->id,
            'title' => 'Module C',
            'order' => 2,
        ]);

        $controller = new ModuleCourseController();
        $request = new Request();

        // delete the middle module
        $response = $controller->destroy($course->id, $module2->id);
        $responseData = $this->resolveResponseData($response, $request);

        // assert success response
        $this->assertArrayHasKey('status', $responseData);
        if (is_bool($responseData['status'])) {
            $this->assertTrue($responseData['status']);
        } else {
            $this->assertEquals('success', $responseData['status']);
        }
        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Module deleted successfully', $responseData['message']);

        // module2 should be removed
        $this->assertNull(ModuleCourse::find($module2->id));

        // remaining modules should be re-ordered sequentially starting at 0
        $remaining = ModuleCourse::where('course_id', $course->id)
            ->orderBy('order', 'asc')
            ->get();

        $this->assertCount(2, $remaining);
        $this->assertEquals(0, $remaining[0]->order);
        $this->assertEquals(1, $remaining[1]->order);

        // ensure the remaining ids match module1 and module3
        $remainingIds = $remaining->pluck('id')->toArray();
        $this->assertContains($module1->id, $remainingIds);
        $this->assertContains($module3->id, $remainingIds);
    }

    public function test_destroy_returns_failure_when_module_not_found()
    {
        // create minimal course
        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Destroy Cat 2',
            'image' => null,
        ]);

        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instr_' . Str::random(6),
            'first_name' => 'Instr',
            'last_name' => 'Destroy2',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $course = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Course For Destroy NotFound Test',
            'price' => null,
            'is_discount_active' => false,
        ]);

        $controller = new ModuleCourseController();
        $request = new Request();

        $fakeModuleId = Str::uuid()->toString();
        $response = $controller->destroy($course->id, $fakeModuleId);
        $responseData = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $responseData);
        if (is_bool($responseData['status'])) {
            $this->assertFalse($responseData['status']);
        } else {
            $this->assertNotEquals('success', $responseData['status']);
        }

        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Failed to delete module', $responseData['message']);
    }
}
