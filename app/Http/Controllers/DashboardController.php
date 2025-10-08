<?php

namespace App\Http\Controllers;

use App\Http\Resources\InstructorResource;
use App\Http\Resources\PostResource;
use App\Models\Category;
use App\Models\ContactUsMessage;
use App\Models\Coupon;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Faq;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function getDashboardAdmin(Request $request)
    {
        try {
            // Ambil tanggal awal & akhir dari request, default: 1 bulan terakhir
            $endDate = $request->input('end_date') ?? now()->format('Y-m-d');
            $startDate = $request->input('start_date') ?? now()->subMonth()->format('Y-m-d');

            $totalCourses = Course::count();
            $totalUsers = User::where('role', '!=', 'admin')->count();
            $totalFaq = Faq::count();
            $totalCategories = Category::count();
            $totalMessages = ContactUsMessage::count();
            $totalCoupon = Coupon::count();
            $totalRevenue = CourseEnrollment::whereBetween('created_at', [
                $startDate . ' 00:00:00',
                $endDate . ' 23:59:59'
            ])->sum('price');

            $progress = [
                'new' => Course::where('status', 'new')->count(),
                'edited' => Course::where('status', 'edited')->count(),
                'awaiting_approval' => Course::where('status', 'awaiting_approval')->count(),
                'published' => Course::where('status', 'published')->count(),
            ];

            // Revenue chart (grouped by day in selected range)
            $revenueChart = CourseEnrollment::select(
                DB::raw("DATE_FORMAT(created_at, '%Y-%m-%d') as day"),
                DB::raw("SUM(price) as total")
            )
                ->whereBetween('created_at', [
                    $startDate . ' 00:00:00',
                    $endDate . ' 23:59:59'
                ])
                ->groupBy('day')
                ->orderBy('day', 'asc')
                ->get()
                ->map(function ($item) {
                    return [
                        'day' => $item->day,
                        'total' => (int) $item->total,
                    ];
                });

            $data = [
                'total_courses' => $totalCourses,
                'total_users' => $totalUsers,
                'total_messages' => $totalMessages,
                'total_faq' => $totalFaq,
                'total_categories' => $totalCategories,
                'total_coupon' => $totalCoupon,
                'total_revenue' => (int) $totalRevenue,
                'progress' => $progress,
                'revenue_chart' => $revenueChart,
                'filter' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ],
            ];

            return new PostResource(
                true,
                'Dashboard admin data retrieved successfully.',
                $data
            );
        } catch (\Exception $e) {
            return new PostResource(
                false,
                'Failed to retrieve dashboard admin data: ' . $e->getMessage(),
                null
            );
        }
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
