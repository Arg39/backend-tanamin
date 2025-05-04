<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            if (!$user = JWTAuth::parseToken()->authenticate()) {
                return response()->json([
                    'status' => 'failed',
                    'message' => 'User not found',
                ], Response::HTTP_UNAUTHORIZED);
            }

            // Periksa apakah pengguna adalah admin
            if ($user->role !== 'admin') {
                return response()->json([
                    'status' => 'failed',
                    'message' => 'Unauthorized',
                ], Response::HTTP_FORBIDDEN);
            }
        } catch (JWTException $e) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Token is invalid or not provided',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}