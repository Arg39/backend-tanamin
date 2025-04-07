<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('certificate');
});

Route::get('/phpinfo', function () {
    phpinfo();
});