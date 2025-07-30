<?php

namespace App\Http\Controllers\Api\Course;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\UpdateCourseOverviewRequest;
use App\Http\Requests\UpdateCoursePriceRequest;
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
            $course = Course::where('id', $courseId)->firstOrFail();
            $validated = $request->validated();

            // Handle image upload
            if (isset($validated['image'])) {
                $newImagePath = $validated['image']->store('course', 'public');
                if ($course->image && $course->image !== $newImagePath) {
                    if (Storage::disk('public')->exists($course->image)) {
                        Storage::disk('public')->delete($course->image);
                    }
                }
                $course->image = $newImagePath;
            }

            // Update course attributes (excluding price and discount)
            if (isset($validated['title'])) {
                $course->title = $validated['title'];
            }
            if (isset($validated['level'])) {
                $course->level = $validated['level'];
            }
            if (isset($validated['status'])) {
                $course->status = $validated['status'];
            }

            // wysiwyg detail handling
            if (isset($validated['detail'])) {
                $oldDetail = $course->detail ?? '';
                $newDetail = $validated['detail'];
                $course->detail = $this->handleWysiwygUpdate($oldDetail, $newDetail);
            }

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

    public function updatePriceAndDiscount(UpdateCoursePriceRequest $request, $courseId)
    {
        try {
            $course = Course::where('id', $courseId)->firstOrFail();
            $validated = $request->validated();
    
            $discountFields = [
                'discount_type',
                'discount_value',
                'is_discount_active',
                'discount_start_at',
                'discount_end_at'
            ];
            $isDiscountAttempted = false;
            foreach ($discountFields as $field) {
                if (isset($validated[$field])) {
                    $isDiscountAttempted = true;
                    break;
                }
            }
            if (is_null($course->price) && $isDiscountAttempted && !isset($validated['price'])) {
                return new PostResource(false, 'You need to set the course price first before you can add a discount.', null);
            }
    
            // Update price
            if (isset($validated['price'])) {
                $course->price = $validated['price'];
            }
    
            // Update discount fields
            if (isset($validated['discount_type'])) {
                $course->discount_type = $validated['discount_type'];
            }
            if (isset($validated['discount_value'])) {
                $course->discount_value = $validated['discount_value'];
            }
            if (isset($validated['is_discount_active'])) {
                $course->is_discount_active = $validated['is_discount_active'];
            }
            if (isset($validated['discount_start_at'])) {
                $course->discount_start_at = $validated['discount_start_at'];
            }
            if (isset($validated['discount_end_at'])) {
                $course->discount_end_at = $validated['discount_end_at'];
            }
    
            $course->save();
            $course->touch();
    
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
    
            return new PostResource(true, 'Course price and discount updated successfully', (new CoursePostResource($course))->withExtra($dataInstructor)->resolve(request()));
    
        } catch (\Exception $e) {
            return new PostResource(false, 'Failed to update price and discount: ' . $e->getMessage(), null);
        }
    }
}
