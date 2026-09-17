<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/** PWA مشتری — فصل ۹ سند معماری (SPA سبک بدون session سمت سرور) */
Route::get('/c/{slug}', function (string $slug) {
    return view('pwa', ['slug' => $slug]);
})->where('slug', '[a-z0-9-]+');
