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
use App\Domain\Referral\Events\ReferralRewardGranted;
use App\Domain\Retention\Events\CustomerCheckedIn;
use App\Domain\Reward\Events\RewardIssued;
use App\Domain\Subscription\Events\SubscriptionChanged;
use App\Infrastructure\Payment\Drivers\FakeGateway;
use App\Infrastructure\Payment\Drivers\SandboxGateway;
use App\Infrastructure\Payment\Drivers\ZarinpalGateway;
use App\Infrastructure\Payment\PaymentGateway;
use App\Infrastructure\Sms\Drivers\IppanelSmsChannel;
use App\Infrastructure\Sms\Drivers\LogSmsChannel;
use App\Infrastructure\Sms\SmsChannel;
use App\Support\Audit\Listeners\AuditReferralReward;
use App\Support\Audit\Listeners\AuditRewardIssued;
use App\Support\Audit\Listeners\AuditSubscriptionChanged;
use App\Support\Settings\SiteSettingsService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * درایورهای تعویض‌پذیر Infrastructure — فصل ۲-۲ سند معماری (Sprint 7-9).
     *
     * اولویت پیکربندی: سندباکس‌های تنظیمات سایت (فقط Admin) → مقادیر تنظیمات سایت
     * (متغیرهای درایور قابل ویرایش از پنل — Sprint 9) → پیش‌فرض env/config.
     * یعنی Admin می‌تواند بدون تغییر env، درایور و کلیدهای IPPanel/ZarinPal را
     * از بخش تنظیمات پنل عوض کند؛ سندباکس‌ها همچنان بر همه چیز مقدم‌اند.
     */
    public function register(): void
    {
        // تنظیمات سایت (تک‌ردیفی) — Singleton برای حداکثر یک Query در هر Request
        $this->app->singleton(SiteSettingsService::class);

        $this->app->bind(SmsChannel::class, function (): SmsChannel {
            // سندباکس پیامک: هیچ درایور واقعی بایند نمی‌شود (ارسال SMS کلاً Skip می‌شود)
            $settings = app(SiteSettingsService::class);

            return match ($settings->smsChannel()) {
                'ippanel' => new IppanelSmsChannel(
                    apiKey: $settings->ippanelApiKey(),
                    originator: $settings->ippanelOriginator(),
                    baseUrl: $settings->ippanelBaseUrl(),
                    timeout: (int) config('gamification.sms.ippanel.timeout', 10),
                ),
                default => new LogSmsChannel,
            };
        });

        $this->app->bind(PaymentGateway::class, function (): PaymentGateway {
            $settings = app(SiteSettingsService::class);

            // سندباکس پرداخت (تنظیمات سایت، فقط Admin) مقدم بر همه — همان جریان کامل callback/verify بدون سرویس خارجی
            if ($settings->paymentSandboxEnabled()) {
                return new SandboxGateway;
            }

            return match ($settings->paymentGateway()) {
                'zarinpal' => new ZarinpalGateway(
                    merchantId: $settings->zarinpalMerchantId(),
                    callbackUrl: $settings->zarinpalCallbackUrl(),
                    // سندباکس رسمی خود زرین‌پال: sandbox.zarinpal.com (همان API v4) — Sprint 9
                    baseUrl: $settings->zarinpalBaseUrl(),
                    tomanToRial: $settings->zarinpalTomanToRial(),
                    description: $settings->zarinpalDescription(),
                ),
                default => new FakeGateway,
            };
        });
    }

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
