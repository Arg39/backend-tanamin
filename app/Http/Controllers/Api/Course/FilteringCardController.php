<?php

namespace App\Http\Controllers\Api\Course;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\Category;
use App\Models\User;
use App\Models\Course;
use App\Models\CourseReview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FilteringCardController extends Controller
{
    public function getFilteringCard()
    {
        // Get categories with count of published courses
        $categories = Category::withCount([
            'courses as published_courses_count' => function ($query) {
                $query->where('status', 'published');
            }
        ])->get();

        // Get instructors with role 'instructor'
        $instructors = User::where('role', 'instructor')->get();

        $result = [
            'category' => [],
            'instructor' => [],
        ];

        foreach ($categories as $category) {
            $result['category'][] = [
                'id' => $category->id,
                'name' => $category->name,
                'published_courses_count' => $category->published_courses_count,
            ];
        }

        foreach ($instructors as $instructor) {
            $publishedCoursesCount = Course::where('instructor_id', $instructor->id)
                ->where('status', 'published')
                ->count();
            if ($publishedCoursesCount > 0) {
                $result['instructor'][] = [
                    'id' => $instructor->id,
                    'full_name' => $instructor->full_name,
                    'published_courses_count' => $publishedCoursesCount,
                ];
            }
        }

        // Rating: count of courses by rating value (1-5)
        $ratingCounts = CourseReview::select('rating', DB::raw('count(*) as total'))
            ->groupBy('rating')
            ->pluck('total', 'rating')
            ->toArray();

        // Build rating array as array of objects with rating and total
        $ratings = [];
        foreach (range(5, 1) as $rate) {
            $ratings[] = [
                'rating' => $rate,
                'total' => isset($ratingCounts[$rate]) ? $ratingCounts[$rate] : 0,
            ];
        }
        $result['rating'] = $ratings;
        // Price: count of free and paid courses
        $freeCount = Course::where(function ($q) {
            $q->whereNull('price')->orWhere('price', 0);
        })->count();
        $paidCount = Course::where('price', '>', 0)->count();
        $result['price'][] = [
            'free' => $freeCount,
            'paid' => $paidCount,
        ];

        // Level: count of courses by level
        $levelCounts = Course::select('level', DB::raw('count(*) as total'))
            ->whereNotNull('level')
            ->groupBy('level')
            ->pluck('total', 'level')
            ->toArray();

        $levels = [
            'beginner' => isset($levelCounts['beginner']) ? $levelCounts['beginner'] : 0,
            'intermediate' => isset($levelCounts['intermediate']) ? $levelCounts['intermediate'] : 0,
            'advance' => isset($levelCounts['advance']) ? $levelCounts['advance'] : 0,
        ];
        $result['level'] = $levels;

        return new PostResource(true, 'Success get filtering category & instructor', $result);
    }
}
