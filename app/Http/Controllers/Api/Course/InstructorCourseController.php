<?php

namespace App\Http\Controllers\Api\Course;

use App\Http\Controllers\Controller;
use App\Http\Resources\CoursePostResource;
use App\Http\Resources\ErrorResource;
use App\Http\Resources\PostResource;
use App\Http\Resources\TableResource;
use App\Models\Course;
use App\Traits\CourseFilterTrait;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

class InstructorCourseController extends Controller
{
    use CourseFilterTrait;

    // get list of courses for instructor
    public function index(Request $request)
    {
        $user = JWTAuth::user();
        if ($user->role !== 'instructor') {
            return new PostResource(false, 'Unauthorized', null);
        }

        try {
            $courses = $this->filterCourses($request, $user->id);

            $courses->getCollection()->transform(function ($course) {
                return [
                    'id' => $course->id,
                    'title' => $course->title,
                    'status' => $course->status,
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
    public function showOverview($id)
    {
        try {
            $user = JWTAuth::user();
            $course = Course::with(['category', 'instructor'])
                ->where('id', $id)
                ->where('id_instructor', $user->id)
                ->first();

            if (!$course) {
                return new PostResource(false, 'Course not found or unauthorized access', null);
            }

            return new PostResource(true, 'Course retrieved successfully', (new CoursePostResource($course))->resolve(request()));
        }
        catch (\Exception $e) {
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
