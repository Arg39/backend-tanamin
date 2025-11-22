<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use Illuminate\Http\Request;
use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
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
                'photo_profile' => $user->photo_profile ? asset('storage/' . $user->photo_profile) : null,
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
}
