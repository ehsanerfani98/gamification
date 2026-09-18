<?php

use App\Models\Payment;
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

/**
 * صفحه پرداخت آزمایشی سندباکس — Sprint 8 (بتای ۵ فروشگاه).
 * فقط وقتی SandboxGateway فعال است به اینجا ریدایرکت می‌شود؛
 * Authority در query و reference رکورد یکسان‌اند (capability).
 */
Route::get('/payments/sandbox/{payment}', function (Payment $payment) {
    $payment->load(['plan', 'store']);

    return view('payments.sandbox', ['payment' => $payment]);
})->whereNumber('payment')->middleware('throttle:payments');
