<?php

namespace App\Http\Controllers\Api\Course\Material;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\ModuleCourse;
use Illuminate\Http\Request;

class ModuleCourseController extends Controller
{
    // get all modules for a course
    public function index(Request $request, $courseId)
    {
        try {
            $modules = ModuleCourse::where('course_id', $courseId)
                ->with('lessons')
                ->orderBy('order', 'asc')
                ->get();

            return new PostResource(true, 'Modules fetched successfully', $modules);
        } catch (\Exception $e) {
            return new PostResource(false, 'Failed to fetch modules', $e->getMessage());
        }
    }
}
