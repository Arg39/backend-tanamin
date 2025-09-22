<?php

namespace App\Http\Controllers;

use App\Http\Resources\CardCourseResource;
use App\Http\Resources\DetailCourseResource;
use App\Http\Resources\InstructorResource;
use App\Http\Resources\PostResource;
use App\Http\Resources\UserResource;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Course;
use App\Models\CourseAttribute;
use App\Models\LessonCourse;
use App\Models\LessonMaterial;
use App\Models\ModuleCourse;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Exception;

class DetailCourseController extends Controller
{
    public function showDetail($courseId)
    {
        try {
            $course = Course::where('id', $courseId)->where('status', 'published')->first();

            if (!$course) {
                return new PostResource(
                    false,
                    'Course not found.',
                    null
                );
            }

            $resource = new DetailCourseResource($course);
            $data = $resource->toArray(request());

            $access = false;
            $user = auth('api')->user();
            if ($user) {
                // Cek enrollment
                $enrollment = \App\Models\CourseEnrollment::where('user_id', $user->id)
                    ->where('course_id', $courseId)
                    ->orderByDesc('created_at')
                    ->first();

                if ($enrollment && $enrollment->payment_status === 'paid'  && $enrollment->access_status === 'active') {
                    $access = true;
                    unset($data['price']);
                }
            }
            $data['access'] = $access;

            // Coupon logic tetap
            $couponInfo = ['coupon' => false];
            if ($user) {
                $coupon_usage = \App\Models\CouponUsage::where('user_id', $user->id)
                    ->where('course_id', $courseId)
                    ->first();

                if ($coupon_usage) {
                    $now = \Carbon\Carbon::now('Asia/Makassar');
                    $coupon = \App\Models\Coupon::where('id', $coupon_usage->coupon_id)
                        ->where('is_active', 1)
                        ->where('start_at', '<=', $now)
                        ->where('end_at', '>=', $now)
                        ->orderBy('value', 'desc')
                        ->first();

                    if ($coupon) {
                        $couponInfo = [
                            'available' => true,
                            'type' => $coupon->type,
                            'coupon_id' => $coupon->id,
                            'discount' => $coupon->value,
                        ];
                    }
                } else {
                    $couponInfo = [
                        'course' => false,
                    ];
                }
            }
            $data['coupon'] = $couponInfo;

            return new PostResource(
                true,
                'Course retrieved successfully.',
                $data
            );
        } catch (Exception $e) {
            return new PostResource(
                false,
                'An error occurred: ' . $e->getMessage(),
                null
            );
        }
    }

    public function getDetailAttribute($courseId)
    {
        try {
            $attributes = CourseAttribute::where('course_id', $courseId)->get();

            if ($attributes->isEmpty()) {
                return new PostResource(
                    true,
                    'No attributes found for this course.',
                    null
                );
            }

            $grouped = [];
            foreach ($attributes as $attr) {
                $grouped[$attr->type][] = $attr->content;
            }

            return new PostResource(
                true,
                'Course attributes retrieved successfully.',
                $grouped
            );
        } catch (Exception $e) {
            return new PostResource(
                false,
                'An error occurred: ' . $e->getMessage(),
                null
            );
        }
    }

    public function getMaterialPublic($courseId)
    {
        try {
            $moduleIds = ModuleCourse::where('course_id', $courseId)->pluck('id');
            if ($moduleIds->isEmpty()) {
                return new PostResource(
                    true,
                    'No modules found for this course.',
                    null
                );
            }

            $lessons = LessonCourse::whereIn('module_id', $moduleIds)->get(['id', 'title']);
            if ($lessons->isEmpty()) {
                return new PostResource(true, 'No lessons found for this course.', null);
            }

            $result = [];
            foreach ($lessons as $lesson) {
                $materials = LessonMaterial::where('lesson_id', $lesson->id)
                    ->where('visible', true)
                    ->get(['id', 'content']);
                foreach ($materials as $material) {
                    $result[] = [
                        'id' => $material->id,
                        'title' => $lesson->title,
                        'content' => $material->content,
                    ];
                }
            }

            return new PostResource(
                true,
                'Visible materials retrieved successfully.',
                $result
            );
        } catch (\Exception $e) {
            return new PostResource(
                false,
                'An error occurred: ' . $e->getMessage(),
                null
            );
        }
    }

    public function getDetailInstructor($courseId)
    {
        try {
            $course = Course::where('id', $courseId)->where('status', 'published')->first();

            if (!$course) {
                return new PostResource(
                    false,
                    'Course not found.',
                    null
                );
            }

            $instructor = $course->instructor;

            if (!$instructor) {
                return new PostResource(
                    false,
                    'Instructor not found.',
                    null
                );
            }

            return new PostResource(
                true,
                'Profil pengguna berhasil diambil.',
                (new UserResource($instructor))->resolve(request())
            );
        } catch (\Exception $e) {
            return new PostResource(
                false,
                'An error occurred: ' . $e->getMessage(),
                null
            );
        }
    }

    public function getCoursesFromInstructorId($instructorId)
    {
        try {
            $courses = Course::where('instructor_id', $instructorId)
                ->where('status', 'published')
                ->inRandomOrder()
                ->limit(8)
                ->get();

            if ($courses->isEmpty()) {
                return new PostResource(true, 'No courses found for this instructor.', null);
            }

            $resource = CardCourseResource::collection($courses)->resolve(request());

            return new PostResource(true, 'Courses retrieved successfully.', $resource);
        } catch (\Exception $e) {
            return new PostResource(false, 'An error occurred: ' . $e->getMessage(), null);
        }
    }

    public function getOtherCoursesInstructor($courseId)
    {
        try {
            $course = Course::where('id', $courseId)->where('status', 'published')->first();

            if (!$course) {
                return new PostResource(true, 'Course not found.', null);
            }

            $instructorId = $course->instructor_id;

            $courses = Course::where('instructor_id', $instructorId)
                ->where('status', 'published')
                ->where('id', '!=', $courseId)
                ->inRandomOrder()
                ->limit(8)
                ->get();

            if ($courses->isEmpty()) {
                return new PostResource(true, 'No other courses found for this instructor.', null);
            }

            $resource = CardCourseResource::collection($courses)->resolve(request());

            return new PostResource(true, 'Other courses retrieved successfully.', $resource);
        } catch (\Exception $e) {
            return new PostResource(false, 'An error occurred: ' . $e->getMessage(), null);
        }
    }
}
