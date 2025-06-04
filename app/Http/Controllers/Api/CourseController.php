<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ErrorResource;
use App\Http\Resources\PostResource;
use App\Http\Resources\TableResource;
use App\Models\Course;
use App\Models\CourseDescription;
use App\Models\CoursePrerequisite;
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
                ->select('id', 'title', 'id_category', 'price', 'level', 'image', 'status', 'detail', 'created_at', 'updated_at');

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
                    'price' => $course->price,
                    'level' => $course->level,
                    'image' => $course->image ? asset('storage/' . $course->image) : null,
                    'status' => $course->status,
                    'detail' => $course->detail,
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
                $course = Course::with(['category', 'instructor'])
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
                    'image' => $course->image ? asset('storage/' . $course->image) : null,
                    'detail' => $course->detail,
                    'status' => $course->status,
                    'updated_at' => $course->updated_at,
                    'created_at' => $course->created_at,
                ];

                return new PostResource(true, 'Course retrieved successfully', $data);
            } else if ($tab === 'persyaratan') {
                $prerequisites = CoursePrerequisite::where('id_course', $id)->get(['id', 'content']);
                $data = $prerequisites->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'prerequisite' => $item->content,
                    ];
                });
                return new TableResource(true, 'Prerequisite retrieved successfully', [
                    'data' => $data,
                ], 200);
            } else if ($tab === 'deskripsi') {
                $descriptions = CourseDescription::where('id_course', $id)->get(['id', 'content']);
                $data = $descriptions->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'description' => $item->content,
                    ];
                });
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
                'level' => 'required|in:beginner,intermediate,advance',
                'price' => 'required|numeric|min:0',
                'image' => 'nullable|file|mimes:jpg,jpeg,png|max:2048',
                'detail' => 'required|string',
            ]);

            $course = Course::where('id', $id)
                ->where('id_instructor', $user->id)
                ->firstOrFail();

            // Handle image upload
            if ($request->hasFile('image')) {
                $newImagePath = $request->file('image')->store('course', 'public');

                // Check if the new image is different from the current one
                if ($course->image && $course->image !== $newImagePath) {
                    // Delete the old image from storage
                    if (Storage::disk('public')->exists($course->image)) {
                        Storage::disk('public')->delete($course->image);
                    }
                }

                // Update the course's image attribute
                $course->image = $newImagePath;
            }

            // Update course attributes
            $course->title = $validated['title'];
            $course->level = $validated['level'];
            $course->price = $validated['price'];

            $oldDetail = $course->detail ?? '';
            $newDetail = $validated['detail'];

            $imagesToDelete = $this->getImagesToDeleteFromDetail($oldDetail, $newDetail);
            $newDetailCleaned = $this->removeDeletedImagesFromDetail($newDetail, $imagesToDelete);

            foreach ($imagesToDelete as $imgPath) {
                if (Storage::disk('public')->exists($imgPath)) {
                    Storage::disk('public')->delete($imgPath);
                }
            }

            $course->detail = $newDetailCleaned;
            $course->save();

            $data = [
                'id' => $course->id,
                'title' => $course->title,
                'level' => $course->level,
                'price' => $course->price,
                'image' => $course->image ? asset('storage/' . $course->image) : null,
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
            $course = Course::with(['category', 'instructor', 'descriptions', 'prerequisites', 'reviews'])
                ->where('id', $id)
                ->firstOrFail();

            return new PostResource(true, 'Course retrieved successfully', $course);
        } catch (\Exception $e) {
            return new PostResource(false, 'Failed to retrieve course: ' . $e->getMessage(), null);
        }
    }
}