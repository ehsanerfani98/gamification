<?php

namespace App\Domain\Referral\Services;

use App\Domain\Points\Actions\EarnPointsAction;
use App\Domain\Referral\Events\ReferralRewardGranted;
use App\Models\Customer;
use App\Models\PointTransaction;
use App\Models\Referral;

/**
 * جوایز پله‌ای دعوت دوستان — فصل ۱۰ سند معماری (۱/۳/۵ دعوت).
 *
 * با هر ثبت دعوت جدید، شمارش دعوت‌های دعوت‌کننده یک واحد رشد می‌کند؛
 * اگر همین تعداد با یکی از آستانه‌های پیکربندی برابر باشد، جایزه همان
 * پله یک‌بار پرداخت می‌شود (چون شمارش فقط واحدی رشد می‌کند، هیچ پله‌ای
 * حتی در رقابت هم‌زمان دوبار پرداخت نمی‌شود).
 */
final class ReferralTierAwarder
{
    public function __construct(private readonly EarnPointsAction $earnPoints) {}

    /** آستانه‌ها به امتیاز — از config/gamification.php */
    public static function tiers(): array
    {
        return (array) config('gamification.referral.tiers', [1 => 100, 3 => 300, 5 => 500]);
    }

    /** @return array{tier: int, points: int, total: int, balance: int}|null پرداخت‌شده در این فراخوانی */
    public function process(Customer $referrer, Referral $newReferral): ?array
    {
        $total = Referral::query()
            ->where('store_id', $referrer->store_id)
            ->where('referrer_id', $referrer->getKey())
            ->count();

        $points = (int) ($this->tiers()[$total] ?? 0);

        if ($points === 0) {
            return null;
        }

        $balance = $this->earnPoints->handle(
            $referrer,
            $points,
            PointTransaction::TYPE_EARN,
            'referral',
            (int) $newReferral->getKey(),
            "جایزه دعوت دوستان — پله {$total} دعوت",
        );

        ReferralRewardGranted::dispatch($referrer, $points, $total, $total);

        return ['tier' => $total, 'points' => $points, 'total' => $total, 'balance' => $balance];
    }
}
