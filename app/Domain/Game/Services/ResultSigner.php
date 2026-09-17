<?php

namespace App\Domain\Game\Services;

/**
 * امضای HMAC نتیجه — فصل ۵-۲ سند معماری.
 *
 * پاسخ ارسالی به کلاینت قابل دستکاری و قابل اثبات است؛ در اختلاف احتمالی
 * با فروشگاه‌دار یا بررسی تقلب، رکورد Session + امضا مرجع نهایی است.
 */
final class ResultSigner
{
    public static function sign(array $result): string
    {
        return hash_hmac(
            'sha256',
            self::canonical($result),
            (string) config('gamification.signature.key'),
        );
    }

    public static function verify(array $result, string $signature): bool
    {
        return hash_equals(self::sign($result), $signature);
    }

    private static function canonical(array $result): string
    {
        return json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }
}
