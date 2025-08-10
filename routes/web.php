<?php

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/phpinfo', function () {
    phpinfo();
});

Route::get('/phpinfo-franken', function () {
    ob_start();
    phpinfo();
    $phpinfo = ob_get_clean();
    return response($phpinfo)->header('Content-Type', 'text/html');
});

Route::get('/test-email', function () {
    Mail::raw('Ini email tes dari Laravel pakai Gmail SMTP.', function ($message) {
        $message->to('pabannu@gmail.com')
                ->subject('Tes Email Laravel SMTP');
    });

    return 'Email terkirim!';
});