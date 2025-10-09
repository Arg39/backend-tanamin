<?php

use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\DisableOctane;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\ResetAuthMiddleware;
use App\Http\Middleware\ResetAuthStateMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'disable.octane' => DisableOctane::class,
            'reset.auth' => ResetAuthMiddleware::class,
            'jwt.refresh' => \App\Http\Middleware\ForceFreshJwtAuth::class,
        ]);

        $middleware->appendToGroup('api', [
            \App\Http\Middleware\ForceFreshJwtAuth::class,
        ]);
    })


    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
