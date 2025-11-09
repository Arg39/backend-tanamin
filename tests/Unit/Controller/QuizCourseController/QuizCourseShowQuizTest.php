<?php

namespace Tests\Unit\Controller\QuizCourseController;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use App\Models\Category;
use App\Models\User;
use App\Models\Course;
use App\Models\ModuleCourse;
use App\Models\LessonCourse;
use App\Models\Quiz;
use App\Models\Question;
use App\Models\AnswerOption;
use App\Models\QuizAttempt;
use App\Http\Controllers\Api\Course\Material\QuizCourseController;
use App\Models\LessonQuiz;
use Tymon\JWTAuth\Facades\JWTAuth;

class QuizCourseShowQuizTest extends TestCase
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

    public function test_show_returns_lesson_not_found()
    {
        $controller = new QuizCourseController();
        $request = new Request();
        $fakeLessonId = Str::uuid()->toString();

        $response = $controller->showQuiz($fakeLessonId);
        $data = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $data);
        if (is_bool($data['status'])) {
            $this->assertFalse($data['status']);
        } else {
            $this->assertNotEquals('success', $data['status']);
        }

        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('Lesson not found', $data['message']);
    }

    public function test_show_returns_quiz_not_found_when_no_quiz()
    {
        // create related records
        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instr_' . Str::random(6),
            'first_name' => 'Instr',
            'last_name' => 'NoQuiz',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'NoQuiz Cat',
        ]);

        $course = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Course No Quiz',
        ]);

        $module = ModuleCourse::create([
            'id' => Str::uuid()->toString(),
            'course_id' => $course->id,
            'title' => 'Module No Quiz',
        ]);

        $lesson = LessonCourse::create([
            'id' => Str::uuid()->toString(),
            'module_id' => $module->id,
            'title' => 'Lesson No Quiz',
            'type' => 'quiz',
            'order' => 0,
        ]);

        $controller = new QuizCourseController();
        $request = new Request();
        $response = $controller->showQuiz($lesson->id);
        $data = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $data);
        if (is_bool($data['status'])) {
            $this->assertFalse($data['status']);
        } else {
            $this->assertNotEquals('success', $data['status']);
        }

        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('Quiz not found', $data['message']);
    }

    public function test_show_returns_questions_and_options_without_is_correct()
    {
        // create required records
        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instr_' . Str::random(6),
            'first_name' => 'Instr',
            'last_name' => 'Quiz',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Quiz Cat',
        ]);

        $course = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Course With Quiz',
        ]);

        $module = ModuleCourse::create([
            'id' => Str::uuid()->toString(),
            'course_id' => $course->id,
            'title' => 'Module Quiz',
        ]);

        $lesson = LessonCourse::create([
            'id' => Str::uuid()->toString(),
            'module_id' => $module->id,
            'title' => 'Lesson With Quiz',
            'type' => 'quiz',
            'order' => 0,
        ]);

        $quiz = LessonQuiz::create([
            'id' => Str::uuid()->toString(),
            'lesson_id' => $lesson->id,
            'title' => 'Sample Quiz',
        ]);

        $question = Question::create([
            'id' => Str::uuid()->toString(),
            'quiz_id' => $quiz->id,
            'question' => 'What is 1+1?',
        ]);

        $opt1 = AnswerOption::create([
            'id' => Str::uuid()->toString(),
            'question_id' => $question->id,
            'answer' => '1',
            'is_correct' => false,
        ]);
        $opt2 = AnswerOption::create([
            'id' => Str::uuid()->toString(),
            'question_id' => $question->id,
            'answer' => '2',
            'is_correct' => true,
        ]);
        $opt3 = AnswerOption::create([
            'id' => Str::uuid()->toString(),
            'question_id' => $question->id,
            'answer' => '3',
            'is_correct' => false,
        ]);

        $controller = new QuizCourseController();
        $request = new Request();
        $response = $controller->showQuiz($lesson->id);
        $data = $this->resolveResponseData($response, $request);

        // status
        $this->assertArrayHasKey('status', $data);
        if (is_bool($data['status'])) {
            $this->assertTrue($data['status']);
        } else {
            $this->assertEquals('success', $data['status']);
        }

        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('Quiz fetched successfully', $data['message']);

        $this->assertArrayHasKey('data', $data);
        $d = $data['data'];
        $this->assertEquals($lesson->id, $d['id']);
        $this->assertArrayHasKey('content', $d);
        $this->assertArrayHasKey('attempt', $d);
        $this->assertFalse($d['attempt']);

        $this->assertIsArray($d['content']);
        $first = $d['content'][0];
        $this->assertArrayHasKey('answer', $first);
        $answerArr = $first['answer'];

        // options 1..4 present
        $this->assertArrayHasKey('option1', $answerArr);
        $this->assertArrayHasKey('option2', $answerArr);
        $this->assertArrayHasKey('option3', $answerArr);
        $this->assertArrayHasKey('option4', $answerArr);

        // Collect non-null option ids from response (order-independent)
        $responseOptionIds = [];
        foreach (['option1', 'option2', 'option3', 'option4'] as $optKey) {
            if (!is_null($answerArr[$optKey]['id'])) {
                $responseOptionIds[] = $answerArr[$optKey]['id'];
            }
        }

        $expectedOptionIds = [$opt1->id, $opt2->id, $opt3->id];
        // compare regardless of order
        $this->assertEqualsCanonicalizing($expectedOptionIds, $responseOptionIds);

        // None of the non-null options should expose is_correct when no attempt
        foreach (['option1', 'option2', 'option3'] as $optKey) {
            if (!is_null($answerArr[$optKey]['id'])) {
                $this->assertArrayNotHasKey('is_correct', $answerArr[$optKey]);
            }
        }

        // filler option4 should have nulls and no is_correct
        $this->assertNull($answerArr['option4']['id']);
        $this->assertNull($answerArr['option4']['answer']);
        $this->assertArrayNotHasKey('is_correct', $answerArr['option4']);

        // user_answer and is_correct fields at question-level should be null
        $this->assertArrayHasKey('user_answer', $first);
        $this->assertNull($first['user_answer']);
        $this->assertArrayHasKey('is_correct', $first);
        $this->assertNull($first['is_correct']);
    }

    public function test_show_includes_user_attempt_and_is_correct_when_authenticated()
    {
        // create user who attempted
        $user = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'stu_' . Str::random(6),
            'first_name' => 'Student',
            'last_name' => 'One',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        // create other required records
        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instr_' . Str::random(6),
            'first_name' => 'Instr',
            'last_name' => 'Quiz',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Quiz Cat 2',
        ]);

        $course = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Course With Quiz 2',
        ]);

        $module = ModuleCourse::create([
            'id' => Str::uuid()->toString(),
            'course_id' => $course->id,
            'title' => 'Module Quiz 2',
        ]);

        $lesson = LessonCourse::create([
            'id' => Str::uuid()->toString(),
            'module_id' => $module->id,
            'title' => 'Lesson With Quiz 2',
            'type' => 'quiz',
            'order' => 0,
        ]);

        $quiz = LessonQuiz::create([
            'id' => Str::uuid()->toString(),
            'lesson_id' => $lesson->id,
            'title' => 'Sample Quiz 2',
        ]);

        $question = Question::create([
            'id' => Str::uuid()->toString(),
            'quiz_id' => $quiz->id,
            'question' => 'What is 3+2?',
        ]);

        $opt1 = AnswerOption::create([
            'id' => Str::uuid()->toString(),
            'question_id' => $question->id,
            'answer' => '4',
            'is_correct' => false,
        ]);
        $opt2 = AnswerOption::create([
            'id' => Str::uuid()->toString(),
            'question_id' => $question->id,
            'answer' => '5',
            'is_correct' => true,
        ]);

        // create attempt where user picked opt2 (correct)
        $answersMap = [
            $question->id => $opt2->id,
        ];

        $attempt = QuizAttempt::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $user->id,
            'lesson_id' => $lesson->id,
            'answers' => $answersMap,
            'score' => 100,
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        // mock JWTAuth to return the user
        JWTAuth::shouldReceive('user')->andReturn($user);

        $controller = new QuizCourseController();
        $request = new Request();
        $response = $controller->showQuiz($lesson->id);
        $data = $this->resolveResponseData($response, $request);

        // status and message
        $this->assertArrayHasKey('status', $data);
        if (is_bool($data['status'])) {
            $this->assertTrue($data['status']);
        } else {
            $this->assertEquals('success', $data['status']);
        }

        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('Quiz fetched successfully', $data['message']);

        $this->assertArrayHasKey('data', $data);
        $d = $data['data'];
        $this->assertTrue($d['attempt']);
        $this->assertArrayHasKey('score', $d);
        $this->assertEquals($attempt->score, $d['score']);

        $first = $d['content'][0];
        $answerArr = $first['answer'];

        // Collect non-null option ids from response and assert they match created ones
        $responseOptionIds = [];
        foreach (['option1', 'option2', 'option3', 'option4'] as $optKey) {
            if (!is_null($answerArr[$optKey]['id'])) {
                $responseOptionIds[] = $answerArr[$optKey]['id'];
            }
        }
        $expectedOptionIds = [$opt1->id, $opt2->id];
        $this->assertEqualsCanonicalizing($expectedOptionIds, $responseOptionIds);

        // For authenticated attempt, non-null options should include is_correct key
        foreach (['option1', 'option2', 'option3', 'option4'] as $optKey) {
            if (!is_null($answerArr[$optKey]['id'])) {
                $this->assertArrayHasKey('is_correct', $answerArr[$optKey]);
            }
        }

        // user_answer should match attempt
        $this->assertEquals($opt2->id, $first['user_answer']);
        $this->assertTrue($first['is_correct']);

        // Also confirm that the option matching opt2 has is_correct true
        $foundCorrectFlag = null;
        foreach (['option1', 'option2', 'option3', 'option4'] as $optKey) {
            if (!is_null($answerArr[$optKey]['id']) && $answerArr[$optKey]['id'] === $opt2->id) {
                $foundCorrectFlag = $answerArr[$optKey]['is_correct'];
                break;
            }
        }
        $this->assertTrue($foundCorrectFlag === true);
    }
}
