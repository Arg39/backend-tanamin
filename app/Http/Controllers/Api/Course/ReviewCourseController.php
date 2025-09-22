<?php

namespace App\Http\Controllers\Api\Course;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Course;
use App\Models\CourseReview;
use App\Http\Resources\PostResource;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Str;

class ReviewCourseController extends Controller
{
    // Create a review for a course
    public function makeReviewCourse(Request $request, $courseId)
    {
        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        $userId = JWTAuth::user()->id;

        $course = Course::find($courseId);
        if (!$course) {
            return new PostResource(false, 'Course not found', null);
        }

        // Check if user already reviewed this course
        $existingReview = CourseReview::where('user_id', $userId)
            ->where('course_id', $courseId)
            ->first();

        if ($existingReview) {
            return new PostResource(false, 'You have already reviewed this course', null);
        }

        $review = CourseReview::create([
            'id' => (string) Str::uuid(),
            'course_id' => $courseId,
            'user_id' => $userId,
            'rating' => $request->rating,
            'comment' => $request->comment,
        ]);

        return new PostResource(true, 'Review created successfully', $review);
    }

    // View reviews for a course
    public function viewReviewCourse($courseId)
    {
        $course = Course::find($courseId);
        if (!$course) {
            return new PostResource(false, 'Course not found', null);
        }

        $userId = JWTAuth::user()->id;

        $reviews = CourseReview::where('course_id', $courseId)
            ->where('user_id', $userId)
            ->get();

        if ($reviews->isEmpty()) {
            return new PostResource(true, 'No reviews found for this course', null);
        }

        // Only send id, rating, comment
        $filteredReviews = $reviews->map(function ($review) {
            return [
                'id' => $review->id,
                'rating' => $review->rating,
                'comment' => $review->comment,
            ];
        });

        return new PostResource(true, 'Reviews fetched successfully', $filteredReviews);
    }
}
