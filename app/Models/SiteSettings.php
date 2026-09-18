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
 */
#[Fillable(['sms_sandbox', 'payment_sandbox'])]
class SiteSettings extends Model
{
    protected function casts(): array
    {
        return [
            'sms_sandbox' => 'boolean',
            'payment_sandbox' => 'boolean',
        ];
    }

    /** رکورد یکتا — اولین بار با مقادیر پیش‌فرض (هر دو false) ساخته می‌شود */
    public static function current(): self
    {
        return static::query()->first() ?? static::query()->create();
    }
}
