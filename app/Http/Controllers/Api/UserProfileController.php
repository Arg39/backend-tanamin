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

        /**
     * Get user profile and details by id (for admin).
     */
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
        $query = User::where('role', 'instructor');
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
    
        return (new TableResource(true, 'Instructors retrieved successfully', ['data' => $paginated]))
            ->response()
            ->setStatusCode(200);
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

    public function getInstructorForSelect()
    {
        try {
            $instructors = User::where('role', 'instructor')
            ->select('id', 'first_name', 'last_name')
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => trim($user->first_name . ' ' . $user->last_name),
                ];
            });

            if ($instructors->isEmpty()) {
                return new PostResource(false, 'No instructor found', []);
            }

            return (new PostResource(true, 'Instructor retrieved successfully', $instructors))
                ->response()
                ->setStatusCode(200);
        } catch (\Exception $e) {
            return new PostResource(false, 'Failed to retrieve instructor: ' . $e->getMessage(), null);
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