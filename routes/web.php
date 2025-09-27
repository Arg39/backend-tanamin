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

Route::get('/worker-test', function () {
    static $counter = 0;
    $counter++;

    $start = microtime(true);

    // simulasi pekerjaan ringan
    for ($i = 0; $i < 100000; $i++) {
        sqrt($i);
    }

    $end = microtime(true);
    $duration = round(($end - $start) * 1000, 2); // ms

    return response()->json([
        'counter' => $counter,
        'response_time_ms' => $duration,
    ]);
});


Route::get('/test-email', function () {
    Mail::raw('Ini email tes dari Laravel pakai Gmail SMTP.', function ($message) {
        $message->to('pabannu@gmail.com')
            ->subject('Tes Email Laravel SMTP');
    });

    return 'Email terkirim!';
});
