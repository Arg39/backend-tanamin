<?php

namespace App\Http\Controllers\Api\Course;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCourseRequest;
use App\Http\Resources\CoursePostResource;
use App\Http\Resources\CourseResource;
use App\Http\Resources\ErrorResource;
use App\Http\Resources\PostResource;
use App\Http\Resources\TableResource;
use App\Models\Course;
use App\Traits\CourseFilterTrait;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Str;

class AdminCourseController extends Controller
{
    use CourseFilterTrait;

    public function index(Request $request)
    {
        try {
            $courses = $this->filterCourses($request);

            return new TableResource(true, 'Courses retrieved successfully', [
                'data' => CourseResource::collection($courses),
            ]);
        } catch (\Exception $e) {
            return (new ErrorResource(['message' => 'Failed to retrieve courses: ' . $e->getMessage()]))
                ->response()->setStatusCode(500);
        }
    }


    // Store a new course to instructor
    public function store(StoreCourseRequest $request)
    {
        try {
            $courseId = (string) Str::uuid();

            $course = Course::create([
                'id' => $courseId,
                'id_category' => $request->id_category,
                'id_instructor' => $request->id_instructor,
                'title' => $request->title,
                'price' => $request->price,
                'level' => $request->level,
                'image' => null,
                'status' => $request->status ?? 'new',
                'detail' => $request->detail,
            ]);

            return new PostResource(true, 'Course created successfully', (new CoursePostResource($course))->resolve(request()));
        } catch (\Exception $e) {
            return new PostResource(false, 'Failed to create course: ' . $e->getMessage(), null);
        }
    }

    // Retrieve a specific course by ID
    public function show($id)
    {
        try {
            $course = Course::with(['category', 'instructor', 'descriptions', 'prerequisites', 'reviews'])
                ->where('id', $id)
                ->firstOrFail();

            return new PostResource(true, 'Course retrieved successfully', $course);
        } catch (\Exception $e) {
            return new PostResource(false, 'Failed to retrieve course: ' . $e->getMessage(), null);
        }
    }

    // delete a course by ID if status is new
    public function destroy($id)
    {
        try {
            $course = Course::findOrFail($id);

            if ($course->status !== 'new') {
                return new PostResource(false, 'Only courses with status "new" can be deleted', null);
            }

            $course->delete();

            return new PostResource(true, 'Course deleted successfully', null);
        } catch (\Exception $e) {
            return new PostResource(false, 'Failed to delete course: ' . $e->getMessage(), null);
        }
    }
}
