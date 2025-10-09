<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Hapus instance JWT tiap request agar tidak nyangkut di Octane
        if (app()->bound('tymon.jwt')) {
            app()->forgetInstance('tymon.jwt');
            app()->forgetInstance('tymon.jwt.auth');
            app()->forgetInstance('tymon.jwt.manager');
        }

        // Tambahkan ini agar Auth selalu di-reset juga
        if (function_exists('app')) {
            app()->terminating(function () {
                \Illuminate\Support\Facades\Auth::forgetGuards();
            });
        }
    }
}
