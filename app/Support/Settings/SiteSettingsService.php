<?php

namespace App\Support\Settings;

use App\Models\SiteSettings;
use Throwable;

/**
 * سرویس تنظیمات سایت — نقطه یکتای خواندن/نوشتن (Singleton در AppServiceProvider).
 *
 * سندباکس‌ها برای بتای ۵ فروشگاه طراحی شده‌اند: مدیر سایت بدون کلید واقعی
 * IPPanel/ZarinPal می‌تواند کل جریان ورود و خرید اشتراک را تست کند.
 *
 * متغیرهای درایور پیامک/پرداخت (Sprint 9): همه با «اولویت DB بر env» resolve
 * می‌شوند — مقدار ست‌شده در پنل بر env مقدم است؛ مقدار null یعنی پیش‌فرض env/config.
 * به این ترتیب گذار به production فقط با پاک‌کردن مقادیر پنل (یا تنظیم env) انجام می‌شود.
 *
 * resolverهای درایور در برابر نبودِ جدول (حین migrate/تست‌های خام) مقاوم‌اند
 * و به‌جای Exception مقدار env را برمی‌گردانند — همان تضمین جریان قبلی.
 */
final class SiteSettingsService
{
    /** آدرس سندباکس رسمی زرین‌پال — همان API v4 با محیط آزمایشگاهی */
    public const ZARINPAL_SANDBOX_BASE_URL = 'https://sandbox.zarinpal.com';

    public function get(): SiteSettings
    {
        return SiteSettings::current();
    }

    public function smsSandboxEnabled(): bool
    {
        return (bool) ($this->safeGet()?->sms_sandbox ?? false);
    }

    public function paymentSandboxEnabled(): bool
    {
        return (bool) ($this->safeGet()?->payment_sandbox ?? false);
    }

    /**
     * به‌روزرسانی تنظیمات — فقط از SiteSettingsController (Admin) فراخوانی می‌شود.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(array $data): SiteSettings
    {
        $settings = $this->get();
        $settings->fill($data)->save();

        return $settings;
    }

    // ─────────────────────────────────────────────────────────────
    // Resolverهای درایور پیامک — DB مقدم بر env
    // ─────────────────────────────────────────────────────────────

    /** درایور پیامک: log | ippanel (DB → env) */
    public function smsChannel(): string
    {
        $channel = $this->safeGet()?->sms_channel;

        return (string) ($channel ?? config('gamification.sms.channel', 'log'));
    }

    /** کلید API پنل IPPanel (DB → env) */
    public function ippanelApiKey(): string
    {
        $key = $this->safeGet()?->ippanel_api_key;

        return (string) ($key ?? config('gamification.sms.ippanel.api_key', ''));
    }

    /** خط ارسال IPPanel (DB → env) */
    public function ippanelOriginator(): string
    {
        $originator = $this->safeGet()?->ippanel_originator;

        return (string) ($originator ?? config('gamification.sms.ippanel.originator', ''));
    }

    /** Base URL IPPanel (DB → env) */
    public function ippanelBaseUrl(): string
    {
        $baseUrl = $this->safeGet()?->ippanel_base_url;

        return (string) ($baseUrl ?? config('gamification.sms.ippanel.base_url', 'https://api2.ippanel.com'));
    }

    // ─────────────────────────────────────────────────────────────
    // Resolverهای دروازه پرداخت — DB مقدم بر env
    // ─────────────────────────────────────────────────────────────

    /** دروازه پرداخت: fake | zarinpal (DB → env) */
    public function paymentGateway(): string
    {
        $gateway = $this->safeGet()?->payment_gateway;

        return (string) ($gateway ?? config('gamification.payments.gateway', 'fake'));
    }

    /** شناسه پذیرندگی زرین‌پال (DB → env) */
    public function zarinpalMerchantId(): string
    {
        $merchantId = $this->safeGet()?->zarinpal_merchant_id;

        return (string) ($merchantId ?? config('gamification.payments.zarinpal.merchant_id', ''));
    }

    /**
     * Base URL زرین‌پال.
     *
     * اولویت: (۱) سندباکس رسمی زرین‌پال اگر روشن باشد → sandbox.zarinpal.com
     * (۲) مقدار ست‌شده در پنل (۳) پیش‌فرض env (payment.zarinpal.com).
     */
    public function zarinpalBaseUrl(): string
    {
        $settings = $this->safeGet();

        if ($settings !== null && $settings->zarinpal_sandbox) {
            return self::ZARINPAL_SANDBOX_BASE_URL;
        }

        $baseUrl = $settings?->zarinpal_base_url;

        return (string) ($baseUrl ?? config('gamification.payments.zarinpal.base_url', 'https://payment.zarinpal.com'));
    }

    /** تبدیل تومان به ریال (DB → env؛ پیش‌فرض true یعنی ×۱۰) */
    public function zarinpalTomanToRial(): bool
    {
        $tomanToRial = $this->safeGet()?->zarinpal_toman_to_rial;

        return (bool) ($tomanToRial ?? config('gamification.payments.zarinpal.toman_to_rial', true));
    }

    /** آدرس callback زرین‌پال (DB → env؛ خالی → route ویرچوال روی APP_URL) */
    public function zarinpalCallbackUrl(): string
    {
        $callbackUrl = $this->safeGet()?->zarinpal_callback_url;

        return (string) ($callbackUrl ?? config('gamification.payments.zarinpal.callback_url', ''))
            ?: url('/api/v1/payments/zarinpal/callback');
    }

    /** توضیحات تراکنش زرین‌پال (DB → env) */
    public function zarinpalDescription(): string
    {
        $description = $this->safeGet()?->zarinpal_description;

        return (string) ($description ?? config('gamification.payments.zarinpal.description', 'خرید اشتراک پلتفرم گیمیفیکیشن'));
    }

    /**
     * خواندن امن تنظیمات — در نبود جدول (حین migrate اولیه) null برمی‌گرداند
     * تا resolverها به env برگردند و جریان اصلی هرگز نشکند.
     */
    private function safeGet(): ?SiteSettings
    {
        try {
            return SiteSettings::current();
        } catch (Throwable) {
            return null;
        }
    }
}
