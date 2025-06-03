<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ErrorResource;
use App\Http\Resources\PostResource;
use App\Http\Resources\TableResource;
use App\Models\Course;
use App\Models\DetailCourse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Str;

class CourseController extends Controller
{
    // ADMIN: Get all courses
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
                ->select(['id', 'id_category', 'id_instructor', 'title', 'is_published', 'created_at', 'updated_at']);

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
                    'is_published' => $course->is_published,
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

    // ADMIN: Create a new course
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
            ]);

            $courseId = (string) Str::uuid();

            $course = Course::create([
                'id' => $courseId,
                'id_category' => $request->id_category,
                'id_instructor' => $request->id_instructor,
                'title' => $request->title,
                'is_published' => false,
            ]);

            DetailCourse::create([
                'id' => $courseId,
            ]);

            // Fetch only needed fields for category and instructor
            $category = $course->category()->select('id', 'name')->first();
            $instructor = $course->instructor()->select('id', 'first_name', 'last_name')->first();
            $detail = $course->detail;

            $responseData = [
                'id' => $course->id,
                'id_category' => $course->id_category,
                'id_instructor' => $course->id_instructor,
                'title' => $course->title,
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
                'detail' => $detail,
            ];

            return new PostResource(true, 'Course created successfully', $responseData);
        } catch (\Exception $e) {
            return new PostResource(false, 'Failed to create course: ' . $e->getMessage(), null);
        }
    }

    // INSTRUCTOR: Get courses by instructor
    public function getInstructorCourse(Request $request)
    {
        $user = JWTAuth::user();
        if ($user->role !== 'instructor') {
            return new PostResource(false, 'Unauthorized', null);
        }

        try {
            $sortBy = $request->input('sortBy', 'title');
            $sortOrder = $request->input('sortOrder', 'asc');
            $perPage = (int) $request->input('perPage', 10);
            $search = $request->input('search');
            $dateStart = $request->input('dateStart');
            $dateEnd = $request->input('dateEnd');

            $query = Course::with('category:id,name')
                ->where('id_instructor', $user->id)
                ->select('id', 'title', 'id_category', 'created_at', 'updated_at');

            if ($search) {
                $query->where('title', 'like', '%' . $search . '%');
            }

            if ($dateStart) {
                $query->whereDate('created_at', '>=', $dateStart);
            }

            if ($dateEnd) {
                $query->whereDate('created_at', '<=', $dateEnd);
            }

            $courses = $query->orderBy($sortBy, $sortOrder)->paginate($perPage);

            $courses->getCollection()->transform(function ($course) {
                return [
                    'id' => $course->id,
                    'title' => $course->title,
                    'category' => $course->category ? $course->category->name : null,
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

    // INSTRUCTOR: Get detail course by ID
    public function getDetailCourse($tab, $id)
    {
        try {
            $user = JWTAuth::user();
            if ($user->role !== 'instructor') {
                return new PostResource(false, 'Unauthorized', null);
            }

            if ($tab === 'ringkasan') {
                $course = Course::with(['category', 'instructor', 'detail'])
                    ->where('id', $id)
                    ->where('id_instructor', $user->id)
                    ->firstOrFail();

                $data = [
                    'id' => $course->id,
                    'title' => $course->title,
                    'category' => $course->category ? [
                        'id' => $course->category->id,
                        'name' => $course->category->name,
                    ] : null,
                    'instructor' => $course->instructor ? [
                        'id' => $course->instructor->id,
                        'full_name' => trim($course->instructor->first_name . ' ' . $course->instructor->last_name),
                    ] : null,
                    'level' => $course->level,
                    'price' => $course->price,
                    'image_video' => $course->image_video ? asset('storage/' . $course->image_video) : null,
                    'detail' => $course->detail->detail,
                    'updated_at' => $course->updated_at,
                    'created_at' => $course->created_at,
                ];

                return new PostResource(true, 'Course retrieved successfully', $data);
            } else if ($tab === 'persyaratan') {
                $preRequisite = DetailCourse::where('id', $id)->value('prerequisite');
                $data = [
                    [
                        'id' => $id,
                        'prerequisite' => $preRequisite,
                    ]
                ];
                return new TableResource(true, 'Prerequisite retrieved successfully', [
                    'data' => $data,
                ], 200);
            } else if ($tab === 'deskripsi') {
                $description = DetailCourse::where('id', $id)->value('description');
                $data = [
                    [
                        'id' => $id,
                        'description' => $description,
                    ]
                ];
                return new TableResource(true, 'Description retrieved successfully', [
                    'data' => $data,
                ], 200);
            }
        } catch (\Exception $e) {
            return new PostResource(false, 'Failed to retrieve course: ' . $e->getMessage(), null);
        }
    }

    // Helper to extract local wysiwyg image paths from HTML
    private function getImagesToDeleteFromDetail($oldDetail, $newDetail)
    {
        $extractLocalImages = function($html) {
            $images = [];
            preg_match_all('/<img[^>]+src="([^">]+)"/', $html, $matches);
            if (isset($matches[1])) {
                foreach ($matches[1] as $imgUrl) {
                    if (strpos($imgUrl, '/storage/wysiwyg/') !== false) {
                        $path = preg_replace('#^.*?/storage/#', '', $imgUrl);
                        $images[] = $path;
                    }
                }
            }
            return $images;
        };

        $oldImages = $extractLocalImages($oldDetail);
        $newImages = $extractLocalImages($newDetail);

        $toDelete = array_diff($oldImages, $newImages);

        return array_values($toDelete);
    }

    // Helper to remove <img> tags for deleted images from HTML detail
    private function removeDeletedImagesFromDetail($detailHtml, $imagesToDelete)
    {
        if (empty($imagesToDelete)) return $detailHtml;

        foreach ($imagesToDelete as $imgPath) {
            $pattern = '#<img[^>]+src="[^">]*' . preg_quote($imgPath, '#') . '[^">]*"[^>]*>#i';
            $detailHtml = preg_replace($pattern, '', $detailHtml);
        }
        return $detailHtml;
    }

    // INSTRUCTOR: UPDATE course by ID
    public function updateSummary(Request $request, $id)
    {
        $user = JWTAuth::user();
        if ($user->role !== 'instructor') {
            return new PostResource(false, 'Unauthorized', null);
        }

        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'level' => 'required|string|max:50',
                'price' => 'required|numeric|min:0',
                'image_video' => 'nullable|file|mimes:jpg,jpeg,png,mp4,mov,avi|max:20480', // 20MB max
                'detail' => 'required|string',
            ]);

            $course = Course::with(['detail'])
                ->where('id', $id)
                ->where('id_instructor', $user->id)
                ->firstOrFail();

            $oldDetail = $course->detail ? $course->detail->detail : '';
            $newDetail = $validated['detail'];

            $imagesToDelete = $this->getImagesToDeleteFromDetail($oldDetail, $newDetail);

            $newDetailCleaned = $this->removeDeletedImagesFromDetail($newDetail, $imagesToDelete);

            foreach ($imagesToDelete as $imgPath) {
                if (Storage::disk('public')->exists($imgPath)) {
                    Storage::disk('public')->delete($imgPath);
                }
            }

            if ($course->detail) {
                $course->detail->detail = $newDetailCleaned;
                $course->detail->save();
            }

            $data = [
                'id' => $course->id,
                'title' => $course->title,
                'level' => $course->level,
                'price' => $course->price,
                'image_video' => $course->image_video ? asset('storage/' . $course->image_video) : null,
                'detail' => $newDetailCleaned,
                'updated_at' => $course->updated_at,
            ];

            return new PostResource(true, 'Course summary updated successfully', $data);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return new PostResource(false, 'Validation failed', $e->errors());
        } catch (\Exception $e) {
            return new PostResource(false, 'Failed to update course summary: ' . $e->getMessage(), null);
        }
    }

    // ADMIN & INSTRUCTOR: Get detail course by ID
    public function show($id)
    {
        try {
            $course = Course::with(['category', 'instructor', 'detail', 'reviews'])
                ->where('id', $id)
                ->firstOrFail();

            return new PostResource(true, 'Course retrieved successfully', $course);
        } catch (\Exception $e) {
            return new PostResource(false, 'Failed to retrieve course: ' . $e->getMessage(), null);
        }
    }
}