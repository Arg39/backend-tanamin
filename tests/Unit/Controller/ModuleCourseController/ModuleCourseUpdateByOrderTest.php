<?php

namespace Tests\Unit\Controller\ModuleCourseController;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use App\Models\Course;
use App\Models\ModuleCourse;
use App\Models\Category;
use App\Models\User;
use App\Http\Controllers\Api\Course\Material\ModuleCourseController;

class ModuleCourseUpdateByOrderTest extends TestCase
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

    public function test_move_first_module_forward_updates_orders()
    {
        // setup required FK records
        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'MoveForward Cat',
            'image' => null,
        ]);

        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instr_' . Str::random(6),
            'first_name' => 'Instr',
            'last_name' => 'MoveF',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $course = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Course Move Forward',
            'price' => null,
            'is_discount_active' => false,
        ]);

        // create 3 modules with orders 0,1,2
        $m0 = ModuleCourse::create(['id' => Str::uuid()->toString(), 'course_id' => $course->id, 'title' => 'M0', 'order' => 0]);
        $m1 = ModuleCourse::create(['id' => Str::uuid()->toString(), 'course_id' => $course->id, 'title' => 'M1', 'order' => 1]);
        $m2 = ModuleCourse::create(['id' => Str::uuid()->toString(), 'course_id' => $course->id, 'title' => 'M2', 'order' => 2]);

        $controller = new ModuleCourseController();
        $request = new Request(['id' => $m0->id, 'order' => 2]); // move first to index 2 (end)
        $response = $controller->updateByOrder($request);

        $responseData = $this->resolveResponseData($response, $request);

        // assert response success
        $this->assertArrayHasKey('status', $responseData);
        if (is_bool($responseData['status'])) {
            $this->assertTrue($responseData['status']);
        } else {
            $this->assertEquals('success', $responseData['status']);
        }
        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Module order updated successfully', $responseData['message']);

        // reload modules and verify new orders
        $r0 = ModuleCourse::find($m0->id);
        $r1 = ModuleCourse::find($m1->id);
        $r2 = ModuleCourse::find($m2->id);

        $this->assertEquals(2, $r0->order, 'Moved module should have order 2');
        $this->assertEquals(0, $r1->order, 'Previously order 1 should become 0');
        $this->assertEquals(1, $r2->order, 'Previously order 2 should become 1');
    }

    public function test_move_last_module_backward_updates_orders()
    {
        // setup required FK records
        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'MoveBack Cat',
            'image' => null,
        ]);

        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instr_' . Str::random(6),
            'first_name' => 'Instr',
            'last_name' => 'MoveB',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $course = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Course Move Backward',
            'price' => null,
            'is_discount_active' => false,
        ]);

        // create 3 modules with orders 0,1,2
        $m0 = ModuleCourse::create(['id' => Str::uuid()->toString(), 'course_id' => $course->id, 'title' => 'MB0', 'order' => 0]);
        $m1 = ModuleCourse::create(['id' => Str::uuid()->toString(), 'course_id' => $course->id, 'title' => 'MB1', 'order' => 1]);
        $m2 = ModuleCourse::create(['id' => Str::uuid()->toString(), 'course_id' => $course->id, 'title' => 'MB2', 'order' => 2]);

        $controller = new ModuleCourseController();
        $request = new Request(['id' => $m2->id, 'order' => 0]); // move last to index 0 (front)
        $response = $controller->updateByOrder($request);

        $responseData = $this->resolveResponseData($response, $request);

        // assert response success
        $this->assertArrayHasKey('status', $responseData);
        if (is_bool($responseData['status'])) {
            $this->assertTrue($responseData['status']);
        } else {
            $this->assertEquals('success', $responseData['status']);
        }
        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Module order updated successfully', $responseData['message']);

        // reload modules and verify new orders
        $r0 = ModuleCourse::find($m0->id);
        $r1 = ModuleCourse::find($m1->id);
        $r2 = ModuleCourse::find($m2->id);

        $this->assertEquals(1, $r0->order, 'Previously order 0 should become 1');
        $this->assertEquals(2, $r1->order, 'Previously order 1 should become 2');
        $this->assertEquals(0, $r2->order, 'Moved module should have order 0');
    }

    public function test_move_with_order_too_high_caps_to_end()
    {
        // setup required FK records
        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Caps Cat',
            'image' => null,
        ]);

        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instr_' . Str::random(6),
            'first_name' => 'Instr',
            'last_name' => 'Caps',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $course = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Course Caps',
            'price' => null,
            'is_discount_active' => false,
        ]);

        // create 3 modules with orders 0,1,2
        $m0 = ModuleCourse::create(['id' => Str::uuid()->toString(), 'course_id' => $course->id, 'title' => 'C0', 'order' => 0]);
        $m1 = ModuleCourse::create(['id' => Str::uuid()->toString(), 'course_id' => $course->id, 'title' => 'C1', 'order' => 1]);
        $m2 = ModuleCourse::create(['id' => Str::uuid()->toString(), 'course_id' => $course->id, 'title' => 'C2', 'order' => 2]);

        $controller = new ModuleCourseController();
        // request order far beyond bounds; expect it to be capped to count($modules) == 2 (append)
        $request = new Request(['id' => $m1->id, 'order' => 999]);
        $response = $controller->updateByOrder($request);

        $responseData = $this->resolveResponseData($response, $request);

        // assert response success
        $this->assertArrayHasKey('status', $responseData);
        if (is_bool($responseData['status'])) {
            $this->assertTrue($responseData['status']);
        } else {
            $this->assertEquals('success', $responseData['status']);
        }
        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Module order updated successfully', $responseData['message']);

        // reload modules and verify new orders: moving middle module to end -> others shift left
        $r0 = ModuleCourse::find($m0->id);
        $r1 = ModuleCourse::find($m1->id);
        $r2 = ModuleCourse::find($m2->id);

        $this->assertEquals(0, $r0->order, 'First module should remain at 0');
        $this->assertEquals(2, $r1->order, 'Moved module should be placed at end (2)');
        $this->assertEquals(1, $r2->order, 'Last module should shift to 1');
    }
}
