<?php

namespace App\Domain\Reward\Issuers;

use App\Domain\Reward\Contracts\RewardIssuerInterface;
use App\Domain\Reward\DTO\IssuanceResult;
use App\Models\Customer;
use App\Models\GameSession;
use App\Models\Reward;

/** Issuer «بدون جایزه» — پاسخ محترمانه + ثبت نتیجه NoReward (فصل ۶-۲) */
final class NoneIssuer implements RewardIssuerInterface
{
    public static function type(): string
    {
        return Reward::TYPE_NONE;
    }

    public function issue(Reward $reward, Customer $customer, GameSession $session): IssuanceResult
    {
        return new IssuanceResult(IssuanceResult::KIND_NONE, [
            'message' => 'این بار شانس با شما نبود؛ دوباره تلاش کنید!',
        ]);
    }
}
