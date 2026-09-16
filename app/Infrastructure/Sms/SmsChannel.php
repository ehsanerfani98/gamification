<?php

namespace App\Infrastructure\Sms;

/**
 * قرارداد سرویس پیامک — فصل ۲-۲ و ۱۰-۳ سند معماری.
 *
 * تغییر سرویس‌دهنده OTP فقط با پیاده‌سازی یک Interface انجام می‌شود
 * و هیچ تغییر دیگری در سیستم لازم نیست (درایور تعویض‌پذیر + صف Retry).
 */
interface SmsChannel
{
    /** ارسال یک پیام متنی به شماره موبایل. پیاده‌سازی باید Idempotent و Exception-safe باشد. */
    public function send(string $phone, string $message): void;
}
