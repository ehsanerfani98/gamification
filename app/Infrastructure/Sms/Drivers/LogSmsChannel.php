<?php

namespace App\Infrastructure\Sms\Drivers;

use App\Infrastructure\Sms\SmsChannel;
use Illuminate\Support\Facades\Log;

/**
 * درایور پیامک لاگ‌محور برای محیط توسعه و تست.
 * کد OTP در فایل لاگ قابل مشاهده است تا بدون سرویس پیامک واقعی بتوان جریان را کامل کرد.
 * درایور واقعی (Kavenegar / SMS.ir / Ghasedak) با همین قرارداد اضافه می‌شود.
 */
final class LogSmsChannel implements SmsChannel
{
    public function send(string $phone, string $message): void
    {
        Log::info('SMS sent via log driver', [
            'phone' => $phone,
            'message' => $message,
        ]);
    }
}
