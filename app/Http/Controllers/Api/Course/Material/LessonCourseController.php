<?php

namespace App\Http\Controllers\Api\Course\Material;

use App\Http\Controllers\Controller;
use App\Models\LessonCourse;
use Illuminate\Http\Request;

class LessonCourseController extends Controller
{
    // get all lessons by module id
    public function index(Request $request, $courseId, $moduleId)
    {
        try {
            $lessons = LessonCourse::where('module_id', $moduleId)
                ->orderBy('order', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Lessons fetched successfully',
                'data' => $lessons,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch lessons',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
