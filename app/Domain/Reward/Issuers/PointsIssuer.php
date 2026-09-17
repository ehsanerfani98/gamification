<?php

namespace App\Domain\Reward\Issuers;

use App\Domain\Points\Actions\EarnPointsAction;
use App\Domain\Reward\Contracts\RewardIssuerInterface;
use App\Domain\Reward\DTO\IssuanceResult;
use App\Models\Customer;
use App\Models\GameSession;
use App\Models\PointTransaction;
use App\Models\Reward;

/** Issuer امتیاز — Point Transaction مثبت در Ledger (فصل ۶-۲) */
final class PointsIssuer implements RewardIssuerInterface
{
    public static function type(): string
    {
        return Reward::TYPE_POINTS;
    }

    public function issue(Reward $reward, Customer $customer, GameSession $session): IssuanceResult
    {
        $points = (int) ($reward->params['points'] ?? 0);

        $balance = app(EarnPointsAction::class)->handle(
            $customer,
            $points,
            PointTransaction::TYPE_EARN,
            'game_session',
            (int) $session->getKey(),
            'برد در کمپین: '.$reward->name,
        );

        return new IssuanceResult(IssuanceResult::KIND_POINTS, [
            'points' => $points,
            'balance' => $balance,
        ]);
    }
}
