<?php

namespace App\Domain\Reward\Issuers;

use App\Domain\Coupon\Events\CouponCreated;
use App\Domain\Coupon\Services\CouponCodeGenerator;
use App\Domain\Reward\Contracts\RewardIssuerInterface;
use App\Domain\Reward\DTO\IssuanceResult;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\GameSession;
use App\Models\Reward;

/**
 * Issuer کوپن‌محور — چهار نوع جایزه: تخفیف درصدی، مبلغ ثابت،
 * ارسال رایگان و محصول رایگان (فصل ۶-۲ سند معماری).
 */
final class CouponIssuer implements RewardIssuerInterface
{
    public static function type(): string
    {
        return 'coupon';
    }

    /** @return array<int, string> انواع جایزه‌ای که این Issuer صادر می‌کند */
    public static function handles(): array
    {
        return [
            Reward::TYPE_PERCENTAGE,
            Reward::TYPE_FIXED,
            Reward::TYPE_FREE_SHIPPING,
            Reward::TYPE_FREE_PRODUCT,
        ];
    }

    public function issue(Reward $reward, Customer $customer, GameSession $session): IssuanceResult
    {
        $params = (array) ($reward->params ?? []);

        $value = match ($reward->type) {
            Reward::TYPE_PERCENTAGE => [
                'percent' => (int) ($params['percent'] ?? 5),
                'max_amount' => $params['max_amount'] ?? null,
            ],
            Reward::TYPE_FIXED => [
                'amount_irt' => (int) ($params['amount_irt'] ?? 0),
            ],
            Reward::TYPE_FREE_PRODUCT => [
                'sku' => $params['sku'] ?? null,
            ],
            default => (object) [], // free_shipping
        };

        $coupon = Coupon::query()->create([
            'store_id' => $reward->store_id,
            'campaign_id' => $reward->campaign_id,
            'reward_id' => $reward->getKey(),
            'customer_id' => $customer->getKey(),
            'code' => CouponCodeGenerator::generate($params['prefix'] ?? null),
            'type' => $reward->type,
            'value' => $value,
            'expires_at' => now()->addDays((int) ($params['expires_days'] ?? 14)),
            'status' => Coupon::STATUS_ISSUED,
        ]);

        CouponCreated::dispatch($coupon);

        return new IssuanceResult(IssuanceResult::KIND_COUPON, [
            'coupon_id' => $coupon->id,
            'code' => $coupon->code,
            'type' => $coupon->type,
            'value' => $coupon->value,
            'expires_at' => $coupon->expires_at?->toIso8601String(),
        ]);
    }
}
