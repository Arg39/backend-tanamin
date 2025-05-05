<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Http\Resources\ErrorResource;

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
                return (new ErrorResource(['message' => 'User not found']))
                    ->response()
                    ->setStatusCode(Response::HTTP_UNAUTHORIZED);
            }

            // Periksa apakah pengguna adalah admin
            if ($user->role !== 'admin') {
                return (new ErrorResource(['message' => 'Unauthorized']))
                    ->response()
                    ->setStatusCode(Response::HTTP_FORBIDDEN);
            }
        } catch (JWTException $e) {
            return (new ErrorResource(['message' => 'Token is invalid or not provided']))
                ->response()
                ->setStatusCode(Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}