<?php

namespace App\Domain\Reward\Issuers;

use App\Domain\Reward\Contracts\RewardIssuerInterface;
use App\Domain\Reward\DTO\IssuanceResult;
use App\Models\Customer;
use App\Models\GameSession;
use App\Models\Reward;

/**
 * Issuer هدیه فیزیکی/دیجیتال — ثبت درخواست هدیه با وضعیت پیگیری
 * (ارسال در Sprint 5 با Notification تکمیل می‌شود — فصل ۶-۲).
 */
final class GiftIssuer implements RewardIssuerInterface
{
    public static function type(): string
    {
        return Reward::TYPE_GIFT;
    }

    public function issue(Reward $reward, Customer $customer, GameSession $session): IssuanceResult
    {
        $params = (array) ($reward->params ?? []);

        return new IssuanceResult(IssuanceResult::KIND_GIFT, [
            'gift_ref' => $params['gift_ref'] ?? null,
            'status' => 'requested',
            'instructions' => $params['instructions'] ?? 'برای دریافت هدیه با پشتیبانی فروشگاه تماس بگیرید.',
        ]);
    }
}
