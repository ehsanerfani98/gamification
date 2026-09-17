<?php

namespace App\Domain\Authentication\Actions;

use App\Domain\Authentication\Jobs\SendSmsJob;
use App\Domain\Authentication\Services\OtpCodeHasher;
use App\Models\OtpCode;

/**
 * درخواست کد یک‌بارمصرف — فصل ۲-۵ و ۸-۳ سند معماری.
 *
 * دفاع‌ها: Rate Limit پلکانی (۳/ساعت هر شماره + ۲۰/ساعت هر IP روی Route)
 * و هش امن کد. خود کد به‌صورت Idempotent فقط از طریق صف پیامک ارسال می‌شود.
 */
final class RequestOtpAction
{
    public function handle(string $phone, string $purpose = OtpCode::PURPOSE_AUTH): array
    {
        $length = (int) config('gamification.otp.code_length', 6);
        $ttl = (int) config('gamification.otp.ttl_minutes', 5);

        // random_int = تصادف رمزنگارانه امن
        $code = str_pad((string) random_int(0, 10 ** $length - 1), $length, '0', STR_PAD_LEFT);

        OtpCode::create([
            'phone' => $phone,
            'purpose' => $purpose,
            'code_hash' => OtpCodeHasher::make($code),
            'expires_at' => now()->addMinutes($ttl),
        ]);

        SendSmsJob::dispatch($phone, 'کد ورود شما به گیمیفیکیشن: '.$code);

        return [
            'sent' => true,
            'expires_in' => $ttl * 60,
            // فقط در محیط توسعه/تست؛ در production همیشه null است
            'debug_code' => app()->environment('production') ? null : $code,
        ];
    }
}
