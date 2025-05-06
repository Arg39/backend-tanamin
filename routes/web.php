<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    // return view('welcome');
    return response()->json([
        'message' => 'Welcome to the API',
    ]);
});

Route::get('/phpinfo', function () {
    ob_start();
    phpinfo();
    $phpinfo = ob_get_clean();
    return response($phpinfo)->header('Content-Type', 'text/html');
});