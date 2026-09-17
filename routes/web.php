<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/panel');
});

/** PWA مشتری — فصل ۹ سند معماری (SPA سبک بدون session سمت سرور) */
Route::get('/c/{slug}', function (string $slug) {
    return view('pwa', ['slug' => $slug]);
})->where('slug', '[a-z0-9-]+');

/** پنل فروشگاه‌دار — Vue SPA با hash router */
Route::get('/panel', fn () => view('panel'));
