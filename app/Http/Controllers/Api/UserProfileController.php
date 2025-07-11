<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class UserProfileController extends Controller
{
    public function updateProfile(Request $request)
    {
        $user = Auth::user();
    
        // Fix: Convert social_media to array if it's JSON string or null
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
    
        // Handle photo_profile upload
        if ($request->hasFile('photo_profile')) {
            // Hapus gambar lama jika ada
            if ($user->photo_profile && Storage::disk('public')->exists($user->photo_profile)) {
                Storage::disk('public')->delete($user->photo_profile);
            }
            $photoProfilePath = $request->file('photo_profile')->store('profile_photos', 'public');
            $user->photo_profile = $photoProfilePath;
        }
    
        // Update user basic information
        $user->fill(array_intersect_key($input, array_flip(['first_name', 'last_name', 'email', 'username', 'telephone'])));
        $user->save();
    
        // Prepare detail data
        $detailData = array_intersect_key($input, array_flip(['expertise', 'about', 'social_media']));
    
        // Handle photo_cover upload
        if ($request->hasFile('photo_cover')) {
            // Pastikan relasi detail sudah ada
            $detail = $user->detail;
            // Hapus gambar lama jika ada
            if ($detail && $detail->photo_cover && \Storage::disk('public')->exists($detail->photo_cover)) {
                \Storage::disk('public')->delete($detail->photo_cover);
            }
            $photoCoverPath = $request->file('photo_cover')->store('cover_photos', 'public');
            $detailData['photo_cover'] = $photoCoverPath;
        }
    
        // Update or create user detail information
        if (!empty($detailData)) {
            $user->detail()->updateOrCreate(
                ['id_user' => $user->id],
                $detailData
            );
        }
    
        // Reload user with detail
        $user->load('detail');
    
        return (new PostResource(true, 'Profile updated successfully', $user))
            ->response()
            ->setStatusCode(200);
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

    public function getInstructors()
    {
        $instructors = User::where('role', 'instructor')->get();

        return (new PostResource(true, 'Instructors retrieved successfully', $instructors))
            ->response()
            ->setStatusCode(200);
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
}