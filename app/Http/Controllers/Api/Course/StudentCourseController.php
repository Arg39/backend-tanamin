<?php

namespace App\Http\Controllers\Api\Course;

use App\Http\Controllers\Controller;
use App\Http\Resources\TableResource;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseReview;
use Illuminate\Http\Request;

class StudentCourseController extends Controller
{
    public function getStudentsEnrolledCourse(Request $request, $courseId)
    {
        try {
            $course = Course::where('id', $courseId)->where('status', 'published')->first();
            if (!$course) {
                return new TableResource(false, 'Course not found.', null, 404);
            }

            $perPage = $request->input('perPage', $request->input('per_page', 10));
            $name = $request->input('name');

            $enrollmentsQuery = CourseEnrollment::where('course_id', $courseId)
                ->whereHas('checkoutSession', function ($query) {
                    $query->where('payment_status', 'paid');
                })
                ->with(['user' => function ($q) {
                    $q->select('id', 'email', 'first_name', 'last_name', 'photo_profile');
                }]);

            if ($name) {
                $enrollmentsQuery->whereHas('user', function ($q) use ($name) {
                    $q->where('first_name', 'like', "%{$name}%")
                        ->orWhere('last_name', 'like', "%{$name}%")
                        ->orWhereRaw("CONCAT(first_name, ' ', last_name) like ?", ["%{$name}%"]);
                });
            }

            $enrollments = $enrollmentsQuery
                ->orderByDesc('created_at')
                ->paginate($perPage);

            $students = $enrollments->getCollection()->map(function ($enrollment) {
                $user = $enrollment->user;
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'full_name' => $user->full_name,
                    'photo_profile' => $user->photo_profile ? asset('storage/' . $user->photo_profile) : null,
                    'enrolled_at' => $enrollment->created_at,
                ];
            });

            $enrollments->setCollection($students);

            return new TableResource(true, 'Students retrieved successfully.', ['data' => $enrollments]);
        } catch (\Exception $e) {
            return new TableResource(false, 'An error occurred: ' . $e->getMessage(), null, 500);
        }
    }

    public function getCourseReviews(Request $request, $courseId)
    {
        try {
            $course = Course::where('id', $courseId)->where('status', 'published')->first();
            if (!$course) {
                return new TableResource(false, 'Course not found.', null, 404);
            }

            $perPage = $request->input('perPage', $request->input('per_page', 10));
            $name = $request->input('name');
            $rating = $request->input('rating');

            $reviewsQuery = CourseReview::where('course_id', $courseId)
                ->with(['user' => function ($q) {
                    $q->select('id', 'email', 'first_name', 'last_name', 'photo_profile');
                }]);

            if ($name) {
                $reviewsQuery->whereHas('user', function ($q) use ($name) {
                    $q->where('first_name', 'like', "%{$name}%")
                        ->orWhere('last_name', 'like', "%{$name}%")
                        ->orWhereRaw("CONCAT(first_name, ' ', last_name) like ?", ["%{$name}%"]);
                });
            }

            if (!is_null($rating)) {
                // Support single value or comma separated values
                if (is_array($rating)) {
                    $reviewsQuery->whereIn('rating', $rating);
                } else if (strpos($rating, ',') !== false) {
                    $ratings = array_map('intval', explode(',', $rating));
                    $reviewsQuery->whereIn('rating', $ratings);
                } else {
                    $reviewsQuery->where('rating', intval($rating));
                }
            }

            $reviews = $reviewsQuery
                ->orderByDesc('created_at')
                ->paginate($perPage);

            $data = $reviews->getCollection()->map(function ($review) {
                $user = $review->user;
                return [
                    'id' => $review->id,
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'full_name' => $user->full_name,
                        'email' => $user->email,
                        'photo_profile' => $user->photo_profile ? asset('storage/' . $user->photo_profile) : null,
                    ],
                    'rating' => $review->rating,
                    'comment' => $review->comment,
                    'created_at' => $review->created_at,
                ];
            });

            $reviews->setCollection($data);

            return new TableResource(true, 'Course reviews retrieved successfully.', ['data' => $reviews]);
        } catch (\Exception $e) {
            return new TableResource(false, 'An error occurred: ' . $e->getMessage(), null, 500);
        }
    }
}
