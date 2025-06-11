<?php

namespace App\Http\Controllers\Api\Course;

use App\Http\Controllers\Controller;
use App\Http\Resources\ErrorResource;
use App\Http\Resources\PostResource;
use App\Http\Resources\TableResource;
use App\Models\Course;
use App\Models\CourseDescription;
use App\Models\CoursePrerequisite;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

class InstructorCourseController extends Controller
{
    // getInstructorCourse()
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
                    'category' => $course->category ? $course->category->name : null,
                    'created_at' => $course->created_at,
                    'updated_at' => $course->updated_at,
                ];
            });

            return new TableResource(true, 'Courses retrieved successfully', [
                'data' => $courses,
            ], 200);
        } catch (\Exception $e) {
            return (new ErrorResource(['message' => 'Failed to retrieve courses: ' . $e->getMessage()]))
                ->response()
                ->setStatusCode(500);
        }
    }

    // getDetailCourse()
    public function showDetail($tab, $id)
    {
        try {
            $user = JWTAuth::user();
            if ($user->role !== 'instructor') {
                return new PostResource(false, 'Unauthorized', null);
            }

            if ($tab === 'ringkasan') {
                $course = Course::with(['category', 'instructor'])
                    ->where('id', $id)
                    ->where('id_instructor', $user->id)
                    ->firstOrFail();

                $data = [
                    'id' => $course->id,
                    'title' => $course->title,
                    'category' => $course->category ? [
                        'id' => $course->category->id,
                        'name' => $course->category->name,
                    ] : null,
                    'instructor' => $course->instructor ? [
                        'id' => $course->instructor->id,
                        'full_name' => trim($course->instructor->first_name . ' ' . $course->instructor->last_name),
                    ] : null,
                    'level' => $course->level,
                    'price' => $course->price,
                    'image' => $course->image ? asset('storage/' . $course->image) : null,
                    'detail' => $course->detail,
                    'status' => $course->status,
                    'updated_at' => $course->updated_at,
                    'created_at' => $course->created_at,
                ];

                return new PostResource(true, 'Course retrieved successfully', $data);
            }
        } catch (\Exception $e) {
            return new PostResource(false, 'Failed to retrieve course: ' . $e->getMessage(), null);
        }
    }

    // getInstructorCourseInfo()
    public function getInfo($id)
    {
        try {
            $course = Course::with([
                    'descriptions' => function ($q) {
                        $q->orderBy('created_at', 'asc');
                    },
                    'prerequisites' => function ($q) {
                        $q->orderBy('created_at', 'asc');
                    }
                ])
                ->where('id', $id)
                ->firstOrFail();

            $prerequisites = $course->prerequisites->map(function ($pre) {
                return [
                    'id' => $pre->id,
                    'content' => $pre->content,
                ];
            });
            $descriptions = $course->descriptions->map(function ($desc) {
                return [
                    'id' => $desc->id,
                    'content' => $desc->content,
                ];
            });

            return new PostResource(true, 'Course info retrieved successfully', [
                'prerequisites' => $prerequisites,
                'descriptions' => $descriptions,
            ]);
        } catch (\Exception $e) {
            return new PostResource(false, 'Failed to retrieve course info: ' . $e->getMessage(), null);
        }
    }
}
