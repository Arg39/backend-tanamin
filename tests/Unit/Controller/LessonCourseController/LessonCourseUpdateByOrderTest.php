<?php

namespace Tests\Unit\Controller\LessonCourseController;

use App\Http\Controllers\Api\Course\Material\LessonCourseController;
use App\Models\Category;
use App\Models\Course;
use App\Models\LessonCourse;
use App\Models\ModuleCourse;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Tests\TestCase;

class LessonCourseUpdateByOrderTest extends TestCase
{
    use DatabaseTransactions;

    public function test_moves_lesson_within_same_module_updates_orders()
    {
        $instructor = User::create([
            'id' => Str::uuid(),
            'first_name' => 'Alice',
            'last_name' => 'Smith',
            'username' => 'alicesmith',
            'email' => 'alice@example.com',
            'password' => bcrypt('password'),
            'role' => 'instructor',
            'status' => 'active',
        ]);

        $category = Category::create([
            'id' => Str::uuid(),
            'name' => 'Test Category',
        ]);

        $course = Course::create([
            'id' => Str::uuid(),
            'instructor_id' => $instructor->id,
            'category_id' => $category->id,
            'title' => 'Order Course',
            'description' => 'Desc',
        ]);

        $module = ModuleCourse::create([
            'id' => Str::uuid(),
            'course_id' => $course->id,
            'title' => 'Module',
        ]);

        $lesson0 = LessonCourse::create([
            'id' => (string) Str::uuid(),
            'module_id' => $module->id,
            'title' => 'Lesson 0',
            'type' => 'material',
            'order' => 0,
        ]);
        $lesson1 = LessonCourse::create([
            'id' => (string) Str::uuid(),
            'module_id' => $module->id,
            'title' => 'Lesson 1',
            'type' => 'material',
            'order' => 1,
        ]);
        $lesson2 = LessonCourse::create([
            'id' => (string) Str::uuid(),
            'module_id' => $module->id,
            'title' => 'Lesson 2',
            'type' => 'material',
            'order' => 2,
        ]);

        $request = new Request([
            'id' => (string) $lesson0->id,
            'moveToModule' => (string) $module->id,
            'order' => 2,
        ]);

        $controller = new LessonCourseController();
        $response = $controller->updateByOrder($request);
        $data = $response->response()->getData(true);

        $this->assertEquals('success', $data['status']);
        $this->assertEquals('Lesson order updated successfully', $data['message']);

        $l0 = LessonCourse::find($lesson0->id);
        $l1 = LessonCourse::find($lesson1->id);
        $l2 = LessonCourse::find($lesson2->id);

        $this->assertEquals(2, $l0->order, 'moved lesson should be at order 2');
        $this->assertEquals(0, $l1->order, 'lesson1 should become order 0');
        $this->assertEquals(1, $l2->order, 'lesson2 should become order 1');
    }

    public function test_moves_lesson_between_modules_updates_orders_in_both_modules()
    {
        $instructor = User::create([
            'id' => Str::uuid(),
            'first_name' => 'Bob',
            'last_name' => 'Brown',
            'username' => 'bobbrown',
            'email' => 'bob@example.com',
            'password' => bcrypt('password'),
            'role' => 'instructor',
            'status' => 'active',
        ]);

        $category = Category::create([
            'id' => Str::uuid(),
            'name' => 'Another Category',
        ]);

        $course = Course::create([
            'id' => Str::uuid(),
            'instructor_id' => $instructor->id,
            'category_id' => $category->id,
            'title' => 'Move Course',
            'description' => 'Desc',
        ]);

        $moduleA = ModuleCourse::create([
            'id' => Str::uuid(),
            'course_id' => $course->id,
            'title' => 'Module A',
        ]);

        $moduleB = ModuleCourse::create([
            'id' => Str::uuid(),
            'course_id' => $course->id,
            'title' => 'Module B',
        ]);

        $a1 = LessonCourse::create([
            'id' => (string) Str::uuid(),
            'module_id' => $moduleA->id,
            'title' => 'A1',
            'type' => 'material',
            'order' => 0,
        ]);
        $a2 = LessonCourse::create([
            'id' => (string) Str::uuid(),
            'module_id' => $moduleA->id,
            'title' => 'A2',
            'type' => 'material',
            'order' => 1,
        ]);

        // module B lessons
        $b1 = LessonCourse::create([
            'id' => (string) Str::uuid(),
            'module_id' => $moduleB->id,
            'title' => 'B1',
            'type' => 'material',
            'order' => 0,
        ]);
        $b2 = LessonCourse::create([
            'id' => (string) Str::uuid(),
            'module_id' => $moduleB->id,
            'title' => 'B2',
            'type' => 'material',
            'order' => 1,
        ]);

        $request = new Request([
            'id' => (string) $a1->id,
            'moveToModule' => (string) $moduleB->id,
            'order' => 1,
        ]);

        $controller = new LessonCourseController();
        $response = $controller->updateByOrder($request);
        $data = $response->response()->getData(true);

        $this->assertEquals('success', $data['status']);
        $this->assertEquals('Lesson order updated successfully', $data['message']);

        $a1r = LessonCourse::find($a1->id);
        $a2r = LessonCourse::find($a2->id);
        $b1r = LessonCourse::find($b1->id);
        $b2r = LessonCourse::find($b2->id);

        $this->assertEquals($moduleB->id, $a1r->module_id, 'a1 should have new module id');
        $this->assertEquals(1, $a1r->order, 'moved a1 should be order 1 in moduleB');

        $this->assertEquals(0, $b1r->order, 'b1 should remain order 0');
        $this->assertEquals(2, $b2r->order, 'b2 should shift to order 2');

        $this->assertEquals($moduleA->id, $a2r->module_id);
        $this->assertEquals(0, $a2r->order, 'a2 should be reordered to 0 in moduleA');
    }
}
