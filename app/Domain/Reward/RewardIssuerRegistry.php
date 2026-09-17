<?php

namespace App\Domain\Reward;

use App\Domain\Reward\Contracts\RewardIssuerInterface;
use App\Domain\Reward\Issuers\CouponIssuer;
use App\Domain\Reward\Issuers\CustomIssuer;
use App\Domain\Reward\Issuers\GiftIssuer;
use App\Domain\Reward\Issuers\NoneIssuer;
use App\Domain\Reward\Issuers\PointsIssuer;
use App\Models\Reward;
use App\Support\Exceptions\ApiException;
use RuntimeException;

/**
 * نگاشت ۹ نوع جایزه به Issuer — فصل ۶-۲ سند معماری.
 * افزودن نوع جدید فقط یک کلاس Issuer جدید است؛ هسته تغییر نمی‌کند.
 */
final class RewardIssuerRegistry
{
    /** @var array<string, class-string<RewardIssuerInterface>> */
    private const ISSUERS = [
        Reward::TYPE_PERCENTAGE => CouponIssuer::class,
        Reward::TYPE_FIXED => CouponIssuer::class,
        Reward::TYPE_FREE_SHIPPING => CouponIssuer::class,
        Reward::TYPE_FREE_PRODUCT => CouponIssuer::class,
        Reward::TYPE_GIFT => GiftIssuer::class,
        Reward::TYPE_POINTS => PointsIssuer::class,
        Reward::TYPE_STORE_COUPON => CustomIssuer::class,
        Reward::TYPE_CUSTOM => CustomIssuer::class,
        Reward::TYPE_NONE => NoneIssuer::class,
    ];

    public static function for(string $type): RewardIssuerInterface
    {
        $class = self::ISSUERS[$type] ?? null;

        if ($class === null) {
            throw new ApiException('REWARD_TYPE_UNKNOWN', "نوع جایزه [{$type}] پشتیبانی نمی‌شود.", 500);
        }

        $issuer = app($class);

        if (! $issuer instanceof RewardIssuerInterface) {
            throw new RuntimeException("Issuer [{$class}] must implement RewardIssuerInterface.");
        }

        return $issuer;
    }

    /** @return array<int, string> */
    public static function types(): array
    {
        return array_keys(self::ISSUERS);
    }
}
