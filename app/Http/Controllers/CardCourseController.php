<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Course;
use App\Models\CourseRating;
use App\Http\Resources\TableResource;
use App\Http\Resources\CardCourseResource;

class CardCourseController extends Controller
{
    public function index(Request $request)
    {
        // Get paginated courses
        $perPage = $request->get('per_page', 10);
        $courses = Course::paginate($perPage);
    
        // Find active global discount
        $now = now();
        $activeDiscount = \App\Models\CourseDiscount::where('is_active', true)
            ->where('start_at', '<=', $now)
            ->where('end_at', '>=', $now)
            ->first();
    
        // Transform each course using CardCourseResource and add rating info
        $items = $courses->getCollection()->map(function ($course) use ($activeDiscount) {
            $data = (new \App\Http\Resources\CardCourseResource($course))->resolve(request());
    
            // Add rating info
            $data['average_rating'] = round(\App\Models\CourseRating::averageForCourse($course->id) ?? 0, 2);
            $data['total_rating'] = \App\Models\CourseRating::countForCourse($course->id);
    
            // Attach global discount if available
            $data['discount'] = $activeDiscount ? $activeDiscount : null;
    
            return $data;
        });
    
        // Prepare paginated data for TableResource
        $paginatedData = [
            'data' => $courses->setCollection(collect($items))
        ];
    
        return new \App\Http\Resources\TableResource(
            true,
            'List of card courses retrieved successfully.',
            $paginatedData,
            200
        );
    }
}