<?php

namespace App\Http\Controllers;

use App\Http\Resources\DetailCourseResource;
use App\Http\Resources\PostResource;
use App\Models\Course;
use App\Models\CourseAttribute;
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

            return new PostResource(
                true,
                'Course retrieved successfully.',
                $resource->toArray(request())
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
            $attributes = CourseAttribute::where('id_course', $courseId)->get();
    
            if ($attributes->isEmpty()) {
                return new PostResource(
                    false,
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

    public function getDetailInstructor($courseId)
    {
        // This method is not implemented in the provided code snippet.
    }
}
