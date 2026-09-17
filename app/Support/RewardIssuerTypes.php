<?php

namespace App\Support;

use App\Models\Reward;

/**
 * فهرست ۹ نوع جایزه MVP — فصل ۶-۲ سند معماری.
 * افزودن نوع جدید: کلاس Issuer + یک خط در RewardIssuerRegistry.
 */
final class RewardIssuerTypes
{
    /** @return array<int, string> */
    public static function all(): array
    {
        return [
            Reward::TYPE_PERCENTAGE,
            Reward::TYPE_FIXED,
            Reward::TYPE_FREE_SHIPPING,
            Reward::TYPE_FREE_PRODUCT,
            Reward::TYPE_GIFT,
            Reward::TYPE_POINTS,
            Reward::TYPE_STORE_COUPON,
            Reward::TYPE_CUSTOM,
            Reward::TYPE_NONE,
        ];
    }
}
