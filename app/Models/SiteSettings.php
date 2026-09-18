<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * تنظیمات سایت — رکورد یکتا (تک‌ردیفی) که فقط Admin تغییر می‌دهد.
 *
 * سندباکس = اجرای کامل جریان بدون سرویس واقعی:
 *   sms_sandbox     → OTP بدون ارسال واقعی IPPanel (کد در پاسخ API برای تست)
 *   payment_sandbox → پرداخت با شبیه‌ساز داخلی (همان مسیر callback/verify، بدون ZarinPal واقعی)
 *
 * متغیرهای درایورها (پیامک/پرداخت): همه nullable — مقدار null یعنی پیش‌فرض
 * env/config استفاده شود؛ بنابراین پس از گذار به production، پاک‌کردن مقدار در
 * پنل کافی است تا env سرور مجدد مرجع شود.
 *
 * zarinpal_sandbox = سندباکس رسمی خود زرین‌پال (sandbox.zarinpal.com، همان API v4)
 */
#[Fillable([
    'sms_sandbox',
    'payment_sandbox',
    'sms_channel',
    'ippanel_api_key',
    'ippanel_originator',
    'ippanel_base_url',
    'payment_gateway',
    'zarinpal_merchant_id',
    'zarinpal_sandbox',
    'zarinpal_base_url',
    'zarinpal_toman_to_rial',
    'zarinpal_callback_url',
    'zarinpal_description',
])]
class SiteSettings extends Model
{
    protected function casts(): array
    {
        return [
            'sms_sandbox' => 'boolean',
            'payment_sandbox' => 'boolean',
            'zarinpal_sandbox' => 'boolean',
            'zarinpal_toman_to_rial' => 'boolean',
        ];
    }

    /**
     * رکورد یکتا — اولین بار با مقادیر پیش‌فرض (سندباکس‌ها خاموش) ساخته می‌شود.
     *
     * نکته: پس از create() مقادیر پیش‌فرض سطح دیتابیس (false) روی مدل in-memory
     * اعمال نمی‌شوند؛ رکورد تازه دوباره خوانده می‌شود تا castها روی مقدار واقعی
     * دیتابیس اجرا شوند (وگرنه مثلاً beta:status روی null کرش می‌کند).
     */
    public static function current(): self
    {
        $settings = static::query()->first();

        if ($settings !== null) {
            return $settings;
        }

        static::query()->create();

        return static::query()->firstOrFail();
    }

    /** کلید IPPanel برای نمایش در پنل — ماسک‌شده (فقط ۴ کاراکتر آخر) */
    public function maskedIppanelApiKey(): ?string
    {
        $key = (string) ($this->ippanel_api_key ?? '');

        if ($key === '') {
            return null;
        }

        $tail = substr($key, -4);

        return '••••••••'.$tail;
    }
}
