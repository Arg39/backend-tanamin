<?php

namespace Tests\Unit\Controller\ModuleCourseController;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use App\Models\Course;
use App\Models\ModuleCourse;
use App\Models\LessonCourse;
use App\Models\LessonMaterial;
use App\Models\LessonProgress;
use App\Models\User;
use App\Models\Category;
use App\Http\Controllers\Api\Course\Material\ModuleCourseController;
use Tymon\JWTAuth\Facades\JWTAuth;

class ModuleCourseIndexTest extends TestCase
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

    public function test_index_returns_modules_and_lessons_without_authenticated_user()
    {
        // create related records required by DB constraints
        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'ModuleTest Category',
            'image' => null,
        ]);

        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instr_' . Str::random(6),
            'first_name' => 'Instr',
            'last_name' => 'Mod',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        // create a course
        $course = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Course for Modules Test',
            'price' => null,
            'is_discount_active' => false,
        ]);

        // create modules
        $module1 = ModuleCourse::create([
            'id' => Str::uuid()->toString(),
            'course_id' => $course->id,
            'title' => 'Module 1',
            'order' => 0,
        ]);

        $module2 = ModuleCourse::create([
            'id' => Str::uuid()->toString(),
            'course_id' => $course->id,
            'title' => 'Module 2',
            'order' => 1,
        ]);

        // module1 lessons
        $lesson1 = LessonCourse::create([
            'id' => Str::uuid()->toString(),
            'module_id' => $module1->id,
            'title' => 'Material Lesson',
            'type' => 'material',
            'order' => 0,
        ]);

        // add material (visible = true) - include required content field
        LessonMaterial::create([
            'id' => Str::uuid()->toString(),
            'lesson_id' => $lesson1->id,
            'content' => '',
            'visible' => true,
        ]);

        $lesson2 = LessonCourse::create([
            'id' => Str::uuid()->toString(),
            'module_id' => $module1->id,
            'title' => 'Quiz Lesson',
            'type' => 'quiz',
            'order' => 1,
        ]);

        // module2 lesson with material visible = false
        $lesson3 = LessonCourse::create([
            'id' => Str::uuid()->toString(),
            'module_id' => $module2->id,
            'title' => 'Hidden Material',
            'type' => 'material',
            'order' => 0,
        ]);
        LessonMaterial::create([
            'id' => Str::uuid()->toString(),
            'lesson_id' => $lesson3->id,
            'content' => '',
            'visible' => false,
        ]);

        $controller = new ModuleCourseController();
        $request = new Request();
        $response = $controller->index($course->id);

        $responseData = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $responseData);
        if (is_bool($responseData['status'])) {
            $this->assertTrue($responseData['status']);
        } else {
            $this->assertEquals('success', $responseData['status']);
        }

        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Modules fetched successfully', $responseData['message']);

        $this->assertArrayHasKey('data', $responseData);
        $data = $responseData['data'];
        $this->assertIsArray($data);
        $this->assertCount(2, $data, 'Expected 2 modules returned');

        // normalize first module
        $m1 = $data[0];
        $this->assertArrayHasKey('lessons', $m1);
        $this->assertIsArray($m1['lessons']);
        // find material lesson and quiz lesson
        $foundMaterial = null;
        $foundQuiz = null;
        foreach ($m1['lessons'] as $l) {
            if ($l['id'] === $lesson1->id) $foundMaterial = $l;
            if ($l['id'] === $lesson2->id) $foundQuiz = $l;
        }
        $this->assertNotNull($foundMaterial, 'Material lesson should be present');
        $this->assertArrayHasKey('visible', $foundMaterial);
        $this->assertTrue($foundMaterial['visible']);
        $this->assertArrayHasKey('completed', $foundMaterial);
        $this->assertFalse($foundMaterial['completed']);

        $this->assertNotNull($foundQuiz, 'Quiz lesson should be present');
        $this->assertArrayHasKey('completed', $foundQuiz);
        $this->assertFalse($foundQuiz['completed']);
        // quiz lesson should not have 'visible' property (controller only adds for type material)
        $this->assertArrayNotHasKey('visible', $foundQuiz);
    }

    public function test_index_marks_completed_lessons_when_user_authenticated()
    {
        // create a user
        $user = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'user_' . Str::random(6),
            'first_name' => 'User',
            'last_name' => 'Test',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        // mock JWTAuth::user() to return this user
        JWTAuth::shouldReceive('user')->andReturn($user);

        // create related records required by DB constraints
        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'ModuleTest Category 2',
            'image' => null,
        ]);
        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instr_' . Str::random(6),
            'first_name' => 'Instr',
            'last_name' => 'Mod2',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        // create course, module, lessons
        $course = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Course For Completed Test',
            'price' => null,
            'is_discount_active' => false,
        ]);

        $module = ModuleCourse::create([
            'id' => Str::uuid()->toString(),
            'course_id' => $course->id,
            'title' => 'Module Completed',
            'order' => 0,
        ]);

        $lessonA = LessonCourse::create([
            'id' => Str::uuid()->toString(),
            'module_id' => $module->id,
            'title' => 'Lesson A',
            'type' => 'material',
            'order' => 0,
        ]);
        LessonMaterial::create([
            'id' => Str::uuid()->toString(),
            'lesson_id' => $lessonA->id,
            'content' => '',
            'visible' => true,
        ]);

        $lessonB = LessonCourse::create([
            'id' => Str::uuid()->toString(),
            'module_id' => $module->id,
            'title' => 'Lesson B',
            'type' => 'material',
            'order' => 1,
        ]);
        LessonMaterial::create([
            'id' => Str::uuid()->toString(),
            'lesson_id' => $lessonB->id,
            'content' => '',
            'visible' => true,
        ]);

        // mark lessonA as completed for user
        LessonProgress::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $user->id,
            'lesson_id' => $lessonA->id,
            'completed_at' => now(),
        ]);

        $controller = new ModuleCourseController();
        $request = new Request();
        $response = $controller->index($course->id);

        $responseData = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $responseData);
        if (is_bool($responseData['status'])) {
            $this->assertTrue($responseData['status']);
        } else {
            $this->assertEquals('success', $responseData['status']);
        }

        $this->assertArrayHasKey('data', $responseData);
        $data = $responseData['data'];
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);

        // locate module and lessons
        $found = null;
        foreach ($data as $m) {
            if ($m['id'] === $module->id) {
                $found = $m;
                break;
            }
        }
        $this->assertNotNull($found, 'Module should be present in response');
        $this->assertIsArray($found['lessons']);

        $completedMap = [];
        foreach ($found['lessons'] as $l) {
            $completedMap[$l['id']] = $l['completed'] ?? false;
        }

        $this->assertTrue($completedMap[$lessonA->id], 'Lesson A should be marked completed for the user');
        $this->assertFalse($completedMap[$lessonB->id], 'Lesson B should not be marked completed for the user');
    }
}
