<?php

namespace App\Providers;

use App\Domain\Analytics\Listeners\RecordCampaignView;
use App\Domain\Analytics\Listeners\RecordCouponRedeem;
use App\Domain\Analytics\Listeners\RecordCustomerCheckin;
use App\Domain\Analytics\Listeners\RecordCustomerEnter;
use App\Domain\Analytics\Listeners\RecordGamePlay;
use App\Domain\Analytics\Listeners\RecordReferral;
use App\Domain\Campaign\Events\CampaignViewed;
use App\Domain\Coupon\Events\CouponRedeemed;
use App\Domain\Customer\Events\CustomerEnteredCampaign;
use App\Domain\Game\Events\GamePlayed;
use App\Domain\Referral\Events\ReferralRegistered;
use App\Domain\Retention\Events\CustomerCheckedIn;
use App\Domain\Reward\Events\RewardIssued;
use App\Domain\Subscription\Events\SubscriptionChanged;
use App\Infrastructure\Payment\Drivers\FakeGateway;
use App\Infrastructure\Payment\PaymentGateway;
use App\Infrastructure\Sms\Drivers\LogSmsChannel;
use App\Infrastructure\Sms\SmsChannel;
use App\Support\Audit\Listeners\AuditReferralReward;
use App\Support\Audit\Listeners\AuditRewardIssued;
use App\Support\Audit\Listeners\AuditSubscriptionChanged;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
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

        // سقف‌های سخت‌گیرانه مسیرهای حساس بازی — فصل ۸-۳
        RateLimiter::for('play-start', function (Request $request) {
            return [
                Limit::perMinute(10)->by('play-start:'.($request->user()?->id ?: $request->ip())),
            ];
        });

        RateLimiter::for('play-action', function (Request $request) {
            return [
                Limit::perMinute(30)->by('play-action:'.($request->user()?->id ?: $request->ip())),
            ];
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)
                ->by($request->user()?->id ?: $request->ip());
        });

        /*
        |--------------------------------------------------------------------------
        | Listenerهای Analytics — فصل ۴-۳ و ۱۰ سند معماری (Sprint 5)
        |--------------------------------------------------------------------------
        | ارتباط Cross-Domain فقط با Domain Event: دامنه Analytics به رخدادهای
        | سایر دامنه‌ها گوش می‌دهد و قیف View → Enter → Play → Win → Redeem
        | را ثبت می‌کند. شکست Analytics هرگز جریان اصلی را نمی‌شکند.
        */
        Event::listen(CampaignViewed::class, RecordCampaignView::class);
        Event::listen(CustomerEnteredCampaign::class, RecordCustomerEnter::class);
        Event::listen(GamePlayed::class, RecordGamePlay::class);
        Event::listen(CouponRedeemed::class, RecordCouponRedeem::class);
        Event::listen(CustomerCheckedIn::class, RecordCustomerCheckin::class);
        Event::listen(ReferralRegistered::class, RecordReferral::class);

        /*
        |--------------------------------------------------------------------------
        | Listenerهای Audit — فصل ۲-۵ و ۱۰ سند معماری (Sprint 6)
        |--------------------------------------------------------------------------
        | ثبت Append-Only رخدادهای حساس: صدور جایزه، تغییر اشتراک، جایزه دعوت.
        | (ورود، شروع Session، نتیجه بازی و استفاده کوپن مستقیماً در Actionها ثبت می‌شوند.)
        */
        Event::listen(RewardIssued::class, AuditRewardIssued::class);
        Event::listen(SubscriptionChanged::class, AuditSubscriptionChanged::class);
        Event::listen(ReferralRewardGranted::class, AuditReferralReward::class);
    }
}
