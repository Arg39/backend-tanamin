<?php

namespace Tests\Unit\Controller\LessonProgressCourseController;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use App\Models\Category;
use App\Models\Course;
use App\Models\ModuleCourse;
use App\Models\LessonCourse;
use App\Models\User;
use App\Models\LessonProgress;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Http\Controllers\Api\Course\Material\LessonProgressCourseController;

class LessonProgressCourseStoreProgressLessonTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_creates_progress_when_none_exists()
    {
        // create related records
        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instr_' . Str::random(6),
            'first_name' => 'Instr',
            'last_name' => 'CreateProg',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $student = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'stud_' . Str::random(6),
            'first_name' => 'Student',
            'last_name' => 'Prog',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Progress Cat',
        ]);

        $course = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Course for Progress Test',
        ]);

        $module = ModuleCourse::create([
            'id' => Str::uuid()->toString(),
            'course_id' => $course->id,
            'title' => 'Module Prog',
        ]);

        $lesson = LessonCourse::create([
            'id' => Str::uuid()->toString(),
            'module_id' => $module->id,
            'title' => 'Lesson Prog',
            'type' => 'material',
            'order' => 0,
        ]);

        // mock authenticated user
        JWTAuth::shouldReceive('user')->andReturn($student);

        $request = new Request([
            'lesson_id' => $lesson->id,
        ]);

        $controller = new LessonProgressCourseController();
        $response = $controller->storeProgressLesson($request);

        $data = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $data);
        if (is_bool($data['status'])) {
            $this->assertTrue($data['status']);
        } else {
            $this->assertEquals('success', $data['status']);
        }

        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('Progress berhasil disimpan.', $data['message']);

        $this->assertArrayHasKey('data', $data);
        $prog = $data['data'];
        $this->assertArrayHasKey('user_id', $prog);
        $this->assertArrayHasKey('lesson_id', $prog);
        $this->assertEquals($student->id, $prog['user_id']);
        $this->assertEquals($lesson->id, $prog['lesson_id']);
        $this->assertArrayHasKey('completed_at', $prog);
        $this->assertNotNull($prog['completed_at']);

        $this->assertDatabaseHas('lesson_progresses', [
            'user_id' => $student->id,
            'lesson_id' => $lesson->id,
        ]);
    }

    public function test_does_not_overwrite_completed_at_if_already_completed()
    {
        // create related records
        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instr_' . Str::random(6),
            'first_name' => 'Instr',
            'last_name' => 'Exists',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $student = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'stud_' . Str::random(6),
            'first_name' => 'Student',
            'last_name' => 'Exists',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Progress Cat 2',
        ]);

        $course = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Course for Progress Test 2',
        ]);

        $module = ModuleCourse::create([
            'id' => Str::uuid()->toString(),
            'course_id' => $course->id,
            'title' => 'Module Prog 2',
        ]);

        $lesson = LessonCourse::create([
            'id' => Str::uuid()->toString(),
            'module_id' => $module->id,
            'title' => 'Lesson Prog 2',
            'type' => 'material',
            'order' => 0,
        ]);

        // create existing progress with a specific completed_at
        $existing = LessonProgress::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $student->id,
            'lesson_id' => $lesson->id,
            'completed_at' => now()->subDay(),
        ]);

        $originalCompletedAt = $existing->completed_at->toDateTimeString();

        // mock authenticated user
        JWTAuth::shouldReceive('user')->andReturn($student);

        $request = new Request([
            'lesson_id' => $lesson->id,
        ]);

        $controller = new LessonProgressCourseController();
        $response = $controller->storeProgressLesson($request);
        $data = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $data);
        if (is_bool($data['status'])) {
            $this->assertTrue($data['status']);
        } else {
            $this->assertEquals('success', $data['status']);
        }

        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('Progress berhasil disimpan.', $data['message']);

        $fresh = LessonProgress::where('id', $existing->id)->first();
        $this->assertEquals($originalCompletedAt, $fresh->completed_at->toDateTimeString(), 'completed_at should not be overwritten when already set');
    }
}
