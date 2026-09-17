<?php

namespace App\Providers;

use App\Infrastructure\Payment\Drivers\FakeGateway;
use App\Infrastructure\Payment\PaymentGateway;
use App\Infrastructure\Sms\Drivers\LogSmsChannel;
use App\Infrastructure\Sms\SmsChannel;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * درایورهای تعویض‌پذیر Infrastructure — فصل ۲-۲ سند معماری.
     * تغییر سرویس پیامک/پرداخت فقط با تغییر env و اضافه‌کردن یک Driver جدید.
     */
    public array $bindings = [
        SmsChannel::class => LogSmsChannel::class,
        PaymentGateway::class => FakeGateway::class,
    ];

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Rate Limiters — فصل ۸-۳ سند معماری
        |--------------------------------------------------------------------------
        | OTP: سقف سخت‌گیرانه (۳/ساعت هر شماره، ۲۰/ساعت هر IP) — دفاع Brute Force.
        */
        RateLimiter::for('otp', function (Request $request) {
            return [
                Limit::perHour((int) config('gamification.otp.request_limit.max', 3))
                    ->by('otp-phone:'.$request->input('phone', 'unknown')),
                Limit::perHour((int) config('gamification.otp.ip_limit.max', 20))
                    ->by('otp-ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('otp-verify', function (Request $request) {
            return [
                Limit::perHour(30)->by('otp-verify:'.$request->ip()),
            ];
        });

        RateLimiter::for('payments', function (Request $request) {
            return [
                Limit::perMinute(30)->by('payments:'.$request->ip()),
            ];
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)
                ->by($request->user()?->id ?: $request->ip());
        });
    }
}
