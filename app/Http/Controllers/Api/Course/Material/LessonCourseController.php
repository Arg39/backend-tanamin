<?php

namespace App\Http\Controllers\Api\Course\Material;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\LessonCourse;
use App\Models\LessonMaterial;
use App\Models\LessonQuiz;
use App\Models\ModuleCourse;
use App\Models\Question;
use App\Models\AnswerOption;
use App\Traits\WysiwygTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LessonCourseController extends Controller
{
    use WysiwygTrait;

    // post a new lesson to a module
    public function store(Request $request, $moduleId)
    {
        try {
            $request->validate([
                'title' => 'required|string|max:255',
                'type' => 'required|string|in:material,quiz,final_exam',
                'materialContent' => 'required_if:type,material',
                'quizContent' => 'required_if:type,quiz|array',
            ]);

            $this->updateLessonOrder($moduleId);

            // Tentukan order untuk lesson baru
            $order = LessonCourse::where('module_id', $moduleId)->count();

            // Buat lesson baru
            $lessonId = (string) Str::uuid();
            $lesson = LessonCourse::create([
                'id' => $lessonId,
                'module_id' => $moduleId,
                'title' => $request->title,
                'type' => $request->type,
                'order' => $order,
            ]);

            if ($request->type === 'material') {
                // Handle wysiwyg content
                $content = $this->handleWysiwygUpdate('', $request->materialContent);
                LessonMaterial::create([
                    'id' => (string) Str::uuid(),
                    'lesson_id' => $lessonId,
                    'content' => $content,
                ]);
            } else if ($request->type === 'quiz') {
                $quiz = LessonQuiz::create([
                    'id' => (string) Str::uuid(),
                    'lesson_id' => $lessonId,
                    'title' => $request->title,
                ]);

                foreach ($request->quizContent as $qIndex => $qData) {
                    $question = Question::create([
                        'id' => (string) Str::uuid(),
                        'quiz_id' => $quiz->id,
                        'question' => $this->handleWysiwygUpdate('', $qData['question']),
                        'order' => $qIndex,
                    ]);

                    foreach ($qData['options'] as $optIndex => $optionText) {
                        AnswerOption::create([
                            'id' => (string) Str::uuid(),
                            'question_id' => $question->id,
                            'answer' => $optionText,
                            'is_correct' => $optIndex == $qData['correctAnswer'],
                        ]);
                    }
                }
            }

            return new PostResource(true, 'Lesson created successfully', [
                'id' => $lesson->id,
                'title' => $lesson->title,
                'type' => $lesson->type,
                'order' => $lesson->order,
            ]);
        } catch (\Exception $e) {
            return new PostResource(false, 'Failed to create lesson', $e->getMessage());
        }
    }

    // update lesson order by drag and drop (single move, can move between modules)
    public function updateByOrder(Request $request)
    {
        try {
            $request->validate([
                'id' => 'required|string',
                'moveToModule' => 'required|string',
                'order' => 'required|integer|min:0',
            ]);

            $lesson = LessonCourse::findOrFail($request->id);

            $fromModuleId = $lesson->module_id;
            $toModuleId = $request->moveToModule;

            if ($fromModuleId !== $toModuleId) {
                $lesson->module_id = $toModuleId;
                $lesson->save();
            }

            $lessons = LessonCourse::where('module_id', $toModuleId)
                ->where('id', '!=', $lesson->id)
                ->orderBy('order', 'asc')
                ->get()
                ->values()
                ->all();

            $newOrder = min($request->order, count($lessons));
            array_splice($lessons, $newOrder, 0, [$lesson]);

            foreach ($lessons as $index => $l) {
                $l->update(['order' => $index]);
            }

            if ($fromModuleId !== $toModuleId) {
                $this->updateLessonOrder($fromModuleId);
            }

            return new PostResource(true, 'Lesson order updated successfully', [
                'id' => $lesson->id,
                'module_id' => $lesson->module_id,
                'order' => $lesson->order,
            ]);
        } catch (\Exception $e) {
            return new PostResource(false, 'Failed to update lesson order', $e->getMessage());
        }
    }

    // view lesson details by ID
    public function show($lessonId)
    {
        $lesson = LessonCourse::with(['module', 'materials', 'quiz.questions.answerOptions'])->find($lessonId);

        if (!$lesson) {
            return new PostResource(false, 'Lesson not found', null);
        }

        $data = [
            'id' => $lesson->id,
            'module_title' => $lesson->module->title,
            'lesson_title' => $lesson->title,
            'type' => $lesson->type,
        ];

        if ($lesson->type === 'material') {
            $material = $lesson->materials->first();
            $data['content'] = $material ? [
                'id' => $material->id,
                'material' => $material->content,
            ] : null;
        } elseif ($lesson->type === 'quiz') {
            $quiz = $lesson->quiz->first();
            if ($quiz) {
                $content = [];
                foreach ($quiz->questions->sortBy('order') as $question) {
                    $options = [];
                    foreach ($question->answerOptions as $option) {
                        $options[] = [
                            'answer' => $option->answer,
                            'is_correct' => $option->is_correct,
                        ];
                    }
                    $content[] = [
                        'id' => $question->id,
                        'question' => $question->question,
                        'options' => $options,
                    ];
                }
        
                $data['content'] = $content;
            } else {
                $data['quiz'] = null;
            }
        }

        return new PostResource(true, 'Lesson detail fetched successfully', $data);
    }

    // delete a lesson by ID
    public function destroy($lessonId)
    {
        $lesson = LessonCourse::with(['materials', 'quiz.questions.answerOptions'])->find($lessonId);

        if (!$lesson) {
            return new PostResource(false, 'Lesson not found', null);
        }

        $moduleId = $lesson->module_id;

        if ($lesson->type === 'material') {
            foreach ($lesson->materials as $material) {
                $this->deleteWysiwygImages($material->content);
                $material->delete();
            }
        } elseif ($lesson->type === 'quiz') {
            foreach ($lesson->quiz as $quiz) {
                foreach ($quiz->questions as $question) {
                    $this->deleteWysiwygImages($question->question);
                    foreach ($question->answerOptions as $option) {
                        $option->delete();
                    }
                    $question->delete();
                }
                $quiz->delete();
            }
        }

        $lesson->delete();

        $this->updateLessonOrder($moduleId);

        return new PostResource(true, 'Lesson deleted successfully', null);
    }

    // Update the order of lessons after deletion or insertion
    private function updateLessonOrder($moduleId)
    {
        $lessons = LessonCourse::where('module_id', $moduleId)
            ->orderBy('order', 'asc')
            ->get();

        $currentOrder = 0;
        foreach ($lessons as $lesson) {
            if ($lesson->order != $currentOrder) {
                $lesson->update(['order' => $currentOrder]);
            }
            $currentOrder++;
        }
    }
}