<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Log;

class ForceFreshJwtAuth
{
    public function handle($request, Closure $next)
    {
        try {
            // Paksa JWTAuth refresh di setiap request
            app()->forgetInstance('tymon.jwt');
            app()->forgetInstance('tymon.jwt.auth');
            app()->forgetInstance('tymon.jwt.manager');
            Auth::forgetGuards();

            if ($request->bearerToken()) {
                $user = JWTAuth::setToken($request->bearerToken())->authenticate();
                if ($user) {
                    Auth::setUser($user);
                    Log::info('✅ JWT guard reset berhasil, user terautentikasi ulang: ', [$user->only('id', 'email', 'role')]);
                } else {
                    Log::warning('⚠️ JWT token valid tapi user null');
                }
            }
        } catch (\Throwable $e) {
            Log::error('JWTAuth refresh error: ' . $e->getMessage());
        }

        return $next($request);
    }
}
