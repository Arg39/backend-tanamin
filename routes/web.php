<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/phpinfo', function () {
    phpinfo();
});

Route::get('/phpinfo-franken', function () {
    ob_start(); // Start output buffering
    phpinfo();  // Execute phpinfo()
    $phpinfo = ob_get_clean(); // Get the output and clean the buffer
    return response($phpinfo)->header('Content-Type', 'text/html'); // Return as HTML response
});