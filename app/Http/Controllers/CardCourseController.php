<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Course;
use App\Models\CourseReview;
use App\Http\Resources\TableResource;
use App\Http\Resources\CardCourseResource;
use App\Http\Resources\PostResource;
use App\Models\Bookmark;
use App\Models\CourseEnrollment;
use App\Models\LessonCourse;
use App\Models\LessonProgress;
use App\Models\ModuleCourse;
use Carbon\Carbon;
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
        $bookmarkedCourseIds = [];
        if ($user) {
            $ownedCourseIds = CourseEnrollment::where('user_id', $user->id)
                ->whereHas('checkoutSession', function ($query) {
                    $query->where('payment_status', 'paid');
                })
                ->pluck('course_id')
                ->toArray();
            // Ambil course_id yang dibookmark user
            $bookmarkedCourseIds = Bookmark::where('user_id', $user->id)
                ->pluck('course_id')
                ->toArray();
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

        $items = $courses->getCollection()->map(function ($course) use ($ownedCourseIds, $bookmarkedCourseIds) {
            $resource = new CardCourseResource($course);
            $resource->additional([
                'owned' => in_array($course->id, $ownedCourseIds),
                'bookmark' => in_array($course->id, $bookmarkedCourseIds),
            ]);
            $data = $resource->resolve(request());
            $data['average_rating'] = round($course->reviews()->avg('rating') ?? 0, 2);
            $data['total_rating'] = $course->reviews()->count();
            $data['owned'] = in_array($course->id, $ownedCourseIds);
            $data['bookmark'] = in_array($course->id, $bookmarkedCourseIds);
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
            $lessonIds = LessonCourse::whereIn(
                'module_id',
                ModuleCourse::where('course_id', $course->id)->pluck('id')
            )->pluck('id');

            $totalLessons = $lessonIds->count();

            // Hitung lesson yang sudah selesai oleh user
            $completedLessons = LessonProgress::where('user_id', $user->id)
                ->whereIn('lesson_id', $lessonIds)
                ->whereNotNull('completed_at')
                ->count();

            $progress = "{$completedLessons}/{$totalLessons}";

            // Inject progress ke resource
            return (new CardCourseResource($course))->additional([
                'progress' => $progress
            ])->resolve(request());
        })->filter()->values();

        return new PostResource(true, 'My courses retrieved successfully', [
            'courses' => $courses,
        ]);
    }

    public function purchaseHistory(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return new PostResource(false, 'Unauthorized', null);
        }

        // Ambil semua enrollments yang sudah dibayar (checkoutSession.payment_status = 'paid')
        $enrollments = CourseEnrollment::with(['course', 'checkoutSession'])
            ->where('user_id', $user->id)
            ->whereHas('checkoutSession', function ($q) {
                $q->where('payment_status', 'paid');
            })
            ->orderByDesc('created_at')
            ->get();

        $history = $enrollments->map(function ($enrollment) {
            $course = $enrollment->course;
            $checkout = $enrollment->checkoutSession;

            // Format tanggal dengan Carbon dan locale Indonesia
            $tanggal = '-';
            $dateObj = $checkout ? $checkout->created_at : $enrollment->created_at;
            if ($dateObj) {
                $tanggal = Carbon::parse($dateObj)->locale('id')->translatedFormat('j F Y');
            }

            return [
                'course_id'   => $course ? $course->id : null,
                'order_id'    => $enrollment->checkout_session_id,
                'nama_course' => $course ? $course->title : '-',
                'tanggal'     => $tanggal,
                'total'       => $enrollment->price ?? 0,
                'status'      => $checkout ? $checkout->payment_status : '-',
            ];
        })->values();

        return new PostResource(true, 'Riwayat pembelian berhasil diambil.', [
            'data' => $history,
            'total' => $history->count(),
        ]);
    }

    public function bookmarkedCourses(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return new PostResource(false, 'Unauthorized', null);
        }

        // Ambil semua course_id yang sudah di-enroll (dibayar)
        $enrolledCourseIds = CourseEnrollment::where('user_id', $user->id)
            ->whereHas('checkoutSession', function ($q) {
                $q->where('payment_status', 'paid');
            })
            ->pluck('course_id')
            ->toArray();

        // Ambil bookmark yang belum di-enroll
        $bookmarkedCourses = Bookmark::with('course')
            ->where('user_id', $user->id)
            ->whereHas('course', function ($q) use ($enrolledCourseIds) {
                if (!empty($enrolledCourseIds)) {
                    $q->whereNotIn('id', $enrolledCourseIds);
                }
            })
            ->get()
            ->pluck('course')
            ->filter();

        $courses = $bookmarkedCourses->map(function ($course) {
            return (new CardCourseResource($course))->resolve(request());
        })->values();

        return new PostResource(true, 'Bookmarked courses retrieved successfully.', [
            'courses' => $courses,
        ]);
    }
}
