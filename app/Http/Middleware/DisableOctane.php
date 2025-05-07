<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Octane\Facades\Octane;

class DisableOctane
{
    public function handle(Request $request, Closure $next)
    {
        // Force Octane worker to run this request synchronously (no reuse worker)
        Octane::concurrently([]); // dummy to trigger fresh task

        return $next($request);
    }
}
