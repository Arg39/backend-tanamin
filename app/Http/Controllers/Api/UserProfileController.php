<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Http\Resources\TableResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Traits\FilteringTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class UserProfileController extends Controller
{
    use FilteringTrait;

    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        $input = $request->all();
        if (isset($input['social_media'])) {
            if (is_string($input['social_media']) && !empty($input['social_media'])) {
                $decoded = json_decode($input['social_media'], true);
                $input['social_media'] = is_array($decoded) ? $decoded : [];
            } elseif (is_null($input['social_media']) || $input['social_media'] === '') {
                $input['social_media'] = [];
            }
        }

        $validator = Validator::make($input, [
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'username' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255|unique:users,email,' . $user->id,
            'telephone' => 'nullable|string|max:15',
            'expertise' => 'nullable|string|max:255',
            'about' => 'nullable|string|max:1000',
            'social_media' => 'nullable|array',
            'photo_profile' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'photo_cover' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:4096',
        ]);

        if ($validator->fails()) {
            return (new PostResource(false, 'Validation errors', $validator->errors()))
                ->response()
                ->setStatusCode(422);
        }

        if ($request->hasFile('photo_profile')) {
            if ($user->photo_profile && Storage::disk('public')->exists($user->photo_profile)) {
                Storage::disk('public')->delete($user->photo_profile);
            }
            $photoProfilePath = $request->file('photo_profile')->store('profile_photos', 'public');
            $user->photo_profile = $photoProfilePath;
        }

        $user->fill(array_intersect_key($input, array_flip(['first_name', 'last_name', 'email', 'username', 'telephone'])));
        $user->save();

        $detailData = array_intersect_key($input, array_flip(['expertise', 'about', 'social_media']));

        if ($request->hasFile('photo_cover')) {
            $detail = $user->detail;
            if ($detail && $detail->photo_cover && Storage::disk('public')->exists($detail->photo_cover)) {
                Storage::disk('public')->delete($detail->photo_cover);
            }
            $photoCoverPath = $request->file('photo_cover')->store('cover_photos', 'public');
            $detailData['photo_cover'] = $photoCoverPath;
        }

        if (!empty($detailData)) {
            $user->detail()->updateOrCreate(
                ['id_user' => $user->id],
                $detailData
            );
        }

        $user->load('detail');

        return (new PostResource(true, 'Profile updated successfully', $user))
            ->response()
            ->setStatusCode(200);
    }

    public function updateStatus(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'status' => 'required|in:active,inactive',
            ]);

            if ($validator->fails()) {
                return (new PostResource(false, 'Validation errors', $validator->errors()))
                    ->response()
                    ->setStatusCode(422);
            }

            $user = User::find($id);

            if (!$user) {
                return (new PostResource(false, 'User not found.', null))
                    ->response()
                    ->setStatusCode(404);
            }

            $user->status = $request->input('status');
            $user->save();

            return (new PostResource(true, 'User status updated successfully.', (new UserResource($user))->resolve($request)))
                ->response()
                ->setStatusCode(200);
        } catch (\Exception $e) {
            return (new PostResource(false, 'Failed to update user status: ' . $e->getMessage(), null))
                ->response()
                ->setStatusCode(500);
        }
    }

    /**
     * Get user profile and details.
     */
    public function getProfile()
    {
        $user = Auth::user();
        $user->load('detail');

        return (new PostResource(true, 'Profil pengguna berhasil diambil.', (new UserResource($user))->resolve(request())))
            ->response()
            ->setStatusCode(200);
    }

    public function getProfileById($id)
    {
        $user = User::with('detail')->find($id);

        if (!$user) {
            return (new PostResource(false, 'User not found.', null))
                ->response()
                ->setStatusCode(404);
        }

        return (new PostResource(true, 'Profil pengguna berhasil diambil.', (new UserResource($user))->resolve(request())))
            ->response()
            ->setStatusCode(200);
    }

    public function getInstructors(Request $request)
    {
        try {
            $query = User::where('role', 'instructor')->with('categoriesInstructor');
            $filterable = ['first_name', 'last_name', 'email'];
            $searchable = ['first_name', 'last_name', 'email'];

            if ($request->filled('name')) {
                $name = $request->input('name');
                $query->where(function ($q) use ($name) {
                    $q->where('first_name', 'like', "%{$name}%")
                        ->orWhere('last_name', 'like', "%{$name}%");
                });
            }

            $sortBy = $request->input('sortBy');
            $sortOrder = $request->input('sortOrder', 'asc');
            if ($sortBy) {
                if (strtolower($sortBy) === 'nama' || strtolower($sortBy) === 'full_name') {
                    $query->orderBy('first_name', $sortOrder)->orderBy('last_name', $sortOrder);
                } elseif (in_array($sortBy, $filterable)) {
                    $query->orderBy($sortBy, $sortOrder);
                }
            }

            $paginated = $this->filterQuery($query, $request, $filterable, $searchable);

            $items = collect(method_exists($paginated, 'items') ? $paginated->items() : $paginated);

            $items->transform(function ($user) {
                return [
                    'id'   => $user->id,
                    'full_name'   => $user->full_name,
                    'email'       => $user->email,
                    'status'      => $user->status,
                    'category'    => $user->categoriesInstructor->pluck('name')->first(),
                    'created_at'  => $user->created_at,
                ];
            });

            if (method_exists($paginated, 'setCollection')) {
                $paginated->setCollection($items);
            }

            return (new TableResource(true, 'Instructors retrieved successfully', ['data' => $paginated]))
                ->response()
                ->setStatusCode(200);
        } catch (\Exception $e) {
            return (new TableResource(false, 'Failed to retrieve instructors: ' . $e->getMessage(), []))
                ->response()
                ->setStatusCode(500);
        }
    }

    public function getStudents(Request $request)
    {
        try {
            $query = User::where('role', 'student');
            $filterable = ['first_name', 'last_name', 'email'];
            $searchable = ['first_name', 'last_name', 'email'];

            if ($request->filled('name')) {
                $name = $request->input('name');
                $query->where(function ($q) use ($name) {
                    $q->where('first_name', 'like', "%{$name}%")
                        ->orWhere('last_name', 'like', "%{$name}%");
                });
            }

            $paginated = $this->filterQuery($query, $request, $filterable, $searchable);

            return (new TableResource(true, 'Students retrieved successfully', ['data' => $paginated]))
                ->response()
                ->setStatusCode(200);
        } catch (\Exception $e) {
            return (new TableResource(false, 'Failed to retrieve students: ' . $e->getMessage(), []))
                ->response()
                ->setStatusCode(500);
        }
    }

    public function getInstructorForSelect(Request $request)
    {
        try {
            $query = User::where('role', 'instructor');

            if ($request->filled('id_category')) {
                $idCategory = $request->input('id_category');
                $query->whereHas('categoriesInstructor', function ($q) use ($idCategory) {
                    $q->where('categories.id', $idCategory);
                });
            }

            $instructors = $query
                ->select('id', 'first_name', 'last_name')
                ->get()
                ->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'name' => trim($user->first_name . ' ' . $user->last_name),
                    ];
                });

            if ($instructors->isEmpty()) {
                return (new PostResource(false, 'No instructor found', []))
                    ->response()
                    ->setStatusCode(200);
            }

            return (new PostResource(true, 'Instructor retrieved successfully', $instructors))
                ->response()
                ->setStatusCode(200);
        } catch (\Exception $e) {
            return (new PostResource(false, 'Failed to retrieve instructor: ' . $e->getMessage(), null))
                ->response()
                ->setStatusCode(500);
        }
    }

    public function getInstructorListByCategory(Request $request)
    {
        try {
            $categoryId = $request->input('category_id');
            $more = $request->input('more');
            $limit = 4; // Number of instructors per "page"

            // If category_id and more are present, return only the next instructors for that category
            if ($categoryId && $more) {
                $category = \App\Models\Category::find($categoryId);
                if (!$category) {
                    return (new PostResource(false, 'Category not found.', null))
                        ->response()
                        ->setStatusCode(404);
                }

                // Calculate offset
                $offset = $limit * intval($more);

                // Get instructors for this category, skipping previous ones
                $instructors = User::where('role', 'instructor')
                    ->whereHas('categoriesInstructor', function ($q) use ($category) {
                        $q->where('categories.id', $category->id);
                    })
                    ->with('detail')
                    ->skip($offset)
                    ->take($limit)
                    ->get();

                // Prepare user data
                $userData = $instructors->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->full_name,
                        'course_held' => $user->courses()->count(),
                        'photo_profile' => $user->photo_profile,
                        'expertise' => optional($user->detail)->expertise,
                    ];
                })->values();

                // Check if there are more instructors after this batch
                $totalInstructors = User::where('role', 'instructor')
                    ->whereHas('categoriesInstructor', function ($q) use ($category) {
                        $q->where('categories.id', $category->id);
                    })
                    ->count();

                $hasMore = ($offset + $limit) < $totalInstructors;

                $result = [
                    'user' => $userData,
                    'has_more' => $hasMore,
                ];

                return (new PostResource(true, 'List of instructors retrieved successfully.', [$result]))
                    ->response()
                    ->setStatusCode(200);
            }

            // Default: return grouped by category, first 4 instructors per category
            $categories = \App\Models\Category::all();
            $result = [];

            foreach ($categories as $category) {
                // Get instructors for this category (limit 5 to check if more than 4)
                $instructors = User::where('role', 'instructor')
                    ->whereHas('categoriesInstructor', function ($q) use ($category) {
                        $q->where('categories.id', $category->id);
                    })
                    ->with('detail')
                    ->take($limit + 1)
                    ->get();

                // Prepare user data (max 4)
                $userData = $instructors->take($limit)->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->full_name,
                        'course_held' => $user->courses()->count(),
                        'photo_profile' => $user->photo_profile,
                        'expertise' => optional($user->detail)->expertise,
                    ];
                })->values();

                // Check if there are more than 4 instructors
                $hasMore = $instructors->count() > $limit;

                $result[] = [
                    'category' => [
                        'title' => $category->name,
                        'id' => $category->id,
                    ],
                    'user' => $userData,
                    'has_more' => $hasMore,
                ];
            }

            return (new PostResource(true, 'List of instructors retrieved successfully.', $result))
                ->response()
                ->setStatusCode(200);
        } catch (\Exception $e) {
            return (new PostResource(false, 'Failed to retrieve instructor list by category: ' . $e->getMessage(), null))
                ->response()
                ->setStatusCode(500);
        }
    }

    public function destroy($id)
    {
        try {
            $user = User::with('detail')->find($id);

            if (!$user) {
                return (new PostResource(false, 'User not found.', null))
                    ->response()
                    ->setStatusCode(404);
            }

            if ($user->photo_profile && Storage::disk('public')->exists($user->photo_profile)) {
                Storage::disk('public')->delete($user->photo_profile);
            }

            if ($user->detail && $user->detail->photo_cover && Storage::disk('public')->exists($user->detail->photo_cover)) {
                Storage::disk('public')->delete($user->detail->photo_cover);
            }

            if ($user->detail) {
                $user->detail->delete();
            }

            // Delete user
            $user->delete();

            return (new PostResource(true, 'User deleted successfully.', null))
                ->response()
                ->setStatusCode(200);
        } catch (\Exception $e) {
            return (new PostResource(false, 'Failed to delete user: ' . $e->getMessage(), null))
                ->response()
                ->setStatusCode(500);
        }
    }
}
