<?php

namespace App\Http\Controllers;

use App\Http\Resources\InstructorResource;
use App\Http\Resources\PostResource;
use App\Models\User;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function getCategory()
    {
        // This function is intended to retrieve categories for the dashboard.
    }

    public function getCourseTopInWeek()
    {
        // This function is intended to retrieve top courses for the week for the dashboard.
    }

    public function getInstructor()
    {
        try {
            $instructors = User::where('role', 'instructor')
                ->with('detail')
                ->withCount(['courses as published_courses_count' => function ($query) {
                    $query->where('status', 'published');
                }])
                ->orderByDesc('published_courses_count')
                ->limit(8) // limit to 8 instructors
                ->get();

            $data = $instructors->map(function ($instructor) {
                return (new InstructorResource($instructor))->resolve(request());
            });

            return new PostResource(
                true,
                'List of instructors retrieved successfully.',
                $data
            );
        } catch (\Exception $e) {
            return new PostResource(
                false,
                'Failed to retrieve instructors: ' . $e->getMessage(),
                []
            );
        }
    }

    public function getCourse()
    {
        // This function is intended to retrieve courses for the dashboard.
    }
}
