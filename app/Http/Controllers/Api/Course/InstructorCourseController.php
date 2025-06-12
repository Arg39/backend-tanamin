<?php

namespace App\Http\Controllers\Api\Course;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateCourseOverviewRequest;
use App\Http\Resources\CoursePostResource;
use App\Http\Resources\ErrorResource;
use App\Http\Resources\PostResource;
use App\Http\Resources\TableResource;
use App\Models\Course;
use App\Traits\CourseFilterTrait;
use App\Traits\WysiwygTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Tymon\JWTAuth\Facades\JWTAuth;

class InstructorCourseController extends Controller
{
    use CourseFilterTrait;
    use WysiwygTrait;

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

            $dataInstructor = [
                'instructor' => $course->instructor ? [
                        'id' => $course->instructor->id,
                        'full_name' => trim($course->instructor->first_name . ' ' . $course->instructor->last_name),
                    ] : null,
            ];

            return new PostResource(true, 'Course retrieved successfully', (new CoursePostResource($course))->withExtra($dataInstructor)->resolve(request()));
        }
        catch (\Exception $e) {
            return new PostResource(false, 'Failed to retrieve course: ' . $e->getMessage(), null);
        }
    }

    public function updateOverview(UpdateCourseOverviewRequest $request, $id)
    {
        $user = JWTAuth::user();
        try {
            $course = Course::where('id', $id)
                ->where('id_instructor', $user->id)
                ->firstOrFail();

            // Handle image upload
            if ($request->hasFile('image')) {
                $newImagePath = $request->file('image')->store('course', 'public');

                if ($course->image && $course->image !== $newImagePath) {
                    if (Storage::disk('public')->exists($course->image)) {
                        Storage::disk('public')->delete($course->image);
                    }
                }

                $course->image = $newImagePath;
            }

            // Update course attributes
            $course->title = $request->title;
            $course->level = $request->level;
            $course->price = $request->price;

            // wysiwyg detail handling
            $oldDetail = $course->detail ?? '';
            $newDetail = $request->detail;
            $imagesToDelete = $this->getImagesToDeleteFromDetail($oldDetail, $newDetail);
            $newDetailCleaned = $this->removeDeletedImagesFromDetail($newDetail, $imagesToDelete);
            foreach ($imagesToDelete as $imgPath) {
                if (Storage::disk('public')->exists($imgPath)) {
                    Storage::disk('public')->delete($imgPath);
                }
            }

            $course->detail = $newDetailCleaned;
            $course->save();

            $dataInstructor = [
                'instructor' => $course->instructor ? [
                        'id' => $course->instructor->id,
                        'full_name' => trim($course->instructor->first_name . ' ' . $course->instructor->last_name),
                    ] : null,
            ];

            return new PostResource(true, 'Course summary updated successfully', (new CoursePostResource($course))->withExtra($dataInstructor)->resolve(request()));

        } catch (\Exception $e) {
            return new PostResource(false, 'Failed to update course summary: ' . $e->getMessage(), null);
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
