<?php

namespace App\Domain\Authentication\Services;

/**
 * هش امن کد OTP با کلید سرور — فصل ۲-۵ سند معماری.
 * کد هرگز به‌صورت خام ذخیره نمی‌شود.
 */
final class OtpCodeHasher
{
    public static function make(string $code): string
    {
        return hash('sha256', $code.'|'.(string) config('app.key'));
    }
}
