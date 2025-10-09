<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class ResetAuthMiddleware
{
    public function handle($request, Closure $next)
    {
        // Pastikan setiap request pakai guard fresh
        Auth::forgetGuards();

        // Jalankan request
        $response = $next($request);

        // Hapus guard lagi setelah respon (jaga-jaga)
        Auth::forgetGuards();

        return $response;
    }
}
