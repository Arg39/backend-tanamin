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

class LessonCourseDestroyTest extends TestCase
{
    use DatabaseTransactions;

    public function test_destroy_material_lesson_deletes_material_and_reorders()
    {
        // arrange
        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'first_name' => 'Alice',
            'last_name' => 'Tester',
            'username' => 'alice' . Str::random(6),
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Destroy Cat',
        ]);

        $course = Course::create([
            'id' => Str::uuid()->toString(),
            'instructor_id' => $instructor->id,
            'category_id' => $category->id,
            'title' => 'Destroy Course',
        ]);

        $module = ModuleCourse::create([
            'id' => Str::uuid()->toString(),
            'course_id' => $course->id,
            'title' => 'Destroy Module',
        ]);

        $l0 = LessonCourse::create([
            'id' => Str::uuid()->toString(),
            'module_id' => $module->id,
            'title' => 'L0',
            'type' => 'material',
            'order' => 0,
        ]);
        $l1 = LessonCourse::create([
            'id' => Str::uuid()->toString(),
            'module_id' => $module->id,
            'title' => 'L1',
            'type' => 'material',
            'order' => 1,
        ]);
        $l2 = LessonCourse::create([
            'id' => Str::uuid()->toString(),
            'module_id' => $module->id,
            'title' => 'L2',
            'type' => 'material',
            'order' => 2,
        ]);

        LessonMaterial::create([
            'id' => Str::uuid()->toString(),
            'lesson_id' => $l0->id,
            'content' => '<p>c0</p>',
            'visible' => true,
        ]);
        LessonMaterial::create([
            'id' => Str::uuid()->toString(),
            'lesson_id' => $l1->id,
            'content' => '<p>c1</p>',
            'visible' => true,
        ]);
        LessonMaterial::create([
            'id' => Str::uuid()->toString(),
            'lesson_id' => $l2->id,
            'content' => '<p>c2</p>',
            'visible' => true,
        ]);

        // act: destroy middle lesson l1
        $controller = new LessonCourseController();
        $response = $controller->destroy($l1->id);
        $data = $response->toArray(new Request());

        // assert response success
        $this->assertArrayHasKey('status', $data);
        if (is_bool($data['status'])) {
            $this->assertTrue($data['status']);
        } else {
            $this->assertEquals('success', $data['status']);
        }
        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('Lesson deleted successfully', $data['message']);

        // assert DB changes: lesson and its material deleted
        $this->assertNull(LessonCourse::find($l1->id));
        $this->assertNull(LessonMaterial::where('lesson_id', $l1->id)->first());

        // remaining lessons reordered to 0 and 1
        $remaining = LessonCourse::where('module_id', $module->id)->orderBy('order')->get();
        $this->assertCount(2, $remaining);
        $this->assertEquals($l0->id, $remaining[0]->id);
        $this->assertEquals(0, $remaining[0]->order);
        $this->assertEquals($l2->id, $remaining[1]->id);
        $this->assertEquals(1, $remaining[1]->order);
    }

    public function test_destroy_quiz_lesson_deletes_quiz_questions_and_options()
    {
        // arrange
        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'first_name' => 'Bob',
            'last_name' => 'Quiz',
            'username' => 'bob' . Str::random(6),
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
            'instructor_id' => $instructor->id,
            'category_id' => $category->id,
            'title' => 'Quiz Destroy Course',
        ]);

        $module = ModuleCourse::create([
            'id' => Str::uuid()->toString(),
            'course_id' => $course->id,
            'title' => 'Quiz Module',
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
            'title' => 'QZ',
        ]);

        $q1 = Question::create([
            'id' => Str::uuid()->toString(),
            'quiz_id' => $quiz->id,
            'question' => '<p>Q1</p>',
            'order' => 0,
        ]);
        $q1o = AnswerOption::create([
            'id' => Str::uuid()->toString(),
            'question_id' => $q1->id,
            'answer' => 'a1',
            'is_correct' => 1,
        ]);

        $q2 = Question::create([
            'id' => Str::uuid()->toString(),
            'quiz_id' => $quiz->id,
            'question' => '<p>Q2</p>',
            'order' => 1,
        ]);
        $q2o = AnswerOption::create([
            'id' => Str::uuid()->toString(),
            'question_id' => $q2->id,
            'answer' => 'b1',
            'is_correct' => 0,
        ]);

        // act
        $controller = new LessonCourseController();
        $response = $controller->destroy($lesson->id);
        $data = $response->toArray(new Request());

        // assert response success
        $this->assertArrayHasKey('status', $data);
        if (is_bool($data['status'])) {
            $this->assertTrue($data['status']);
        } else {
            $this->assertEquals('success', $data['status']);
        }
        $this->assertEquals('Lesson deleted successfully', $data['message']);

        // assert DB deletions
        $this->assertNull(LessonCourse::find($lesson->id));
        $this->assertNull(LessonQuiz::find($quiz->id));
        $this->assertNull(Question::find($q1->id));
        $this->assertNull(AnswerOption::find($q1o->id));
        $this->assertNull(Question::find($q2->id));
        $this->assertNull(AnswerOption::find($q2o->id));
    }

    public function test_destroy_returns_not_found_for_missing_lesson()
    {
        $fakeId = Str::uuid()->toString();

        $controller = new LessonCourseController();
        $response = $controller->destroy($fakeId);
        $data = $response->toArray(new Request());

        $this->assertArrayHasKey('status', $data);
        if (is_bool($data['status'])) {
            $this->assertFalse($data['status']);
        } else {
            $this->assertNotEquals('success', $data['status']);
        }

        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('Lesson not found', $data['message']);
    }
}
