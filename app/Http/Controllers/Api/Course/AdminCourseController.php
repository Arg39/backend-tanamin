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
use App\Traits\FilteringTrait;
use App\Traits\WysiwygTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Str;

class AdminCourseController extends Controller
{
    use FilteringTrait, WysiwygTrait;

    public function index(Request $request)
    {
        try {
            $query = Course::with(['category:id,name', 'instructor:id,first_name,last_name'])
                ->select(['id', 'id_category', 'id_instructor', 'title', 'price', 'level', 'image', 'status', 'detail', 'created_at', 'updated_at']);

            if ($request->filled('search')) {
                $query->where('title', 'like', '%' . $request->input('search') . '%');
            }

            if ($request->filled('category')) {
                $query->where('id_category', $request->input('category'));
            }

            if ($request->filled('instructor')) {
                $query->where('id_instructor', $request->input('instructor'));
            }

            $courses = $this->filterQuery($query, $request, [
                'date',
                'id_instructor',
                'level',
                'status'
            ]);

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
            $course = Course::with([
                'modules.lessons.materials',
                'modules.lessons.quiz.questions'
            ])->findOrFail($id);

            // Delete WYSIWYG images in course detail
            if ($course->detail) {
                $this->deleteWysiwygImages($course->detail);
            }

            // Delete course image file if exists
            if ($course->image && Storage::disk('public')->exists($course->image)) {
                Storage::disk('public')->delete($course->image);
            }

            // Delete WYSIWYG images in all materials' content
            foreach ($course->modules as $module) {
                foreach ($module->lessons as $lesson) {
                    foreach ($lesson->materials as $material) {
                        if ($material->content) {
                            $this->deleteWysiwygImages($material->content);
                        }
                    }
                    // Delete WYSIWYG images in all questions' question field (from quizzes)
                    foreach ($lesson->quiz as $quiz) {
                        foreach ($quiz->questions as $question) {
                            if ($question->question) {
                                $this->deleteWysiwygImages($question->question);
                            }
                        }
                    }
                }
            }

            $course->delete();

            return new PostResource(true, 'Course deleted successfully', null);
        } catch (\Exception $e) {
            return new PostResource(false, 'Failed to delete course: ' . $e->getMessage(), null);
        }
    }
}
