<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Course;
use App\Models\CourseRating;
use App\Http\Resources\TableResource;
use App\Http\Resources\CardCourseResource;
use App\Models\CourseDiscount;

class CardCourseController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 10);
        $courses = Course::paginate($perPage);
    
        $now = now();
        $activeDiscount = CourseDiscount::where('is_active', true)
            ->where('start_at', '<=', $now)
            ->where('end_at', '>=', $now)
            ->first();
    
        $items = $courses->getCollection()->map(function ($course) use ($activeDiscount) {
            $data = (new CardCourseResource($course))->resolve(request());
    
            $data['average_rating'] = round(CourseRating::averageForCourse($course->id) ?? 0, 2);
            $data['total_rating'] = CourseRating::countForCourse($course->id);
    
            $data['discount'] = $activeDiscount ? $activeDiscount : null;
    
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