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
use App\Models\LessonQuiz;
use App\Models\Question;
use App\Models\AnswerOption;
use App\Models\QuizAttempt;
use App\Http\Controllers\Api\Course\Material\QuizCourseController;
use Tymon\JWTAuth\Facades\JWTAuth;

class QuizCourseSubmitAnswersTest extends TestCase
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

    public function test_submit_records_attempt_and_calculates_percentage_correctly()
    {
        // create student and supporting records
        $student = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'stu_' . Str::random(6),
            'first_name' => 'Student',
            'last_name' => 'Test',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instr_' . Str::random(6),
            'first_name' => 'Instr',
            'last_name' => 'Test',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Quiz Submit Cat',
        ]);

        $course = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Submit Course',
        ]);

        $module = ModuleCourse::create([
            'id' => Str::uuid()->toString(),
            'course_id' => $course->id,
            'title' => 'Submit Module',
        ]);

        $lesson = LessonCourse::create([
            'id' => Str::uuid()->toString(),
            'module_id' => $module->id,
            'title' => 'Submit Lesson',
            'type' => 'quiz',
            'order' => 0,
        ]);

        $quiz = LessonQuiz::create([
            'id' => Str::uuid()->toString(),
            'lesson_id' => $lesson->id,
            'title' => 'Submit Quiz',
        ]);

        // Question 1 (correct = opt1)
        $q1 = Question::create([
            'id' => Str::uuid()->toString(),
            'quiz_id' => $quiz->id,
            'question' => 'Q1?',
        ]);
        $q1o1 = AnswerOption::create([
            'id' => Str::uuid()->toString(),
            'question_id' => $q1->id,
            'answer' => 'A',
            'is_correct' => true,
        ]);
        $q1o2 = AnswerOption::create([
            'id' => Str::uuid()->toString(),
            'question_id' => $q1->id,
            'answer' => 'B',
            'is_correct' => false,
        ]);

        // Question 2 (correct = opt3)
        $q2 = Question::create([
            'id' => Str::uuid()->toString(),
            'quiz_id' => $quiz->id,
            'question' => 'Q2?',
        ]);
        $q2o1 = AnswerOption::create([
            'id' => Str::uuid()->toString(),
            'question_id' => $q2->id,
            'answer' => 'C',
            'is_correct' => false,
        ]);
        $q2o2 = AnswerOption::create([
            'id' => Str::uuid()->toString(),
            'question_id' => $q2->id,
            'answer' => 'D',
            'is_correct' => false,
        ]);
        $q2o3 = AnswerOption::create([
            'id' => Str::uuid()->toString(),
            'question_id' => $q2->id,
            'answer' => 'E',
            'is_correct' => true,
        ]);

        // Submit answers: q1 correct, q2 incorrect => 1/2 => 50%
        $submitted = [
            [$q1->id => $q1o1->id],
            [$q2->id => $q2o2->id],
        ];

        JWTAuth::shouldReceive('user')->andReturn($student);

        $request = new Request([
            'answer' => $submitted,
        ]);

        $controller = new QuizCourseController();
        $response = $controller->submitAnswers($request, $lesson->id);
        $data = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $data);
        if (is_bool($data['status'])) {
            $this->assertTrue($data['status']);
        } else {
            $this->assertEquals('success', $data['status']);
        }

        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('Quiz submitted successfully', $data['message']);

        $this->assertArrayHasKey('data', $data);
        $d = $data['data'];

        $this->assertArrayHasKey('attempt_id', $d);
        $this->assertArrayHasKey('score', $d);
        $this->assertArrayHasKey('total', $d);
        $this->assertArrayHasKey('answers', $d);

        $this->assertEquals(2, $d['total']);
        $this->assertEquals(50, $d['score']); // 1 out of 2 => 50%

        // Ensure the answers mapping matches flattened mapping
        $expectedMapping = [
            $q1->id => $q1o1->id,
            $q2->id => $q2o2->id,
        ];
        $this->assertEquals($expectedMapping, $d['answers']);

        // Assert DB has the attempt with expected score and user/lesson ids
        $this->assertDatabaseHas('quiz_attempts', [
            'id' => $d['attempt_id'],
            'user_id' => $student->id,
            'lesson_id' => $lesson->id,
            'score' => 50,
        ]);
    }

    public function test_submit_with_all_incorrect_answers_results_in_zero_score()
    {
        // create student and supporting records
        $student = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'stu_' . Str::random(6),
            'first_name' => 'Student2',
            'last_name' => 'Test2',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instr_' . Str::random(6),
            'first_name' => 'Instr2',
            'last_name' => 'Test2',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Quiz Submit Cat 2',
        ]);

        $course = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Submit Course 2',
        ]);

        $module = ModuleCourse::create([
            'id' => Str::uuid()->toString(),
            'course_id' => $course->id,
            'title' => 'Submit Module 2',
        ]);

        $lesson = LessonCourse::create([
            'id' => Str::uuid()->toString(),
            'module_id' => $module->id,
            'title' => 'Submit Lesson 2',
            'type' => 'quiz',
            'order' => 0,
        ]);

        $quiz = LessonQuiz::create([
            'id' => Str::uuid()->toString(),
            'lesson_id' => $lesson->id,
            'title' => 'Submit Quiz 2',
        ]);

        $q = Question::create([
            'id' => Str::uuid()->toString(),
            'quiz_id' => $quiz->id,
            'question' => 'Only Q?',
        ]);
        $o1 = AnswerOption::create([
            'id' => Str::uuid()->toString(),
            'question_id' => $q->id,
            'answer' => 'X',
            'is_correct' => true,
        ]);
        $o2 = AnswerOption::create([
            'id' => Str::uuid()->toString(),
            'question_id' => $q->id,
            'answer' => 'Y',
            'is_correct' => false,
        ]);

        // Submit incorrect option => 0/1 => 0%
        $submitted = [
            [$q->id => $o2->id],
        ];

        JWTAuth::shouldReceive('user')->andReturn($student);

        $request = new Request([
            'answer' => $submitted,
        ]);

        $controller = new QuizCourseController();
        $response = $controller->submitAnswers($request, $lesson->id);
        $data = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $data);
        if (is_bool($data['status'])) {
            $this->assertTrue($data['status']);
        } else {
            $this->assertEquals('success', $data['status']);
        }

        $this->assertArrayHasKey('data', $data);
        $d = $data['data'];

        $this->assertEquals(1, $d['total']);
        $this->assertEquals(0, $d['score']);

        $this->assertDatabaseHas('quiz_attempts', [
            'id' => $d['attempt_id'],
            'user_id' => $student->id,
            'lesson_id' => $lesson->id,
            'score' => 0,
        ]);
    }
}
