<?php

namespace Tests\Unit\Controller\Course\Material\LessonCourseController;

use App\Http\Controllers\Api\Course\Material\LessonCourseController;
use App\Http\Resources\PostResource;
use App\Models\Category;
use App\Models\Course;
use App\Models\ModuleCourse;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Tests\TestCase;

class StoreLessonTest extends TestCase
{
    use DatabaseTransactions;

    public function test_stores_material_lesson_successfully()
    {
        $instructor = User::create([
            'id' => Str::uuid(),
            'first_name' => 'John',
            'last_name' => 'Doe',
            'username' => 'johndoe',
            'email' => 'john@example.com',
            'password' => bcrypt('password'),
            'role' => 'instructor',
            'status' => 'active',
        ]);

        $category = Category::create([
            'id' => Str::uuid(),
            'name' => 'Programming',
        ]);

        $course = Course::create([
            'id' => Str::uuid(),
            'instructor_id' => $instructor->id,
            'category_id' => $category->id,
            'title' => 'Sample Course',
            'description' => 'Desc',
        ]);

        $module = ModuleCourse::create([
            'id' => Str::uuid(),
            'course_id' => $course->id,
            'title' => 'Module 1',
        ]);

        $request = new Request([
            'title' => 'Lesson 1',
            'type' => 'material',
            'materialContent' => '<p>Hello World</p>',
            'visible' => true,
        ]);

        $controller = new LessonCourseController();
        $response = $controller->store($request, $module->id);
        $data = $response->toArray($request);

        $this->assertEquals('success', $data['status']);
        $this->assertEquals('Lesson created successfully', $data['message']);
        $this->assertEquals('Lesson 1', $data['data']['title']);
    }

    public function test_stores_quiz_lesson_successfully()
    {
        $instructor = User::create([
            'id' => Str::uuid(),
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'username' => 'janedoe',
            'email' => 'jane@example.com',
            'password' => bcrypt('password'),
            'role' => 'instructor',
            'status' => 'active',
        ]);

        $category = Category::create([
            'id' => Str::uuid(),
            'name' => 'Science',
        ]);

        $course = Course::create([
            'id' => Str::uuid(),
            'instructor_id' => $instructor->id,
            'category_id' => $category->id,
            'title' => 'Quiz Course',
            'description' => 'Desc',
        ]);

        $module = ModuleCourse::create([
            'id' => Str::uuid(),
            'course_id' => $course->id,
            'title' => 'Module 2',
        ]);

        $request = new Request([
            'title' => 'Quiz Lesson',
            'type' => 'quiz',
            'quizContent' => [
                [
                    'question' => 'What is 2+2?',
                    'options' => ['3', '4', '5'],
                    'correctAnswer' => 1,
                ],
            ],
        ]);

        $controller = new LessonCourseController();
        $response = $controller->store($request, $module->id);
        $data = $response->toArray($request);

        $this->assertEquals('success', $data['status']);
        $this->assertEquals('Lesson created successfully', $data['message']);
        $this->assertEquals('Quiz Lesson', $data['data']['title']);
    }
}
