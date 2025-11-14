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
use App\Models\Course;
use App\Traits\WysiwygTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LessonCourseController extends Controller
{
    use WysiwygTrait;

    // post a new lesson to a module
    public function store(Request $request, $moduleId)
    {
        try {
            $request->validate([
                'title' => 'required|string|max:255',
                'type' => 'required|string|in:material,quiz',
                'materialContent' => 'required_if:type,material',
                'quizContent' => 'required_if:type,quiz|array',
                'visible' => 'nullable|boolean',
            ]);

            $this->updateLessonOrder($moduleId);

            $order = LessonCourse::where('module_id', $moduleId)->count();

            $lessonId = (string) Str::uuid();
            $lesson = LessonCourse::create([
                'id' => $lessonId,
                'module_id' => $moduleId,
                'title' => $request->title,
                'type' => $request->type,
                'order' => $order,
            ]);

            if ($request->type === 'material') {
                $content = $this->handleWysiwygUpdate('', $request->materialContent);
                LessonMaterial::create([
                    'id' => (string) Str::uuid(),
                    'lesson_id' => $lessonId,
                    'content' => $content,
                    'visible' => $request->has('visible') ? (bool)$request->visible : false,
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
                            'is_correct' => $optIndex == $qData['correctAnswer'] ? 1 : 0,
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
                'visible' => $material->visible,
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

        $courseId = $lesson->module->course_id;
        $courseStatus = $lesson->module->course?->status;
        $data['course_id'] = $courseId;
        $data['course_status'] = $courseStatus;

        return new PostResource(true, 'Lesson detail fetched successfully', $data);
    }

    // update a lesson by ID
    public function update(Request $request, $lessonId)
    {
        try {
            $request->validate([
                'title' => 'required|string|max:255',
                'materialContent' => 'required_if:type,material',
                'quizContent' => 'required_if:type,quiz|array',
                'visible' => 'nullable|boolean',
            ]);

            $lesson = LessonCourse::with(['materials', 'quiz.questions.answerOptions'])->find($lessonId);

            if (!$lesson) {
                return new PostResource(false, 'Lesson not found', null);
            }

            // Only update title, not type
            $lesson->title = $request->title;
            $lesson->save();

            if ($lesson->type === 'material') {
                $material = $lesson->materials->first();
                if (!$material) {
                    return new PostResource(false, 'Material not found for this lesson', null);
                }
                $oldContent = $material->content ?? '';
                $newContent = $this->handleWysiwygUpdate($oldContent, $request->materialContent);
                $material->content = $newContent;
                if ($request->has('visible')) {
                    $material->visible = (bool)$request->visible;
                }
                $material->save();
            } elseif ($lesson->type === 'quiz') {
                $quiz = $lesson->quiz->first();
                if (!$quiz) {
                    return new PostResource(false, 'Quiz not found for this lesson', null);
                }

                $existingQuestions = $quiz->questions->keyBy('id');
                $requestQuestions = collect($request->quizContent)->keyBy(function ($q) {
                    return $q['id'] ?? null;
                });

                // Update or create questions
                $questionOrder = 0;
                $questionIdsToKeep = [];
                foreach ($request->quizContent as $qData) {
                    $questionId = $qData['id'] ?? null;
                    if ($questionId && $existingQuestions->has($questionId)) {
                        // Update existing question
                        $question = $existingQuestions[$questionId];
                        $oldQ = $question->question ?? '';
                        $question->question = $this->handleWysiwygUpdate($oldQ, $qData['question']);
                        $question->order = $questionOrder;
                        $question->save();
                    } else {
                        // Create new question
                        $question = Question::create([
                            'id' => (string) Str::uuid(),
                            'quiz_id' => $quiz->id,
                            'question' => $this->handleWysiwygUpdate('', $qData['question']),
                            'order' => $questionOrder,
                        ]);
                    }
                    $questionIdsToKeep[] = $question->id;

                    // Handle answer options
                    $existingOptions = $question->answerOptions->keyBy('id');
                    $optionIdsToKeep = [];
                    foreach ($qData['options'] as $optIndex => $optionText) {
                        $isCorrect = $optIndex == $qData['correctAnswer'];
                        $optionId = $qData['option_ids'][$optIndex] ?? null;
                        if ($optionId && $existingOptions->has($optionId)) {
                            $option = $existingOptions[$optionId];
                            $option->answer = $optionText;
                            $option->is_correct = $isCorrect;
                            $option->save();
                        } else {
                            $option = AnswerOption::create([
                                'id' => (string) Str::uuid(),
                                'question_id' => $question->id,
                                'answer' => $optionText,
                                'is_correct' => $isCorrect,
                            ]);
                        }
                        $optionIdsToKeep[] = $option->id;
                    }
                    // Delete removed options
                    foreach ($existingOptions as $opt) {
                        if (!in_array($opt->id, $optionIdsToKeep)) {
                            $opt->delete();
                        }
                    }
                    $questionOrder++;
                }
                // Delete removed questions (and their options)
                foreach ($existingQuestions as $q) {
                    if (!in_array($q->id, $questionIdsToKeep)) {
                        // Delete WYSIWYG images in question
                        $this->deleteWysiwygImages($q->question);
                        foreach ($q->answerOptions as $opt) {
                            $opt->delete();
                        }
                        $q->delete();
                    }
                }
            }

            return new PostResource(true, 'Lesson updated successfully', [
                'id' => $lesson->id,
                'title' => $lesson->title,
                'type' => $lesson->type,
            ]);
        } catch (ValidationException $e) {
            return new PostResource(false, 'Validation failed', $e->errors());
        } catch (\Exception $e) {
            return new PostResource(false, 'Failed to update lesson', $e->getMessage());
        }
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
