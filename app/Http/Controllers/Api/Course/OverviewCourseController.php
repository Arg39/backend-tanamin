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
}
