<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CourseEnrollment;
use App\Models\CourseCheckoutSession;
use App\Http\Resources\TableResource;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class IncomeController extends Controller
{
    public function dailyIncome(Request $request)
    {
        $perPage = $request->get('perPage', 10);
        $page = $request->get('page', 1);
        $sortBy = $request->get('sortBy', 'date');
        $sortOrder = $request->get('sortOrder', 'desc');

        // Validate sortBy and sortOrder
        $allowedSortBy = ['date', 'total_income', 'total_paid_enrollments'];
        $allowedSortOrder = ['asc', 'desc'];
        if (!in_array($sortBy, $allowedSortBy)) {
            $sortBy = 'date';
        }
        if (!in_array(strtolower($sortOrder), $allowedSortOrder)) {
            $sortOrder = 'desc';
        }

        // Ambil enrollment yang checkout session-nya paid dan paid_at tidak null
        $incomeQuery = CourseEnrollment::query()
            ->select([
                DB::raw('DATE(course_checkout_sessions.paid_at) as date'),
                DB::raw('SUM(course_enrollments.price) as total_income'),
                DB::raw('COUNT(*) as total_paid_enrollments')
            ])
            ->join('course_checkout_sessions', 'course_enrollments.checkout_session_id', '=', 'course_checkout_sessions.id')
            ->where('course_checkout_sessions.payment_status', 'paid')
            ->whereNotNull('course_checkout_sessions.paid_at')
            ->groupBy(DB::raw('DATE(course_checkout_sessions.paid_at)'))
            ->orderBy($sortBy, $sortOrder);

        $paginated = $incomeQuery->paginate($perPage, ['*'], 'page', $page);

        $items = collect($paginated->items())->map(function ($item) {
            $date = Carbon::parse($item->date)->locale('id');
            $item->date = $date->translatedFormat('d F Y');
            return $item;
        });

        $paginated->setCollection($items);

        $resource = [
            'data' => $paginated
        ];

        return new TableResource(
            true,
            'Daily income retrieved successfully.',
            $resource,
            200
        );
    }
}
