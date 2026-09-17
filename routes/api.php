<?php

use App\Http\Controllers\Api\V1\Auth\OtpController;
use App\Http\Controllers\Api\V1\CampaignController;
use App\Http\Controllers\Api\V1\CouponController;
use App\Http\Controllers\Api\V1\Customer\CampaignPublicController;
use App\Http\Controllers\Api\V1\Customer\MeController;
use App\Http\Controllers\Api\V1\Customer\PlayController;
use App\Http\Controllers\Api\V1\GameController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\PlanController;
use App\Http\Controllers\Api\V1\RewardController;
use App\Http\Controllers\Api\V1\StoreController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API نسخه‌دار /api/v1 — فصل ۸ سند معماری
|--------------------------------------------------------------------------
| Merchant و Admin با Sanctum Token پس از OTP، مشتری با توکن محدود.
| همه پاسخ‌ها JSON: موفق data / خطا error{code,message,fields}
*/

Route::prefix('v1')->group(function (): void {
    // ── احراز هویت پنل (عمومی) ───────────────────────────────────
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

                // Game Library و Schema پویا
                Route::get('games', [GameController::class, 'index']);
                Route::get('games/{code}/config-schema', [GameController::class, 'configSchema']);

                // کمپین‌ها
                Route::get('campaigns', [CampaignController::class, 'index']);
                Route::post('campaigns', [CampaignController::class, 'store']);
                Route::get('campaigns/{id}', [CampaignController::class, 'show']);
                Route::patch('campaigns/{id}', [CampaignController::class, 'update']);
                Route::post('campaigns/{id}/publish', [CampaignController::class, 'publish']);

                // جایزه‌های کمپین
                Route::get('campaigns/{id}/rewards', [RewardController::class, 'index']);
                Route::post('campaigns/{id}/rewards', [RewardController::class, 'store']);

                // ثبت استفاده واقعی کوپن در فروشگاه (ROI)
                Route::post('coupons/redeem', [CouponController::class, 'redeem']);
            });
        });

    // مشاهده فروشگاه مشخص — مالکیت در Controller بررسی می‌شود (404 برای Cross-Tenant)
    Route::get('stores/{id}', [StoreController::class, 'show'])
        ->middleware(['auth:sanctum', 'abilities:merchant']);

    // ── callback دروازه پرداخت (امضاشده) ─────────────────────────
    Route::post('payments/callback', [PaymentController::class, 'callback'])
        ->middleware('throttle:payments');

    // ── مسیرهای عمومی PWA کمپین (فصل ۹) ──────────────────────────
    Route::get('c/{slug}', [CampaignPublicController::class, 'show']);
    Route::post('c/{slug}/otp', [CampaignPublicController::class, 'otp'])
        ->middleware('throttle:otp');
    Route::post('c/{slug}/enter', [CampaignPublicController::class, 'enter'])
        ->middleware('throttle:otp-verify');

    // ── جریان بازی مشتری (توکن محدود با ability «customer») ───────
    Route::middleware(['auth:customer', 'abilities:customer'])
        ->group(function (): void {
            Route::post('play/sessions', [PlayController::class, 'start'])
                ->middleware('throttle:play-start');
            Route::post('play/sessions/{token}/action', [PlayController::class, 'action'])
                ->middleware('throttle:play-action');

            // کدها و امتیازهای مشتری
            Route::get('me/rewards', [MeController::class, 'rewards']);
        });
});
