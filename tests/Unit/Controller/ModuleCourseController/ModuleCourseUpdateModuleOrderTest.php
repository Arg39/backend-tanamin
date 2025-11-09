<?php

namespace Tests\Unit\Controller\ModuleCourseController;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use App\Models\Category;
use App\Models\User;
use App\Models\Course;
use App\Models\ModuleCourse;
use App\Http\Controllers\Api\Course\Material\ModuleCourseController;

class ModuleCourseUpdateModuleOrderTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Test that the private updateModuleOrder method compacts gaps and normalizes orders
     */
    public function test_updateModuleOrder_compacts_and_reorders_modules()
    {
        // create required related records
        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'OrderTest Category',
            'image' => null,
        ]);

        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instr_' . Str::random(6),
            'first_name' => 'Instr',
            'last_name' => 'Order',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        // create course
        $course = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Course For Order Normalize',
            'price' => null,
            'is_discount_active' => false,
        ]);

        // create modules with non-sequential orders (gaps)
        $m1 = ModuleCourse::create([
            'id' => Str::uuid()->toString(),
            'course_id' => $course->id,
            'title' => 'Module A',
            'order' => 0,
        ]);

        $m2 = ModuleCourse::create([
            'id' => Str::uuid()->toString(),
            'course_id' => $course->id,
            'title' => 'Module B',
            'order' => 5,
        ]);

        $m3 = ModuleCourse::create([
            'id' => Str::uuid()->toString(),
            'course_id' => $course->id,
            'title' => 'Module C',
            'order' => 2,
        ]);

        $m4 = ModuleCourse::create([
            'id' => Str::uuid()->toString(),
            'course_id' => $course->id,
            'title' => 'Module D',
            'order' => 3,
        ]);

        // capture the sequence of module ids when sorted by current order (before normalization)
        $originalOrdered = ModuleCourse::where('course_id', $course->id)
            ->orderBy('order', 'asc')
            ->get()
            ->pluck('id')
            ->toArray();

        $this->assertCount(4, $originalOrdered, 'Precondition: 4 modules should exist');

        // invoke private method updateModuleOrder via reflection
        $controller = new ModuleCourseController();
        $refMethod = new \ReflectionMethod(ModuleCourseController::class, 'updateModuleOrder');
        $refMethod->setAccessible(true);
        $refMethod->invoke($controller, $course->id);

        // fetch modules after normalization
        $after = ModuleCourse::where('course_id', $course->id)
            ->orderBy('order', 'asc')
            ->get();

        $this->assertCount(4, $after, 'Module count should remain the same after reordering');

        // collect orders and ids
        $orders = $after->pluck('order')->toArray();
        $idsAfter = $after->pluck('id')->toArray();

        // orders should be normalized to 0..3
        $expectedOrders = [0, 1, 2, 3];
        $this->assertEquals($expectedOrders, $orders, 'Orders should be normalized to contiguous sequence starting at 0');

        // relative ordering (by original order) should be preserved
        $this->assertEquals($originalOrdered, $idsAfter, 'Relative module ordering should be preserved after normalization');
    }
}
