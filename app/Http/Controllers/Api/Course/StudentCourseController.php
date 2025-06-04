<?php

namespace App\Http\Controllers\Api\Course;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Http\Resources\TableResource;
use App\Models\Course;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

class StudentCourseController extends Controller
{
    // Get published courses for students
    public function index(Request $request)
    {
        $user = JWTAuth::user();
        if ($user->role !== 'student') {
            return new PostResource(false, 'Unauthorized', null);
        }

        try {
            $sortBy = $request->input('sortBy', 'title');
            $sortOrder = $request->input('sortOrder', 'asc');
            $perPage = (int) $request->input('perPage', 10);
            $search = $request->input('search');
            $category = $request->input('category');
            $dateStart = $request->input('dateStart');
            $dateEnd = $request->input('dateEnd');

            $query = Course::with(['category:id,name', 'instructor:id,first_name,last_name'])
                ->where('status', 'published')
                ->select('id', 'title', 'id_category', 'id_instructor', 'price', 'level', 'image', 'status', 'detail', 'created_at', 'updated_at');

            if ($search) {
                $query->where('title', 'like', "%$search%");
            }
            if ($category) {
                $query->where('id_category', $category);
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
                    'detail' => $course->detail,
                    'category' => $course->category ? ['id' => $course->category->id, 'name' => $course->category->name] : null,
                    'instructor' => $course->instructor ? [
                        'id' => $course->instructor->id,
                        'first_name' => $course->instructor->first_name,
                        'last_name' => $course->instructor->last_name,
                        'full_name' => trim($course->instructor->first_name . ' ' . $course->instructor->last_name),
                    ] : null,
                    'created_at' => $course->created_at,
                    'updated_at' => $course->updated_at,
                ];
            });

            return new TableResource(true, 'Courses for students retrieved', ['data' => $courses], 200);
        } catch (\Exception $e) {
            return new PostResource(false, 'Failed to retrieve courses: ' . $e->getMessage(), null);
        }
    }

    // Get detail of a published course for student
    public function show($id)
    {
        $user = JWTAuth::user();
        if ($user->role !== 'student') {
            return new PostResource(false, 'Unauthorized', null);
        }

        try {
            $course = Course::with(['category:id,name', 'instructor:id,first_name,last_name'])
                ->where('id', $id)
                ->where('status', 'published')
                ->first();

            if (!$course) {
                return new PostResource(false, 'Course not found or not published', null);
            }

            return new PostResource(true, 'Course detail retrieved', [
                'id' => $course->id,
                'title' => $course->title,
                'price' => $course->price,
                'level' => $course->level,
                'image' => $course->image ? asset('storage/' . $course->image) : null,
                'detail' => $course->detail,
                'category' => $course->category ? ['id' => $course->category->id, 'name' => $course->category->name] : null,
                'instructor' => $course->instructor ? [
                    'id' => $course->instructor->id,
                    'first_name' => $course->instructor->first_name,
                    'last_name' => $course->instructor->last_name,
                    'full_name' => trim($course->instructor->first_name . ' ' . $course->instructor->last_name),
                ] : null,
                'created_at' => $course->created_at,
                'updated_at' => $course->updated_at,
            ]);
        } catch (\Exception $e) {
            return new PostResource(false, 'Failed to retrieve course detail: ' . $e->getMessage(), null);
        }
    }
}
