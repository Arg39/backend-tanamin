<?php

namespace App\Http\Controllers\Api\Course;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\UpdateCourseOverviewRequest;
use App\Http\Resources\CoursePostResource;
use App\Http\Resources\PostResource;
use App\Models\Course;
use App\Traits\WysiwygTrait;
use Illuminate\Support\Facades\Storage;
use Tymon\JWTAuth\Facades\JWTAuth;

class OverviewCourseController extends Controller
{
    use WysiwygTrait;
    // getDetailCourse()
    public function show($courseId)
    {
        try {
            $course = Course::with(['category', 'instructor'])
                ->where('id', $courseId)
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

    public function update(UpdateCourseOverviewRequest $request, $courseId)
    {
        try {
            $course = Course::where('id', $courseId)
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
            $course->title = $request->has('title') ? $request->title : $course->title;
            $course->level = $request->has('level') ? $request->level : $course->level;
            $course->price = $request->has('price') ? $request->price : $course->price;
            $course->status = $request->has('status') ? $request->status : $course->status;

            // wysiwyg detail handling
            $course->detail = $this->handleWysiwygUpdate($course->detail ?? '', $request->detail ?? $course->detail);


            $course->save();
            $course->touch();

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
}
