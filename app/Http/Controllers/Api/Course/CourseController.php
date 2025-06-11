<?php

namespace App\Http\Controllers\Api\Course;

use App\Http\Controllers\Controller;
use App\Http\Resources\ErrorResource;
use App\Http\Resources\PostResource;
use App\Http\Resources\TableResource;
use App\Models\Course;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Str;

class CourseController extends Controller
{
    // Get all courses with filtering and sorting
    public function index(Request $request)
    {
        try {
            $sortBy = $request->input('sortBy', 'title');
            $sortOrder = $request->input('sortOrder', 'asc');
            $perPage = (int) $request->input('perPage', 10);

            $search = $request->input('search');
            $category = $request->input('category');
            $instructor = $request->input('instructor');
            $dateStart = $request->input('dateStart');
            $dateEnd = $request->input('dateEnd');

            $query = Course::with([
                    'category:id,name',
                    'instructor:id,first_name,last_name'
                ])
                ->select(['id', 'id_category', 'id_instructor', 'title', 'price', 'level', 'image', 'status', 'detail', 'created_at', 'updated_at']);

            // Filtering
            if ($search) {
                $query->search($search);
            }
            if ($category) {
                $query->category($category);
            }
            if ($instructor) {
                $query->instructor($instructor);
            }
            if ($dateStart && $dateEnd) {
                $query->dateRange($dateStart, $dateEnd);
            } elseif ($dateStart) {
                $query->whereDate('created_at', '>=', $dateStart);
            } elseif ($dateEnd) {
                $query->whereDate('created_at', '<=', $dateEnd);
            }

            $courses = $query->orderBy($sortBy, $sortOrder)->paginate($perPage);

            $courses->getCollection()->transform(function ($course) {
                return [
                    'id' => $course->id,
                    'id_category' => $course->id_category,
                    'id_instructor' => $course->id_instructor,
                    'title' => $course->title,
                    'price' => $course->price,
                    'level' => $course->level,
                    'image' => $course->image ? asset('storage/' . $course->image) : null,
                    'status' => $course->status,
                    'detail' => $course->detail,
                    'category' => $course->category ? [
                        'id' => $course->category->id,
                        'name' => $course->category->name,
                    ] : null,
                    'instructor' => $course->instructor ? [
                        'id' => $course->instructor->id,
                        'first_name' => $course->instructor->first_name,
                        'last_name' => $course->instructor->last_name,
                        'full_name' => trim($course->instructor->first_name . ' ' . $course->instructor->last_name),
                    ] : null,
                    'created_at' => $course->created_at,
                    'updated_at' => $course->updated_at,
                ];
            });

            return new TableResource(true, 'Courses retrieved successfully', [
                'data' => $courses,
            ], 200);
        } catch (\Exception $e) {
            return (new ErrorResource(['message' => 'Failed to retrieve courses: ' . $e->getMessage()]))
                ->response()
                ->setStatusCode(500);
        }
    }

    // Store a new course to instructor
    public function store(Request $request)
    {
        $user = JWTAuth::user();
        if ($user->role !== 'admin') {
            return new PostResource(false, 'Unauthorized', null);
        }

        try {
            $request->validate([
                'id_category' => 'required|exists:categories,id',
                'id_instructor' => 'required|exists:users,id',
                'title' => 'required|string|max:255',
                'price' => 'nullable|numeric|min:0',
                'level' => 'nullable|in:beginner,intermediate,advance',
                'image' => 'nullable|file|mimes:jpg,jpeg,png|max:2048',
                'status' => 'nullable|in:new,edited,published',
                'detail' => 'nullable|string',
            ]);

            $courseId = (string) Str::uuid();

            $imagePath = null;
            if ($request->hasFile('image')) {
                $imagePath = $request->file('image')->store('course', 'public');
            }

            $course = Course::create([
                'id' => $courseId,
                'id_category' => $request->id_category,
                'id_instructor' => $request->id_instructor,
                'title' => $request->title,
                'price' => $request->price,
                'level' => $request->level,
                'image' => $imagePath,
                'status' => $request->status ?? 'new',
                'detail' => $request->detail,
            ]);

            // Fetch only needed fields for category and instructor
            $category = $course->category()->select('id', 'name')->first();
            $instructor = $course->instructor()->select('id', 'first_name', 'last_name')->first();

            $responseData = [
                'id' => $course->id,
                'id_category' => $course->id_category,
                'id_instructor' => $course->id_instructor,
                'title' => $course->title,
                'price' => $course->price,
                'level' => $course->level,
                'image' => $course->image ? asset('storage/' . $course->image) : null,
                'status' => $course->status,
                'detail' => $course->detail,
                'updated_at' => $course->updated_at,
                'created_at' => $course->created_at,
                'category' => $category ? [
                    'id' => $category->id,
                    'name' => $category->name,
                ] : null,
                'instructor' => $instructor ? [
                    'id' => $instructor->id,
                    'first_name' => $instructor->first_name,
                    'last_name' => $instructor->last_name,
                ] : null,
            ];

            return new PostResource(true, 'Course created successfully', $responseData);
        } catch (\Exception $e) {
            return new PostResource(false, 'Failed to create course: ' . $e->getMessage(), null);
        }
    }

    // Retrieve a specific course by ID
    public function show($id)
    {
        try {
            $course = Course::with(['category', 'instructor', 'descriptions', 'prerequisites', 'reviews'])
                ->where('id', $id)
                ->firstOrFail();

            return new PostResource(true, 'Course retrieved successfully', $course);
        } catch (\Exception $e) {
            return new PostResource(false, 'Failed to retrieve course: ' . $e->getMessage(), null);
        }
    }
}
