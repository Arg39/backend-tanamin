<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  mixed ...$roles
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        try {
            if (!$user = JWTAuth::parseToken()->authenticate()) {
                return response()->json([
                    'status' => 'failed',
                    'message' => 'User not found'
                ], Response::HTTP_UNAUTHORIZED);
            }

            if (!in_array($user->role, $roles)) {
                return response()->json([
                    'status' => 'failed',
                    'message' => 'Unauthorized'
                ], Response::HTTP_FORBIDDEN);
            }
        } catch (JWTException) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Token is invalid or not provided'
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
