<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class ResetAuthStateMiddleware
{
    public function handle($request, Closure $next)
    {
        // Pastikan state auth dibersihkan setiap request baru
        Auth::forgetGuards();

        // Jalankan request seperti biasa
        $response = $next($request);

        // Bersihkan lagi setelah response (jaga-jaga kalau guard disimpan statis)
        Auth::forgetGuards();

        return $response;
    }
}
