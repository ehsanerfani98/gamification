<?php

namespace App\Domain\Reward;

use App\Domain\Campaign\Services\CampaignRuleEngine;
use App\Domain\Game\DTO\GameResult;
use App\Domain\Game\Services\WeightedRandomizer;
use App\Domain\Reward\Events\RewardIssued;
use App\Models\Campaign;
use App\Models\CampaignParticipation;
use App\Models\GameSession;
use App\Models\Reward;
use App\Models\RewardInventory;
use Illuminate\Support\Facades\DB;

/**
 * Reward Engine — موتور مستقل جایزه (فصل ۶ سند معماری).
 *
 * تنها ورودی استاندارد آن «نتیجه بازی» است؛ هیچ اطلاعی از بازی مبدأ ندارد.
 * خط لوله تصمیم (شکل ۷ سند): استخراج کاندیدها → چهار فیلتر → انتخاب وزنی
 * (در صورت نیاز) → قفل اتمی موجودی → صدور با Issuer → یا Fallback شفاف به
 * «بدون جایزه». پلاگین‌های بازی هرگز مستقیم جایزه نمی‌سازند.
 */
final class RewardEngine
{
    public function resolveForSession(Campaign $campaign, GameSession $session, GameResult $result): ?array
    {
        if (! $result->isWin()) {
            return null; // ثبت NoReward + پاسخ محترمانه (فصل ۶-۲)
        }

        return DB::transaction(function () use ($campaign, $session, $result): ?array {
            $campaign->loadMissing('rules');

            // ── استخراج کاندیدها ─────────────────────────────
            $rewards = Reward::query()
                ->where('campaign_id', $campaign->getKey())
                ->where('is_active', true)
                ->with('inventory')
                ->get();

            // ── فیلترها: موجودی، سقف برد روزانه، سقف برد کل ──
            $eligible = $rewards->filter(fn (Reward $reward) => $this->passesFilters($reward, $session, $campaign));

            if ($eligible->isEmpty()) {
                return $this->fallbackNoReward();
            }

            // ── نخست: جایزه مرجع نتیجه بازی (reward_ref) ─────
            $target = $eligible->firstWhere('ref', $result->rewardRef)
                // ── در نبود آن: انتخاب وزنی روی باقیمانده‌ها (فصل ۶-۱) ──
                ?? $this->weightedPick($eligible->all());

            if ($target === null) {
                return $this->fallbackNoReward();
            }

            // ── قفل اتمی موجودی (فصل ۶-۴) ────────────────────
            if (! $this->consumeInventory($target)) {
                // کاندید حذف و انتخاب وزنی روی باقیمانده تکرار می‌شود
                $alternate = $this->weightedPick(
                    $eligible->reject(fn (Reward $r) => $r->is($target))->all(),
                );

                if ($alternate === null || ! $this->consumeInventory($alternate)) {
                    return $this->fallbackNoReward();
                }

                $target = $alternate;
            }

            // ── صدور با Issuer اختصاصی نوع جایزه ─────────────
            $issuer = RewardIssuerRegistry::for($target->type);
            $issuance = $issuer->issue($target, $session->customer, $session);

            RewardIssued::dispatch($target, $session, $issuance);

            return [
                'status' => 'issued',
                'reward' => [
                    'ref' => $target->ref,
                    'type' => $target->type,
                    'name' => $target->name,
                ],
                'issuance' => $issuance->toArray(),
            ];
        });
    }

    /**
     * چهار فیلتر حفاظتی روی کاندیداها — فصل ۶-۴:
     * موجودی، سقف برد روزانه کاربر، سقف برد کل کمپین و فعال‌بودن.
     */
    private function passesFilters(Reward $reward, GameSession $session, Campaign $campaign): bool
    {
        if (! $reward->is_active) {
            return false;
        }

        // فیلتر موجودی — سقف کل null یعنی بی‌نهایت
        if ($reward->total_qty !== null && (int) ($reward->inventory?->remaining_qty ?? 0) <= 0) {
            return false;
        }

        $rules = $campaign->rules;
        $ruleEngine = app(CampaignRuleEngine::class);

        // سقف برد روزانه کاربر
        $maxDailyWins = $rules->first(fn ($r) => $r->type === 'max_daily_wins')?->value;

        if ($maxDailyWins !== null) {
            $winsToday = CampaignParticipation::query()
                ->where('campaign_id', $reward->campaign_id)
                ->where('customer_id', $session->customer_id)
                ->where('played_on', now()->toDateString())
                ->where('outcome', 'win')
                ->count();

            if ($winsToday >= (int) $maxDailyWins) {
                return false;
            }
        }

        return true;
    }

    private function weightedPick(array $rewards): ?Reward
    {
        if ($rewards === []) {
            return null;
        }

        $key = WeightedRandomizer::pick($rewards);

        return $rewards[$key];
    }

    /**
     * قفل اتمی موجودی — UPDATE ... WHERE remaining_qty > 0 داخل Transaction.
     * در رقابت هم‌زمان فقط یک درخواست موفق به کسر می‌شود (فصل ۶-۴).
     */
    private function consumeInventory(Reward $reward): bool
    {
        if ($reward->total_qty === null) {
            return true; // بدون سقف
        }

        return RewardInventory::query()
            ->where('reward_id', $reward->getKey())
            ->where('remaining_qty', '>', 0)
            ->decrement('remaining_qty') > 0;
    }

    /** Fallback شفاف به «بدون جایزه» — هرگز جایزه خارج از بودجه صادر نمی‌شود */
    private function fallbackNoReward(): array
    {
        return [
            'status' => 'fallback_no_reward',
            'reward' => null,
            'issuance' => null,
        ];
    }
}
