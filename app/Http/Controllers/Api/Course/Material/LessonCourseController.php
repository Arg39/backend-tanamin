<?php

namespace App\Http\Controllers\Api\Course\Material;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\LessonCourse;
use App\Models\ModuleCourse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LessonCourseController extends Controller
{
    // post a new lesson to a module
    public function store(Request $request, $courseId, $moduleId)
    {
        try {
            $request->validate([
                'title' => 'required|string|max:255',
                'type' => 'required|string|in:material,quiz,final_exam',
            ]);

            // Pastikan module ada
            $module = ModuleCourse::where('course_id', $courseId)->findOrFail($moduleId);

            // Perbaiki urutan lesson jika ada celah
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
