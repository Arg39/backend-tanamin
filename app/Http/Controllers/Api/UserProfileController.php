<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class UserProfileController extends Controller
{
    /**
     * Update user profile and details.
     */
    public function updateProfile(Request $request)
    {
        // mengambil data user berdasarkan id
        $user = Auth::user();
        // dd($user->id);

        $validator = Validator::make($request->all(), [
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255|unique:users,email,' . $user->id,
            'username' => 'sometimes|string|max:255|unique:users,username,' . $user->id,
            'telephone' => 'sometimes|string|max:15',
            'expertise' => 'sometimes|string|max:255',
            'about' => 'sometimes|string|max:1000',
            'social_media' => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            return (new PostResource(false, 'Validation errors', $validator->errors()))
                ->response()
                ->setStatusCode(422);
        }

        // Update user basic information
        $user->update($request->only('first_name', 'last_name', 'email', 'username', 'telephone'));

        // Update user detail information
        $user->detail()->update(
            ['id_user' => $user->id],
            $request->only('expertise', 'about', 'social_media')
        );

        return (new PostResource(true, 'Profile updated successfully', $user->load('detail')))
            ->response()
            ->setStatusCode(200);
    }

    /**
     * Get user profile and details.
     */
    public function getProfile()
    {
        $user = Auth::user();

        // Load user details with the related detail model
        $user->load('detail');

        return (new PostResource(true, 'User profile retrieved successfully', $user))
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