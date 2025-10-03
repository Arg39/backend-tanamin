<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Course;
use App\Models\CourseReview;
use App\Http\Resources\TableResource;
use App\Http\Resources\CardCourseResource;
use App\Http\Resources\PostResource;
use App\Models\CourseEnrollment;
use Illuminate\Pagination\LengthAwarePaginator;

class CardCourseController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 12);
        $search = $request->get('search');
        $categories = $request->get('categories', []);
        $instructors = $request->get('instructor', []);
        $ratings = $request->get('ratings', []);
        $price = $request->get('price', 'all');
        $level = $request->get('level', 'all');

        $query = Course::where('status', 'published');

        $user = auth('api')->user();
        $ownedCourseIds = [];
        if ($user) {
            // Ambil course_id dari course_enrollments yang sudah dibayar (payment_status 'paid' di course_checkout_sessions)
            $ownedCourseIds = CourseEnrollment::where('user_id', $user->id)
                ->whereHas('checkoutSession', function ($query) {
                    $query->where('payment_status', 'paid');
                })
                ->pluck('course_id')
                ->toArray();
            // Tidak perlu exclude owned courses dari query
        }

        // Search
        if ($search) {
            $query->search($search);
        }

        // Categories filter
        if (!empty($categories)) {
            $query->whereIn('category_id', $categories);
        }

        // Instructor filter
        if (!empty($instructors)) {
            $query->whereIn('instructor_id', $instructors);
        }

        // Level filter
        if ($level && $level !== 'all') {
            $query->where('level', $level);
        }

        // Price filter
        if ($price && $price !== 'all') {
            if ($price === 'free') {
                $query->where(function ($q) {
                    $q->where('price', 0)->orWhereNull('price');
                });
            } elseif ($price === 'paid') {
                $query->where('price', '>', 0);
            }
        }

        // Ratings filter (average rating)
        if (!empty($ratings)) {
            $query->whereHas('reviews', function ($q) use ($ratings) {
                $q->select('course_id')
                    ->groupBy('course_id')
                    ->havingRaw('ROUND(AVG(rating)) IN (' . implode(',', array_map('intval', $ratings)) . ')');
            });
        }

        $courses = $query->paginate($perPage);

        $items = $courses->getCollection()->map(function ($course) use ($ownedCourseIds) {
            $data = (new CardCourseResource($course))->resolve(request());
            $data['average_rating'] = round($course->reviews()->avg('rating') ?? 0, 2);
            $data['total_rating'] = $course->reviews()->count();
            $data['owned'] = in_array($course->id, $ownedCourseIds);
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

    public function getBestCourses(Request $request)
    {
        $perPage = $request->get('per_page', 10);
        $page = $request->get('page', 1);

        // Step 1: Get top 10 course_ids by enrollment count (hanya yang checkoutSession.payment_status = 'paid')
        $topCourseIds = CourseEnrollment::whereHas('checkoutSession', function ($query) {
            $query->where('payment_status', 'paid');
        })
            ->select('course_id')
            ->groupBy('course_id')
            ->orderByRaw('COUNT(*) DESC')
            ->limit(10)
            ->pluck('course_id')
            ->toArray();

        // Step 2: If less than 10, fill with random published courses
        $needed = 10 - count($topCourseIds);
        if ($needed > 0) {
            $randomCourseIds = Course::where('status', 'published')
                ->whereNotIn('id', $topCourseIds)
                ->inRandomOrder()
                ->limit($needed)
                ->pluck('id')
                ->toArray();
            $topCourseIds = array_merge($topCourseIds, $randomCourseIds);
        }

        // Step 3: Fetch courses
        $courses = Course::whereIn('id', $topCourseIds)
            ->where('status', 'published')
            ->get();

        // Step 4: Map to resource
        $items = $courses->map(function ($course) {
            $data = (new CardCourseResource($course))->resolve(request());
            $data['average_rating'] = round($course->reviews()->avg('rating') ?? 0, 2);
            $data['total_rating'] = $course->reviews()->count();
            return $data;
        })->values()->all();

        // Step 4.5: Paginate manually
        $total = count($items);
        $pagedItems = array_slice($items, ($page - 1) * $perPage, $perPage);
        $paginator = new LengthAwarePaginator($pagedItems, $total, $perPage, $page, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);

        // Step 5: Return TableResource
        return new TableResource(
            true,
            'List of best courses retrieved successfully.',
            ['data' => $paginator],
            200
        );
    }

    public function myCourses(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return new PostResource(false, 'Unauthorized', null);
        }

        $filter = $request->get('filter', 'enrolled'); // default: enrolled

        // Query enrollments with paid checkout session
        $enrollmentsQuery = CourseEnrollment::with(['course'])
            ->where('user_id', $user->id)
            ->whereHas('checkoutSession', function ($q) {
                $q->where('payment_status', 'paid');
            });

        // Filter by access_status if needed
        if ($filter === 'ongoing') {
            $enrollmentsQuery->where('access_status', 'active');
        } elseif ($filter === 'completed') {
            $enrollmentsQuery->where('access_status', 'completed');
        }
        // 'enrolled' = all (default, no extra filter)

        $enrollments = $enrollmentsQuery->orderByDesc('created_at')->get();

        // Map to CardCourseResource with progress
        $courses = $enrollments->map(function ($enrollment) use ($user) {
            $course = $enrollment->course;
            if (!$course) return null;

            // Ambil semua lesson id pada course
            $lessonIds = \App\Models\LessonCourse::whereIn(
                'module_id',
                \App\Models\ModuleCourse::where('course_id', $course->id)->pluck('id')
            )->pluck('id');

            $totalLessons = $lessonIds->count();

            // Hitung lesson yang sudah selesai oleh user
            $completedLessons = \App\Models\LessonProgress::where('user_id', $user->id)
                ->whereIn('lesson_id', $lessonIds)
                ->whereNotNull('completed_at')
                ->count();

            $progress = "{$completedLessons}/{$totalLessons}";

            // Inject progress ke resource
            return (new \App\Http\Resources\CardCourseResource($course))->additional([
                'progress' => $progress
            ])->resolve(request());
        })->filter()->values();

        return new PostResource(true, 'My courses retrieved successfully', [
            'courses' => $courses,
        ]);
    }
}
