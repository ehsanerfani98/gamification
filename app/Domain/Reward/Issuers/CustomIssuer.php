<?php

namespace App\Domain\Reward\Issuers;

use App\Domain\Reward\Contracts\RewardIssuerInterface;
use App\Domain\Reward\DTO\IssuanceResult;
use App\Models\Customer;
use App\Models\GameSession;
use App\Models\Reward;

/** Issuer جایزه سفارشی/کوپن فروشگاه — رکورد Custom با دستورالعمل نمایش (فصل ۶-۲) */
final class CustomIssuer implements RewardIssuerInterface
{
    public static function type(): string
    {
        return Reward::TYPE_CUSTOM;
    }

    /** @return array<int, string> */
    public static function handles(): array
    {
        return [Reward::TYPE_CUSTOM, Reward::TYPE_STORE_COUPON];
    }

    public function issue(Reward $reward, Customer $customer, GameSession $session): IssuanceResult
    {
        $params = (array) ($reward->params ?? []);

        return new IssuanceResult(IssuanceResult::KIND_CUSTOM, [
            'message' => $params['message'] ?? $reward->name,
            'instructions' => $params['instructions'] ?? null,
            'external_code' => $params['external_code'] ?? null, // کد کوپن فروشگاه‌دار
        ]);
    }
}
