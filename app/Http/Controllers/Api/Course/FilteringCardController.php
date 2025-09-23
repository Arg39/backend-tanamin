<?php

namespace App\Http\Controllers\Api\Course;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\Category;
use App\Models\User;
use App\Models\Course;
use Illuminate\Http\Request;

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
            $result['category'][$category->name] = $category->published_courses_count;
        }

        foreach ($instructors as $instructor) {
            $publishedCoursesCount = Course::where('instructor_id', $instructor->id)
                ->where('status', 'published')
                ->count();
            if ($publishedCoursesCount > 0) {
                $result['instructor'][$instructor->full_name] = $publishedCoursesCount;
            }
        }

        return new PostResource(true, 'Success get filtering category & instructor', $result);
    }
}
