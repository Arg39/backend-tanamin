<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Octane\Events\RequestReceived;
use Laravel\Octane\Facades\Octane;
use Illuminate\Support\Facades\Auth;

class OctaneEventServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Octane::listen(RequestReceived::class, function () {
            app()->forgetInstance('auth');
            app()->forgetInstance('auth.driver');
            app()->forgetInstance('tymon.jwt');
            Auth::forgetGuards();
        });
    }
}
