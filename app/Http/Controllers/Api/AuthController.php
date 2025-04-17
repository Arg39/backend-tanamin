<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\UserDetail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6|confirmed',
            'role' => 'required|string|in:student,admin,instructor',
        ]);

        if ($validator->fails()) {
            return (new PostResource(false, 'Validation errors', $validator->errors()))
                ->response()
                ->setStatusCode(422);
        }

        // Split the name into first_name and last_name
        $nameParts = explode(' ', $request->name, 2);
        $firstName = $nameParts[0];
        $lastName = isset($nameParts[1]) ? $nameParts[1] : '';

        $user = User::create([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'username' => $request->username,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
        ]);

        UserDetail::create([
            'id_user' => $user->id,
            'expertise' => null,
            'about' => null,
            'social_media' => null,
            'photo_cover' => null,
            'update_password' => false,
        ]);

        // Generate and save the token
        $token = JWTAuth::fromUser($user);
        $user->update(['token' => $token]);

        return (new PostResource(true, 'User registered successfully', ['token' => $token]))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Login a user.
     */
    public function login(Request $request)
    {
        $credentials = $request->only('password');
        $loginField = filter_var($request->input('login'), FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        $credentials[$loginField] = $request->input('login');

        if (!$token = JWTAuth::attempt($credentials)) {
            return (new PostResource(false, 'Invalid credentials', null))
                ->response()
                ->setStatusCode(401);
        }

        $user = JWTAuth::user();
        $user->update(['token' => $token]);

        return (new PostResource(true, 'Login successful', ['token' => $token]))
            ->response()
            ->setStatusCode(200);
    }

    /**
     * Logout a user.
     */
    public function logout()
    {
        try {
            // Periksa apakah token disediakan
            if (!$token = JWTAuth::getToken()) {
                return (new PostResource(false, 'Token not provided', null))
                    ->response()
                    ->setStatusCode(400);
            }

            // Check if token exists
            $user = JWTAuth::user();
            $user->update(['token' => null]);

            JWTAuth::invalidate($token);

            return (new PostResource(true, 'Successfully logged out', null))
                ->response()
                ->setStatusCode(200);
        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            return (new PostResource(false, 'Invalid token', ['error' => $e->getMessage()]))
                ->response()
                ->setStatusCode(401);
        } catch (\Exception $e) {
            return (new PostResource(false, 'Failed to log out', ['error' => $e->getMessage()]))
                ->response()
                ->setStatusCode(500);
        }
    }
}
