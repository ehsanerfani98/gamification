<?php

namespace App\Domain\Game\Plugins\Support;

use App\Domain\Campaign\Services\CampaignRuleEngine;
use App\Domain\Game\Actions\StartGameSessionAction;
use App\Domain\Game\Contracts\GameInterface;
use App\Domain\Game\DTO\GameResult;
use App\Domain\Game\DTO\PlayerAction;
use App\Models\Campaign;
use App\Models\Customer;
use App\Models\GameSession;

/**
 * کلاس پایه بازی‌های «انتخابی» (Pick a Box / Pick a Card) — Sprint 4.
 *
 * جریان نتیجه (فصل ۲-۵ و ۵-۳):
 *  ۱. برد/باخت فقط با weighted RNG سمت سرور (WeightedOutcome).
 *  ۲. نتیجه روی گزینه انتخابی کاربر برجسته می‌شود — صرفاً برای انیمیشن.
 *  ۳. ایندکس انتخابی کلاینت sanitize (clamp) و هرگز در تصمیم به‌کار نمی‌رود.
 */
abstract class PickOutcomeGame implements GameInterface
{
    public function startSession(Campaign $campaign, Customer $customer): GameSession
    {
        return app(StartGameSessionAction::class)->handle($campaign, $customer);
    }

    public function resolveResult(GameSession $session, PlayerAction $action): GameResult
    {
        $config = (array) ($session->campaign->configuration->config ?? []);
        $count = $this->optionCount($config);
        $prizes = array_values(array_filter((array) ($config['prizes'] ?? []), 'is_array'));

        [$key, $prize] = WeightedOutcome::pick($prizes, (int) ($config['empty_weight'] ?? 0));

        // ایندکس انتخابی کاربر فقط برای نمایش؛ خارج از بازه → clamp (فصل ۲-۵)
        $chosen = (int) ($action->payload[$this->pickPayloadKey()] ?? 0);
        $chosen = max(0, min($count - 1, $chosen));

        $display = [
            $this->itemKey() => $count,
            'chosen_index' => $chosen,
        ];

        if ($prize !== null) {
            $display['content'] = 'prize';
            $display['prize_label'] = (string) ($prize['label'] ?? '');
            $display['reveal_index'] = $chosen;

            return GameResult::win(
                empty($prize['reward_ref']) ? null : (string) $prize['reward_ref'],
                ['outcome_key' => $key, 'chosen_index' => $chosen],
                $display,
            );
        }

        // نمایش «جایزه کجا بود» — صرفاً نمایشی و با random_int
        $others = array_values(array_diff(range(0, $count - 1), [$chosen]));
        $display['content'] = 'empty';
        $display['reveal_index'] = $others === [] ? $chosen : $others[random_int(0, count($others) - 1)];

        return GameResult::noReward(['outcome_key' => $key, 'chosen_index' => $chosen], $display);
    }

    public function remainingPlays(Campaign $campaign, Customer $customer): int
    {
        return app(CampaignRuleEngine::class)->remainingToday($campaign, $customer);
    }

    /** تعداد گزینه‌ها (جعبه/کارت) از پیکربندی — همیشه clamp شده */
    abstract protected function optionCount(array $config): int;

    /** کلید display برای تعداد گزینه‌ها: boxes | cards */
    abstract protected function itemKey(): string;

    /** کلید payload کلاینت برای ایندکس انتخابی */
    protected function pickPayloadKey(): string
    {
        return 'pick_index';
    }
}
