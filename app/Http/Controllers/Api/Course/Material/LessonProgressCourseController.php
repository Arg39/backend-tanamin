<?php

namespace App\Http\Controllers\Api\Course\Material;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\LessonProgress;
use App\Http\Resources\PostResource;
use Carbon\Carbon;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Str;

class LessonProgressCourseController extends Controller
{

    public function storeProgressLesson(Request $request)
    {
        try {
            $request->validate([
                'lesson_id' => 'required|uuid',
            ]);

            $user = JWTAuth::user();
            $userId = $user ? $user->id : null;

            if (!$userId) {
                return new PostResource(
                    false,
                    'User tidak ditemukan dari token.',
                    null
                );
            }

            $progress = LessonProgress::firstOrNew([
                'user_id'   => $userId,
                'lesson_id' => $request->lesson_id,
            ]);

            if (empty($progress->getKey())) {
                $progress->id = (string) Str::uuid();
            }

            // Only update if not completed
            if (is_null($progress->completed_at)) {
                $progress->completed_at = Carbon::now();
                $progress->save();
            }

            return new PostResource(
                true,
                'Progress berhasil disimpan.',
                $progress
            );
        } catch (\Exception $e) {
            return new PostResource(
                false,
                'Gagal menyimpan progress: ' . $e->getMessage(),
                null
            );
        }
    }
}
