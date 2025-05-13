<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ErrorResource;
use App\Http\Resources\PostResource;
use App\Http\Resources\TableResource;
use App\Models\Course;
use App\Models\DetailCourse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Str;

class CourseController extends Controller
{
    // ADMIN: Get all courses
    public function index(Request $request)
    {
        try {
            $sortBy = $request->input('sortBy', 'title');
            $sortOrder = $request->input('sortOrder', 'asc');
            $perPage = (int) $request->input('perPage', 10);

            $courses = Course::select(['id', 'id_category', 'id_instructor', 'title', 'is_published'])
                ->orderBy($sortBy, $sortOrder)
                ->paginate($perPage);

            return new TableResource(true, 'Courses retrieved successfully', [
                'data' => $courses,
            ], 200);
        } catch (\Exception $e) {
            return (new ErrorResource(['message' => 'Failed to retrieve courses: ' . $e->getMessage()]))
                ->response()
                ->setStatusCode(500);
        }
    }

    // ADMIN: Create a new course
    public function store(Request $request)
    {
        $user = JWTAuth::user();
        if ($user->role !== 'admin') {
            return new PostResource(false, 'Unauthorized', null);
        }

        try {
            $request->validate([
                'id_category' => 'required|exists:categories,id',
                'id_instructor' => 'required|exists:users,id',
                'title' => 'required|string|max:255',
            ]);

            $courseId = (string) Str::uuid();

            $course = Course::create([
                'id' => $courseId,
                'id_category' => $request->id_category,
                'id_instructor' => $request->id_instructor,
                'title' => $request->title,
            ]);

            DetailCourse::create([
                'id' => $courseId,
            ]);

            // Fetch only needed fields for category and instructor
            $category = $course->category()->select('id', 'name')->first();
            $instructor = $course->instructor()->select('id', 'first_name', 'last_name')->first();
            $detail = $course->detail;

            $responseData = [
                'id' => $course->id,
                'id_category' => $course->id_category,
                'id_instructor' => $course->id_instructor,
                'title' => $course->title,
                'updated_at' => $course->updated_at,
                'created_at' => $course->created_at,
                'category' => $category ? [
                    'id' => $category->id,
                    'name' => $category->name,
                ] : null,
                'instructor' => $instructor ? [
                    'id' => $instructor->id,
                    'first_name' => $instructor->first_name,
                    'last_name' => $instructor->last_name,
                ] : null,
                'detail' => $detail,
            ];

            return new PostResource(true, 'Course created successfully', $responseData);
        } catch (\Exception $e) {
            return new PostResource(false, 'Failed to create course: ' . $e->getMessage(), null);
        }
    }

    // INSTRUCTOR: Get assigned courses from admin
    public function getInstructorCourses(Request $request)
    {
        $user = JWTAuth::user();
        if ($user->role !== 'instructor') {
            return new PostResource(false, 'Unauthorized', null);
        }

        try {
            $courses = Course::with(['category', 'instructor', 'detail', 'reviews'])
                ->where('id_instructor', $user->id)
                ->get();

            return new TableResource(true, 'Courses retrieved successfully', [
                'data' => $courses,
            ], 200);
        } catch (\Exception $e) {
            return (new ErrorResource(['message' => 'Failed to retrieve courses: ' . $e->getMessage()]))
                ->response()
                ->setStatusCode(500);
        }
    }

    // ADMIN & INSTRUCTOR: Get detail course by ID
    public function show($id)
    {
        try {
            $course = Course::with(['category', 'instructor', 'detail', 'reviews'])
                ->where('id', $id)
                ->firstOrFail();

            return new PostResource(true, 'Course retrieved successfully', $course);
        } catch (\Exception $e) {
            return new PostResource(false, 'Failed to retrieve course: ' . $e->getMessage(), null);
        }
    }
}