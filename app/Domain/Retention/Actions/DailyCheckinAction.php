<?php

namespace App\Domain\Retention\Actions;

use App\Domain\Points\Actions\EarnPointsAction;
use App\Domain\Retention\Events\CustomerCheckedIn;
use App\Models\Customer;
use App\Models\CustomerCheckin;
use App\Models\PointTransaction;
use Illuminate\Support\Facades\DB;

/**
 * چک‌این روزانه و زنجیره Streak — فصل ۱۰ سند معماری (Retention).
 *
 * قواعد:
 *  - یکتایی (customer, checkin_date) → چک‌این تکراری در همان روز
 *    Idempotent است (already) و امتیاز جدیدی نمی‌سازد
 *  - چک‌این دیروز موجود باشد → streak + ۱؛ در غیر این صورت streak = ۱
 *  - امتیاز پایه + پاداش رسیدن به آستانه‌های زنجیره (۷/۱۴/۳۰ روز) —
 *    همه پرداخت‌ها فقط از طریق Ledger انجام می‌شود (فصل ۶-۴)
 *
 * @return array{already: bool, streak: int, points_awarded: int, balance: int}
 */
final class DailyCheckinAction
{
    public function __construct(private readonly EarnPointsAction $earnPoints) {}

    public function handle(Customer $customer): array
    {
        $today = now()->toDateString();

        $existing = CustomerCheckin::query()
            ->where('customer_id', $customer->getKey())
            ->whereDate('checkin_date', $today)
            ->first();

        if ($existing !== null) {
            return [
                'already' => true,
                'streak' => $existing->streak,
                'points_awarded' => 0,
                'balance' => $this->earnPoints->balance($customer),
            ];
        }

        [$checkin, $balance] = DB::transaction(function () use ($customer, $today): array {
            $last = CustomerCheckin::query()
                ->where('customer_id', $customer->getKey())
                ->orderByDesc('checkin_date')
                ->first();

            $streak = ($last !== null && $last->checkin_date->isYesterday())
                ? $last->streak + 1
                : 1;

            $base = (int) config('gamification.checkin.base_points', 10);
            $bonusTiers = (array) config('gamification.checkin.streak_bonus', [7 => 50, 14 => 100, 30 => 200]);
            $bonus = (int) ($bonusTiers[$streak] ?? 0);
            $points = $base + $bonus;

            $checkin = CustomerCheckin::query()->create([
                'store_id' => $customer->store_id,
                'customer_id' => $customer->getKey(),
                'checkin_date' => $today,
                'streak' => $streak,
                'points_awarded' => $points,
                'created_at' => now(),
            ]);

            $balance = $this->earnPoints->handle(
                $customer,
                $points,
                PointTransaction::TYPE_EARN,
                'checkin',
                (int) $checkin->getKey(),
                "چک‌این روزانه — روز {$streak} زنجیره",
            );

            return [$checkin, $balance];
        });

        CustomerCheckedIn::dispatch($checkin, $checkin->points_awarded, $checkin->points_awarded > (int) config('gamification.checkin.base_points', 10));

        return [
            'already' => false,
            'streak' => $checkin->streak,
            'points_awarded' => $checkin->points_awarded,
            'balance' => $balance,
        ];
    }
}
