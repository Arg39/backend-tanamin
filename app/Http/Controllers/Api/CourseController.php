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
    public function index(Request $request)
    {
        try {
            $sortBy = $request->input('sortBy', 'title');
            $sortOrder = $request->input('sortOrder', 'asc');
            $perPage = (int) $request->input('perPage', 10);

            $courses = Course::with(['category', 'instructor', 'detail', 'reviews'])
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

            return new PostResource(true, 'Course created successfully', $course->load(['category', 'instructor', 'detail']));
        } catch (\Exception $e) {
            return new PostResource(false, 'Failed to create course: ' . $e->getMessage(), null);
        }
    }

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

    public function show($id)
    {
        try {
            $course = Course::with(['category', 'instructor', 'detail', 'reviews'])->find($id);

            if (!$course) {
                return new PostResource(false, 'Course not found', null);
            }

            return new PostResource(true, 'Course retrieved successfully', $course);
        } catch (\Exception $e) {
            return new PostResource(false, 'Failed to retrieve course: ' . $e->getMessage(), null);
        }
    }

    // INSTRUCTOR: Update course details (except title, id_category, id_instructor)
    public function update(Request $request, $id)
    {
        $user = JWTAuth::user();
        $course = Course::find($id);

        if (!$course) {
            return new PostResource(false, 'Course not found', null);
        }

        // Only admin can change title, id_category, id_instructor
        if ($user->role === 'admin') {
            $request->validate([
                'id_category' => 'sometimes|exists:categories,id',
                'id_instructor' => 'sometimes|exists:users,id',
                'title' => 'sometimes|string|max:255',
            ]);
            foreach (['id_category', 'id_instructor', 'title'] as $field) {
                if ($request->has($field)) {
                    $course->$field = $request->$field;
                }
            }
        }

        // Instructor can update other fields
        if ($user->role === 'instructor' && $course->id_instructor !== $user->id) {
            return new PostResource(false, 'Unauthorized', null);
        }

        $request->validate([
            'price' => 'sometimes|nullable|integer|min:0',
            'duration' => 'sometimes|nullable|string|max:255',
            'level' => 'sometimes|nullable|string|max:255',
            'image_video' => 'nullable|file|mimes:jpeg,png,jpg,gif,mp4,mov,avi|max:20480',
            // DetailCourse fields
            'detail' => 'nullable|string',
            'description' => 'nullable|string',
            'prerequisite' => 'nullable|string',
        ]);

        // Update file jika ada
        if ($request->hasFile('image_video')) {
            if ($course->image_video) {
                Storage::disk('public')->delete($course->image_video);
            }
            $imagePath = $request->file('image_video')->store('courses', 'public');
            $course->image_video = $imagePath;
        }

        foreach (['price', 'duration', 'level'] as $field) {
            if ($request->has($field)) {
                $course->$field = $request->$field;
            }
        }
        $course->save();

        // Update DetailCourse
        $detail = DetailCourse::find($id);
        if ($detail) {
            foreach (['detail', 'description', 'prerequisite'] as $field) {
                if ($request->has($field)) {
                    $detail->$field = $request->$field;
                }
            }
            $detail->save();
        }

        return new PostResource(true, 'Course updated successfully', $course->load(['category', 'instructor', 'detail']));
    }

    public function destroy($id)
    {
        $user = JWTAuth::user();
        if ($user->role !== 'admin') {
            return new PostResource(false, 'Unauthorized', null);
        }

        $course = Course::find($id);

        if (!$course) {
            return new PostResource(false, 'Course not found', null);
        }

        if ($course->image_video) {
            if (Storage::disk('public')->exists($course->image_video)) {
                Storage::disk('public')->delete($course->image_video);
            }
        }

        // DetailCourse will be deleted automatically by cascade
        $course->delete();

        return new PostResource(true, 'Course deleted successfully', null);
    }
}