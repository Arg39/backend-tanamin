<?php

namespace App\Http\Controllers\Api\Course;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Http\Resources\TableResource;
use App\Models\Course;
use App\Models\CourseDescription;
use App\Models\CoursePrerequisite;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class InstructorCourseController extends Controller
{
    // Get courses by instructor
    public function index(Request $request)
    {
        $user = JWTAuth::user();
        if ($user->role !== 'instructor') {
            return new PostResource(false, 'Unauthorized', null);
        }

        try {
            $sortBy = $request->input('sortBy', 'title');
            $sortOrder = $request->input('sortOrder', 'asc');
            $perPage = (int) $request->input('perPage', 10);
            $search = $request->input('search');
            $dateStart = $request->input('dateStart');
            $dateEnd = $request->input('dateEnd');

            $query = Course::with('category:id,name')
                ->where('id_instructor', $user->id)
                ->select('id', 'title', 'id_category', 'price', 'level', 'image', 'status', 'detail', 'created_at', 'updated_at');

            if ($search) {
                $query->where('title', 'like', '%' . $search . '%');
            }

            if ($dateStart) {
                $query->whereDate('created_at', '>=', $dateStart);
            }

            if ($dateEnd) {
                $query->whereDate('created_at', '<=', $dateEnd);
            }

            $courses = $query->orderBy($sortBy, $sortOrder)->paginate($perPage);

            $courses->getCollection()->transform(function ($course) {
                return [
                    'id' => $course->id,
                    'title' => $course->title,
                    'price' => $course->price,
                    'level' => $course->level,
                    'image' => $course->image ? asset('storage/' . $course->image) : null,
                    'status' => $course->status,
                    'detail' => $course->detail,
                    'category' => $course->category ? ['id' => $course->category->id, 'name' => $course->category->name] : null,
                    'created_at' => $course->created_at,
                    'updated_at' => $course->updated_at,
                ];
            });

            return new TableResource(true, 'Instructor courses retrieved', ['data' => $courses], 200);
        } catch (\Exception $e) {
            return new PostResource(false, 'Failed to retrieve courses: ' . $e->getMessage(), null);
        }
    }

    // Update course detail by instructor
    public function update(Request $request, $id)
    {
        $user = JWTAuth::user();
        if ($user->role !== 'instructor') {
            return new PostResource(false, 'Unauthorized', null);
        }

        try {
            $course = Course::where('id', $id)->where('id_instructor', $user->id)->first();
            if (!$course) {
                return new PostResource(false, 'Course not found or not yours', null);
            }

            $request->validate([
                'title' => 'sometimes|required|string|max:255',
                'price' => 'nullable|numeric|min:0',
                'level' => 'nullable|in:beginner,intermediate,advance',
                'image' => 'nullable|file|mimes:jpg,jpeg,png|max:2048',
                'status' => 'nullable|in:new,edited,published',
                'detail' => 'nullable|string',
            ]);

            if ($request->hasFile('image')) {
                if ($course->image && Storage::disk('public')->exists($course->image)) {
                    Storage::disk('public')->delete($course->image);
                }
                $course->image = $request->file('image')->store('course', 'public');
            }

            $course->fill($request->only(['title', 'price', 'level', 'status', 'detail']));
            $course->save();

            return new PostResource(true, 'Course updated successfully', [
                'id' => $course->id,
                'title' => $course->title,
                'price' => $course->price,
                'level' => $course->level,
                'image' => $course->image ? asset('storage/' . $course->image) : null,
                'status' => $course->status,
                'detail' => $course->detail,
                'updated_at' => $course->updated_at,
            ]);
        } catch (\Exception $e) {
            return new PostResource(false, 'Failed to update course: ' . $e->getMessage(), null);
        }
    }

    // Add prerequisite by instructor
    public function addPrerequisite(Request $request, $courseId)
    {
        $user = JWTAuth::user();
        if ($user->role !== 'instructor') {
            return new PostResource(false, 'Unauthorized', null);
        }

        try {
            $course = Course::where('id', $courseId)->where('id_instructor', $user->id)->first();
            if (!$course) {
                return new PostResource(false, 'Course not found or not yours', null);
            }

            $request->validate([
                'prerequisite' => 'required|string|max:255',
            ]);

            $prereq = new CoursePrerequisite();
            $prereq->id_course = $course->id;
            $prereq->prerequisite = $request->prerequisite;
            $prereq->save();

            return new PostResource(true, 'Prerequisite added successfully', ['prerequisite' => $prereq]);
        } catch (\Exception $e) {
            return new PostResource(false, 'Failed to add prerequisite: ' . $e->getMessage(), null);
        }
    }

    // Add course description by instructor
    public function addDescription(Request $request, $courseId)
    {
        $user = JWTAuth::user();
        if ($user->role !== 'instructor') {
            return new PostResource(false, 'Unauthorized', null);
        }

        try {
            $course = Course::where('id', $courseId)->where('id_instructor', $user->id)->first();
            if (!$course) {
                return new PostResource(false, 'Course not found or not yours', null);
            }

            $request->validate([
                'description' => 'required|string',
            ]);

            $desc = new CourseDescription();
            $desc->id_course = $course->id;
            $desc->description = $request->description;
            $desc->save();

            return new PostResource(true, 'Description added successfully', ['description' => $desc]);
        } catch (\Exception $e) {
            return new PostResource(false, 'Failed to add description: ' . $e->getMessage(), null);
        }
    }
}
