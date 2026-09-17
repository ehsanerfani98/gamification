<?php

namespace App\Domain\Coupon\Services;

use App\Models\Coupon;

/**
 * تولید کد کوپن یکتا و غیرقابل حدس — فصل ۶-۴ سند معماری.
 * الفبای بدون ابهام (بدون 0/O و 1/I)، طول پیش‌فرض ۸ و پشتیبانی از Prefix برند.
 */
final class CouponCodeGenerator
{
    public static function generate(?string $brandPrefix = null): string
    {
        $alphabet = (string) config('gamification.coupon.alphabet', 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789');
        $length = (int) config('gamification.coupon.length', 8);
        $prefix = $brandPrefix ?? (string) config('gamification.coupon.prefix', '');

        do {
            $body = '';

            for ($i = 0; $i < $length; $i++) {
                $body .= $alphabet[random_int(0, mb_strlen($alphabet) - 1)];
            }

            $code = $prefix.$body;
        } while (Coupon::query()->where('code', $code)->exists());

        return $code;
    }
}
