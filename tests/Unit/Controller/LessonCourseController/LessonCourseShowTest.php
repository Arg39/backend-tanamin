<?php

namespace Tests\Unit\Controller\LessonCourseController;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use App\Models\Category;
use App\Models\Course;
use App\Models\User;
use App\Models\ModuleCourse;
use App\Models\LessonCourse;
use App\Models\LessonMaterial;
use App\Models\LessonQuiz;
use App\Models\Question;
use App\Models\AnswerOption;
use App\Http\Controllers\Api\Course\Material\LessonCourseController;

class LessonCourseShowTest extends TestCase
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

    public function test_show_returns_material_lesson_data_when_found()
    {
        // create related records
        $category = Category::create([
            'id' => (string) Str::uuid(),
            'name' => 'MaterialShowCat',
        ]);

        $instructor = User::create([
            'id' => (string) Str::uuid(),
            'username' => 'instr_' . Str::random(6),
            'first_name' => 'Instr',
            'last_name' => 'Material',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $course = Course::create([
            'id' => (string) Str::uuid(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Course for Lesson Show Material',
        ]);

        $module = ModuleCourse::create([
            'id' => (string) Str::uuid(),
            'course_id' => $course->id,
            'title' => 'Module Material',
            'order' => 0,
        ]);

        $lesson = LessonCourse::create([
            'id' => (string) Str::uuid(),
            'module_id' => $module->id,
            'title' => 'Material Lesson',
            'type' => 'material',
            'order' => 0,
        ]);

        $material = LessonMaterial::create([
            'id' => (string) Str::uuid(),
            'lesson_id' => $lesson->id,
            'content' => '<p>Material Content</p>',
            'visible' => true,
        ]);

        $controller = new LessonCourseController();
        $request = new Request();
        $response = $controller->show($lesson->id);

        $responseData = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $responseData);
        if (is_bool($responseData['status'])) {
            $this->assertTrue($responseData['status']);
        } else {
            $this->assertEquals('success', $responseData['status']);
        }

        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Lesson detail fetched successfully', $responseData['message']);

        $this->assertArrayHasKey('data', $responseData);
        $data = $responseData['data'];
        $this->assertIsArray($data);

        $this->assertEquals($lesson->id, $data['id']);
        $this->assertEquals($module->title, $data['module_title']);
        $this->assertEquals('Material Lesson', $data['lesson_title']);
        $this->assertEquals('material', $data['type']);

        $this->assertArrayHasKey('content', $data);
        $this->assertIsArray($data['content']);
        $this->assertEquals($material->id, $data['content']['id']);
        $this->assertEquals('<p>Material Content</p>', $data['content']['material']);
        // cast visible to boolean because resource may return 1/0
        $this->assertTrue((bool) $data['content']['visible']);
    }

    public function test_show_returns_quiz_lesson_data_when_found()
    {
        // create related records
        $category = Category::create([
            'id' => (string) Str::uuid(),
            'name' => 'QuizShowCat',
        ]);

        $instructor = User::create([
            'id' => (string) Str::uuid(),
            'username' => 'instr_' . Str::random(6),
            'first_name' => 'Instr',
            'last_name' => 'Quiz',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $course = Course::create([
            'id' => (string) Str::uuid(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Course for Lesson Show Quiz',
        ]);

        $module = ModuleCourse::create([
            'id' => (string) Str::uuid(),
            'course_id' => $course->id,
            'title' => 'Module Quiz',
            'order' => 0,
        ]);

        $lesson = LessonCourse::create([
            'id' => (string) Str::uuid(),
            'module_id' => $module->id,
            'title' => 'Quiz Lesson',
            'type' => 'quiz',
            'order' => 0,
        ]);

        $quiz = LessonQuiz::create([
            'id' => (string) Str::uuid(),
            'lesson_id' => $lesson->id,
            'title' => 'Quiz 1',
        ]);

        $q1 = Question::create([
            'id' => (string) Str::uuid(),
            'quiz_id' => $quiz->id,
            'question' => 'What is 1+1?',
            'order' => 0,
        ]);
        AnswerOption::create([
            'id' => (string) Str::uuid(),
            'question_id' => $q1->id,
            'answer' => '1',
            'is_correct' => 0,
        ]);
        AnswerOption::create([
            'id' => (string) Str::uuid(),
            'question_id' => $q1->id,
            'answer' => '2',
            'is_correct' => 1,
        ]);
        AnswerOption::create([
            'id' => (string) Str::uuid(),
            'question_id' => $q1->id,
            'answer' => '3',
            'is_correct' => 0,
        ]);

        $controller = new LessonCourseController();
        $request = new Request();
        $response = $controller->show($lesson->id);

        $responseData = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $responseData);
        if (is_bool($responseData['status'])) {
            $this->assertTrue($responseData['status']);
        } else {
            $this->assertEquals('success', $responseData['status']);
        }

        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Lesson detail fetched successfully', $responseData['message']);

        $this->assertArrayHasKey('data', $responseData);
        $data = $responseData['data'];
        $this->assertIsArray($data);

        $this->assertEquals($lesson->id, $data['id']);
        $this->assertEquals($module->title, $data['module_title']);
        $this->assertEquals('Quiz Lesson', $data['lesson_title']);
        $this->assertEquals('quiz', $data['type']);

        $this->assertArrayHasKey('content', $data);
        $this->assertIsArray($data['content']);
        $this->assertCount(1, $data['content']);

        $questionEntry = $data['content'][0];
        $this->assertEquals($q1->id, $questionEntry['id']);
        $this->assertEquals('What is 1+1?', $questionEntry['question']);
        $this->assertIsArray($questionEntry['options']);
        $this->assertCount(3, $questionEntry['options']);

        // check that one option is correct and matches the '2' answer
        $foundCorrect = false;
        foreach ($questionEntry['options'] as $opt) {
            if ($opt['is_correct']) {
                $foundCorrect = true;
                $this->assertEquals('2', $opt['answer']);
            }
        }
        $this->assertTrue($foundCorrect, 'There should be one correct answer');
    }

    public function test_show_returns_not_found_for_missing_lesson()
    {
        $fakeLessonId = (string) Str::uuid();

        $controller = new LessonCourseController();
        $request = new Request();
        $response = $controller->show($fakeLessonId);

        $responseData = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $responseData);
        if (is_bool($responseData['status'])) {
            $this->assertFalse($responseData['status']);
        } else {
            $this->assertNotEquals('success', $responseData['status']);
        }

        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Lesson not found', $responseData['message']);
    }
}
