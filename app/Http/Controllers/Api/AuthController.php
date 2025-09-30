<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\Category;
use App\Models\InstructorCategory;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\UserDetail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    /**
     * Register a new user.
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return (new PostResource(false, 'Validation errors', $validator->errors()))
                ->response()
                ->setStatusCode(422);
        }

        try {
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
                'role' => 'student',
            ]);

            UserDetail::create([
                'user_id' => $user->id,
                'expertise' => null,
                'about' => null,
                'social_media' => null,
                'photo_cover' => null,
                'update_password' => false,
            ]);

            // Generate and save the token
            $token = JWTAuth::fromUser($user);
            $user->update(['token' => $token]);

            try {
                $notificationController = new NotificationController();
                $notificationController->makeNotification(
                    $user->id,
                    'Registrasi Berhasil',
                    'Selamat datang di aplikasi kami!'
                );
            } catch (\Exception $e) {
                // Log error jika gagal membuat notifikasi, tapi tetap lanjutkan proses registrasi
                \Log::error('Gagal membuat notifikasi registrasi: ' . $e->getMessage());
            }

            return (new PostResource(true, 'User registered successfully', ['token' => $token]))
                ->response()
                ->setStatusCode(200);
        } catch (\Exception $e) {
            return (new PostResource(false, 'Failed to register user', ['error' => $e->getMessage()]))
                ->response()
                ->setStatusCode(500);
        }
    }

    public function registerInstructor(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'username' => 'required|string|max:255|unique:users',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:6|confirmed',
                'role' => 'required|string|in:admin,instructor',
                'category_id' => 'required|uuid',
            ]);

            if ($validator->fails()) {
                return (new PostResource(false, 'Validation errors', $validator->errors()))
                    ->response()
                    ->setStatusCode(422);
            }

            $category = Category::find($request->category_id);
            if (!$category) {
                return (new PostResource(false, 'Category not found', null))
                    ->response()
                    ->setStatusCode(404);
            }

            // Split name
            $nameParts = explode(' ', $request->name, 2);
            $firstName = $nameParts[0];
            $lastName = $nameParts[1] ?? '';

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
                'user_id' => $user->id,
                'expertise' => null,
                'about' => null,
                'social_media' => null,
                'photo_cover' => null,
                'update_password' => false,
            ]);

            InstructorCategory::create([
                'instructor_id' => $user->id,
                'category_id' => $request->category_id,
            ]);

            return (new PostResource(true, 'Admin/Instructor registered successfully', null))
                ->response()
                ->setStatusCode(200);
        } catch (\Exception $e) {
            return (new PostResource(false, 'Failed to register instructor', ['error' => $e->getMessage()]))
                ->response()
                ->setStatusCode(500);
        }
    }

    public function login(Request $request)
    {
        try {
            $credentials = $request->only('password');
            $loginField = filter_var($request->input('login'), FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
            $credentials[$loginField] = $request->input('login');

            $user = User::where($loginField, $request->input('login'))->first();
            if (!$user) {
                return (new PostResource(false, 'Invalid credentials', null))
                    ->response()
                    ->setStatusCode(401);
            }

            if ($user->status === 'inactive') {
                return (new PostResource(false, 'Account inactive', null))
                    ->response()
                    ->setStatusCode(403);
            }

            if (!$token = JWTAuth::attempt($credentials)) {
                return (new PostResource(false, 'Invalid credentials', null))
                    ->response()
                    ->setStatusCode(401);
            }

            $user = JWTAuth::user();
            $user->update(['token' => $token]);

            $userData = [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role,
            ];

            return (new PostResource(true, 'Login successful', [
                'token' => $token,
                'user' => $userData,
            ]))
                ->response()
                ->setStatusCode(200);
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            return (new PostResource(false, 'Could not create token', ['error' => $e->getMessage()]))
                ->response()
                ->setStatusCode(500);
        } catch (\Exception $e) {
            return (new PostResource(false, 'Login failed', ['error' => $e->getMessage()]))
                ->response()
                ->setStatusCode(500);
        }
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
