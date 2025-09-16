<?php

namespace App\Http\Controllers\Api\Course\Material;

use App\Http\Controllers\Controller;
use App\Models\LessonCourse;
use App\Http\Resources\PostResource;
use App\Models\QuizAttempt;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Tymon\JWTAuth\Facades\JWTAuth;

class QuizCourseController extends Controller
{
    public function showQuiz($lessonId)
    {
        // Fetch lesson
        $lesson = LessonCourse::find($lessonId);

        if (!$lesson) {
            return new PostResource(false, 'Lesson not found', null);
        }

        // Get all quizzes for the lesson
        $quizzes = $lesson->quiz()->get();

        if ($quizzes->isEmpty()) {
            return new PostResource(false, 'Quiz not found', null);
        }

        // Get user (if authenticated)
        $user = null;
        try {
            $user = JWTAuth::user();
        } catch (\Exception $e) {
            // user not authenticated, ignore
        }

        // Check if user has attempted this lesson
        $attempt = null;
        if ($user) {
            $attempt = \App\Models\QuizAttempt::where('user_id', $user->id)
                ->where('lesson_id', $lesson->id)
                ->latest('finished_at')
                ->first();
        }

        $contentArr = [];
        $userAnswers = $attempt ? $attempt->answers : [];

        foreach ($quizzes as $quiz) {
            $questions = $quiz->questions()->get();

            foreach ($questions as $question) {
                // Get all answer options, shuffle, pick up to 4
                $answerOptions = $question->answerOptions()->get()->shuffle()->take(4)->values();

                // Prepare answer array
                $answerArr = [];
                foreach ($answerOptions as $idx => $option) {
                    $answerArr['option' . ($idx + 1)] = [
                        'id' => $option->id,
                        'answer' => $option->answer,
                    ];
                    // Only show is_correct if user has attempted
                    if ($attempt) {
                        $answerArr['option' . ($idx + 1)]['is_correct'] = (bool)$option->is_correct;
                    }
                }

                // Fill missing options with null if less than 4
                for ($i = count($answerArr) + 1; $i <= 4; $i++) {
                    $answerArr['option' . $i] = [
                        'id' => null,
                        'answer' => null,
                    ];
                    if ($attempt) {
                        $answerArr['option' . $i]['is_correct'] = null;
                    }
                }

                // User answer and correctness
                $userAnswerId = isset($userAnswers[$question->id]) ? $userAnswers[$question->id] : null;
                $isCorrect = null;
                if ($userAnswerId) {
                    $selectedOption = $question->answerOptions()->find($userAnswerId);
                    $isCorrect = $selectedOption ? (bool)$selectedOption->is_correct : null;
                }

                $contentArr[] = [
                    'id' => $question->id,
                    'quiz' => $question->question,
                    'answer' => $answerArr,
                    'user_answer' => $userAnswerId,
                    'is_correct' => $userAnswerId !== null ? $isCorrect : null,
                ];
            }
        }

        if (empty($contentArr)) {
            return new PostResource(false, 'Question not found', null);
        }

        // Build response data
        $data = [
            'id' => $lesson->id,
            'lesson_title' => $lesson->title,
            'content' => $contentArr,
            'attempt' => $attempt ? true : false,
        ];

        if ($attempt) {
            $data['score'] = $attempt->score;
        }

        return new PostResource(true, 'Quiz fetched successfully', $data);
    }

    public function submitAnswers(Request $request, $lessonId)
    {
        try {
            // 1. Validate request
            $validated = $request->validate([
                'answer' => 'required|array',
            ]);

            // 2. Get user
            $userId = JWTAuth::user()->id;
            if (!$userId) {
                return new PostResource(false, 'User not authenticated', null);
            }

            // 3. Fetch lesson
            $lesson = LessonCourse::find($lessonId);
            if (!$lesson) {
                return new PostResource(false, 'Lesson not found', null);
            }

            // 4. Fetch all questions for the lesson's quizzes
            $questions = [];
            foreach ($lesson->quiz as $quiz) {
                foreach ($quiz->questions as $question) {
                    $questions[$question->id] = $question;
                }
            }

            // 5. Flatten submitted answers
            // FE sends: [{"questionId":"optionId"}, ...]
            $submittedAnswers = [];
            foreach ($validated['answer'] as $item) {
                foreach ($item as $questionId => $optionId) {
                    $submittedAnswers[$questionId] = $optionId;
                }
            }

            // 6. Calculate score
            $score = 0;
            $total = count($questions);
            foreach ($questions as $questionId => $question) {
                if (isset($submittedAnswers[$questionId])) {
                    $selectedOptionId = $submittedAnswers[$questionId];
                    $option = $question->answerOptions()->find($selectedOptionId);
                    if ($option && $option->is_correct) {
                        $score++;
                    }
                }
            }

            // 7. Calculate percentage score
            $percentage = $total > 0 ? round(($score / $total) * 100) : 0;

            // 8. Save QuizAttempt
            $attempt = QuizAttempt::create([
                'id' => Str::uuid(),
                'user_id' => $userId,
                'lesson_id' => $lesson->id,
                'answers' => $submittedAnswers,
                'score' => $percentage, // simpan persentase
                'started_at' => Carbon::now(),
                'finished_at' => Carbon::now(),
            ]);

            // 9. Return response
            return new PostResource(true, 'Quiz submitted successfully', [
                'attempt_id' => $attempt->id,
                'score' => $percentage, // kirim persentase
                'total' => $total,
                'answers' => $submittedAnswers,
            ]);
        } catch (\Exception $e) {
            return new PostResource(false, 'Error: ' . $e->getMessage(), null);
        }
    }
}
