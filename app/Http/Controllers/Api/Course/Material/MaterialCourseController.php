<?php

namespace App\Http\Controllers\Api\Material;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class MaterialCourseController extends Controller
{
    // get all materials for a course
    public function index(Request $request, $courseId, $moduleId)
    {
        // Logic to fetch materials for the specified course and module
    }
}
