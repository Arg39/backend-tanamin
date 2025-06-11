<?php

namespace App\Http\Controllers\Api\Course;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\CourseAttribute;
use Illuminate\Http\Request;

class CourseAttributeController extends Controller
{
    public function index()
    {
        $courseAttributes = CourseAttribute::latest()->get();

        return new PostResource(true, 'Data berhasil diambil.', $courseAttributes);
    }
}
