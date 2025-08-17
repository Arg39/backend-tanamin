<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Course;
use App\Models\CourseReview;
use App\Http\Resources\TableResource;
use App\Http\Resources\CardCourseResource;

class CardCourseController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 10);
        $courses = Course::where('status', 'published')
            ->paginate($perPage);

        $items = $courses->getCollection()->map(function ($course) {
            $data = (new CardCourseResource($course))->resolve(request());

            // Use CourseReview for ratings
            $data['average_rating'] = round(CourseReview::where('id_course', $course->id)->avg('rating') ?? 0, 2);
            $data['total_rating'] = CourseReview::where('id_course', $course->id)->count();

            return $data;
        });

        $paginatedData = [
            'data' => $courses->setCollection(collect($items))
        ];

        return new TableResource(
            true,
            'List of card courses retrieved successfully.',
            $paginatedData,
            200
        );
    }
}
