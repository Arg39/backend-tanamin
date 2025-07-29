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
    public function show($courseId)
    {
        try {
            $course = Course::with(['category', 'instructor'])
                ->where('id', $courseId)
                ->first();
    
            if (!$course) {
                return new PostResource(false, 'Course not found or unauthorized access', null);
            }
    
            $discount = null;
            if ($course->is_discount_active) {
                if ($course->discount_type === 'percent') {
                    $discount = $course->discount_value . ' %';
                } elseif ($course->discount_type === 'nominal') {
                    $discount = 'Rp. ' . number_format($course->discount_value, 0, ',', '.');
                }
            }
    
            $dataInstructor = [
                'instructor' => $course->instructor ? [
                        'id' => $course->instructor->id,
                        'full_name' => trim($course->instructor->first_name . ' ' . $course->instructor->last_name),
                    ] : null,
                'discount' => $discount,
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
            $oldDetail = $course->detail ?? '';
            $newDetail = $request->input('detail', $oldDetail ?? '');
            $course->detail = $this->handleWysiwygUpdate($oldDetail, $newDetail);


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
