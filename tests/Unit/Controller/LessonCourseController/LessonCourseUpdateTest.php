<?php

namespace Tests\Unit\Controller\LessonCourseController;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Http\Controllers\Api\Course\Material\LessonCourseController;
use App\Models\Category;
use App\Models\Course;
use App\Models\ModuleCourse;
use App\Models\User;
use App\Models\LessonCourse;
use App\Models\LessonMaterial;
use App\Models\LessonQuiz;
use App\Models\Question;
use App\Models\AnswerOption;

class LessonCourseUpdateTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Test updating a material lesson successfully updates title, content and visibility.
     */
    public function test_updates_material_lesson_successfully()
    {
        // ...existing code...
        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'first_name' => 'John',
            'last_name' => 'Doe',
            'username' => 'john' . Str::random(6),
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Cat Material',
        ]);

        $course = Course::create([
            'id' => Str::uuid()->toString(),
            'instructor_id' => $instructor->id,
            'category_id' => $category->id,
            'title' => 'Course Material',
        ]);

        $module = ModuleCourse::create([
            'id' => Str::uuid()->toString(),
            'course_id' => $course->id,
            'title' => 'Module Material',
        ]);

        $lesson = LessonCourse::create([
            'id' => Str::uuid()->toString(),
            'module_id' => $module->id,
            'title' => 'Old Lesson Title',
            'type' => 'material',
            'order' => 0,
        ]);

        $material = LessonMaterial::create([
            'id' => Str::uuid()->toString(),
            'lesson_id' => $lesson->id,
            'content' => '<p>Old content</p>',
            'visible' => false,
        ]);

        // act: call controller update
        $request = new Request([
            'title' => 'Updated Lesson Title',
            'type' => 'material',
            'materialContent' => '<p>Updated content</p>',
            'visible' => true,
        ]);

        $controller = new LessonCourseController();
        $response = $controller->update($request, $lesson->id);
        $data = $response->toArray($request);

        // assert response
        $this->assertArrayHasKey('status', $data);
        $this->assertEquals('success', $data['status']);
        $this->assertEquals('Lesson updated successfully', $data['message']);
        $this->assertEquals('Updated Lesson Title', $data['data']['title']);

        // assert DB changes
        $materialFresh = LessonMaterial::where('lesson_id', $lesson->id)->first();
        $this->assertNotNull($materialFresh);
        $this->assertStringContainsString('Updated content', $materialFresh->content);
        $this->assertTrue((bool)$materialFresh->visible);
        // ...existing code...
    }

    /**
     * Test update returns appropriate error when material is missing for a material lesson.
     */
    public function test_update_returns_error_when_material_missing()
    {
        // ...existing code...
        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'first_name' => 'NoMat',
            'last_name' => 'User',
            'username' => 'nomat' . Str::random(6),
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Cat NoMat',
        ]);

        $course = Course::create([
            'id' => Str::uuid()->toString(),
            'instructor_id' => $instructor->id,
            'category_id' => $category->id,
            'title' => 'Course NoMat',
        ]);

        $module = ModuleCourse::create([
            'id' => Str::uuid()->toString(),
            'course_id' => $course->id,
            'title' => 'Module NoMat',
        ]);

        $lesson = LessonCourse::create([
            'id' => Str::uuid()->toString(),
            'module_id' => $module->id,
            'title' => 'Lesson No Material',
            'type' => 'material',
            'order' => 0,
        ]);

        $request = new Request([
            'title' => 'Should Fail',
            'type' => 'material',
            'materialContent' => '<p>Content</p>',
        ]);

        $controller = new LessonCourseController();
        $response = $controller->update($request, $lesson->id);
        $data = $response->toArray($request);

        $this->assertArrayHasKey('status', $data);
        // resource may return boolean false or 'error' style; check message explicitly
        $this->assertEquals('Material not found for this lesson', $data['message']);
        if (is_string($data['status'])) {
            $this->assertNotEquals('success', $data['status']);
        } else {
            $this->assertFalse($data['status']);
        }
        // ...existing code...
    }

    /**
     * Test updating a quiz lesson: updates existing question/options, creates new question,
     * and deletes omitted questions (and their options).
     */
    public function test_updates_quiz_lesson_questions_and_options_and_deletes_removed()
    {
        // ...existing code...
        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'first_name' => 'Quiz',
            'last_name' => 'Master',
            'username' => 'quizmaster' . Str::random(4),
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Cat Quiz',
        ]);

        $course = Course::create([
            'id' => Str::uuid()->toString(),
            'instructor_id' => $instructor->id,
            'category_id' => $category->id,
            'title' => 'Course Quiz',
        ]);

        $module = ModuleCourse::create([
            'id' => Str::uuid()->toString(),
            'course_id' => $course->id,
            'title' => 'Module Quiz',
        ]);

        $lesson = LessonCourse::create([
            'id' => Str::uuid()->toString(),
            'module_id' => $module->id,
            'title' => 'Quiz Lesson',
            'type' => 'quiz',
            'order' => 0,
        ]);

        $quiz = LessonQuiz::create([
            'id' => Str::uuid()->toString(),
            'lesson_id' => $lesson->id,
            'title' => 'Original Quiz',
        ]);

        // question 1 (will be updated)
        $q1 = Question::create([
            'id' => Str::uuid()->toString(),
            'quiz_id' => $quiz->id,
            'question' => '<p>Old Q1</p>',
            'order' => 0,
        ]);
        $q1o1 = AnswerOption::create([
            'id' => Str::uuid()->toString(),
            'question_id' => $q1->id,
            'answer' => 'Old A1',
            'is_correct' => 0,
        ]);
        $q1o2 = AnswerOption::create([
            'id' => Str::uuid()->toString(),
            'question_id' => $q1->id,
            'answer' => 'Old A2',
            'is_correct' => 1,
        ]);

        // question 2 (will be removed)
        $q2 = Question::create([
            'id' => Str::uuid()->toString(),
            'quiz_id' => $quiz->id,
            'question' => '<p>Old Q2</p>',
            'order' => 1,
        ]);
        $q2o1 = AnswerOption::create([
            'id' => Str::uuid()->toString(),
            'question_id' => $q2->id,
            'answer' => 'Q2 A1',
            'is_correct' => 0,
        ]);

        // prepare request:
        // - include q1 with its id and option_ids to update both options and change correctAnswer
        // - omit q2 (so it should be deleted)
        // - include a new question without id
        $request = new Request([
            'title' => 'Quiz Lesson Updated',
            'type' => 'quiz',
            'quizContent' => [
                [
                    'id' => $q1->id,
                    'question' => '<p>Updated Q1</p>',
                    'options' => ['Updated A1', 'Updated A2'],
                    'option_ids' => [$q1o1->id, $q1o2->id],
                    'correctAnswer' => 0, // make first option correct now
                ],
                [
                    // new question
                    'question' => '<p>New Q3</p>',
                    'options' => ['N A1', 'N A2'],
                    // no option_ids for new question
                    'correctAnswer' => 1,
                ],
            ],
        ]);

        $controller = new LessonCourseController();
        $response = $controller->update($request, $lesson->id);
        $data = $response->toArray($request);

        // response asserts
        $this->assertArrayHasKey('status', $data);
        $this->assertEquals('success', $data['status']);
        $this->assertEquals('Lesson updated successfully', $data['message']);
        $this->assertEquals('Quiz Lesson Updated', $data['data']['title']);

        // DB asserts:
        // q1 should exist and be updated
        $q1Fresh = Question::find($q1->id);
        $this->assertNotNull($q1Fresh);
        $this->assertStringContainsString('Updated Q1', $q1Fresh->question);
        $this->assertEquals(0, $q1Fresh->order);

        $optsQ1 = $q1Fresh->answerOptions()->get();
        $this->assertCount(2, $optsQ1);

        // Do not rely on DB ordering; assert by answer content and correctness
        $answers = $optsQ1->pluck('answer')->all();
        $this->assertContains('Updated A1', $answers);
        $this->assertContains('Updated A2', $answers);

        $optUpdatedA1 = $optsQ1->firstWhere('answer', 'Updated A1');
        $this->assertNotNull($optUpdatedA1, 'Updated A1 option should exist');
        $this->assertEquals(1, $optUpdatedA1->is_correct, 'Updated A1 should be marked correct');

        // q2 should be deleted
        $q2Fresh = Question::find($q2->id);
        $this->assertNull($q2Fresh);
        // its options should be deleted
        $q2o1Fresh = AnswerOption::find($q2o1->id);
        $this->assertNull($q2o1Fresh);

        // new question should exist
        $newQuestion = Question::where('quiz_id', $quiz->id)->where('question', 'like', '%New Q3%')->first();
        $this->assertNotNull($newQuestion);
        $this->assertEquals(1, $newQuestion->order);

        $newOptions = $newQuestion->answerOptions;
        $this->assertCount(2, $newOptions);

        // Find the created correct option by its answer text (N A2 should be correct as correctAnswer => 1)
        $newCorrect = $newOptions->firstWhere('answer', 'N A2');
        $this->assertNotNull($newCorrect, 'Expected new option N A2 to be present');
        $this->assertEquals(1, $newCorrect->is_correct);
    }
}
