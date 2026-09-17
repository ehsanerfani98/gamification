<?php

use App\Http\Controllers\Api\V1\Auth\OtpController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\PlanController;
use App\Http\Controllers\Api\V1\StoreController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API نسخه‌دار /api/v1 — فصل ۸ سند معماری
|--------------------------------------------------------------------------
| Merchant و Admin با Sanctum Token پس از OTP، مشتری با Token محدود.
| همه پاسخ‌ها JSON: موفق data / خطا error{code,message,fields}
*/

Route::prefix('v1')->group(function (): void {
    // ── احراز هویت (عمومی) ───────────────────────────────────────
    // Rate Limit پلکانی: ۳/ساعت هر شماره + ۲۰/ساعت هر IP (فصل ۸-۳)
    Route::post('auth/otp/request', [OtpController::class, 'request'])
        ->middleware('throttle:otp');
    Route::post('auth/otp/verify', [OtpController::class, 'verify'])
        ->middleware('throttle:otp-verify');

    // ── مسیرهای Merchant (توکن با ability «merchant») ─────────────
    Route::middleware(['auth:sanctum', 'abilities:merchant'])
        ->group(function (): void {
            Route::get('stores', [StoreController::class, 'index']);
            Route::post('stores', [StoreController::class, 'store']);

            // فروشگاه جاری از X-Store-Id resolve می‌شود (middleware resolve.store)
            Route::middleware('resolve.store')->group(function (): void {
                Route::get('plans', [PlanController::class, 'index']);
                Route::get('subscriptions', [SubscriptionController::class, 'show']);
                Route::post('subscriptions', [SubscriptionController::class, 'subscribe']);
            });
        });

    // مشاهده فروشگاه مشخص — مالکیت در Controller بررسی می‌شود (404 برای Cross-Tenant)
    Route::get('stores/{id}', [StoreController::class, 'show'])
        ->middleware(['auth:sanctum', 'abilities:merchant']);

    // ── callback دروازه پرداخت (امضاشده) ─────────────────────────
    Route::post('payments/callback', [PaymentController::class, 'callback'])
        ->middleware('throttle:payments');
});
