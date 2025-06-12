<?php

namespace App\Http\Controllers\Api\Course;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\CourseAttribute;
use Illuminate\Http\Request;

class CourseAttributeController extends Controller
{
    public function index($id)
    {
        if (!$id) {
            return response()->json(['error' => 'Course ID is required'], 400);
        }
        $courseAttributes = CourseAttribute::where('id_course', $id)->get();

        return new PostResource(true, 'Data berhasil diambil.', $courseAttributes);
    }
}
