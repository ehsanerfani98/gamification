<?php

namespace App\Domain\Analytics\Listeners;

use App\Domain\Analytics\Services\AnalyticsTracker;
use App\Domain\Game\DTO\GameResult;
use App\Domain\Game\Events\GamePlayed;
use App\Models\AnalyticsEvent;

/**
 * قیف مرحله ۳ و ۴ — بازی و برد واقعی.
 * «برد» فقط برای نتیجه win ثبت می‌شود؛ برد بدون بودجه که به
 * no_reward برمی‌گردد، در قیف win شمرده نمی‌شود (سازگار با نتیجه نهایی).
 */
final class RecordGamePlay
{
    public function __construct(private readonly AnalyticsTracker $tracker) {}

    public function handle(GamePlayed $event): void
    {
        $session = $event->session;

        $this->tracker->record(
            name: AnalyticsEvent::NAME_PLAY,
            storeId: (int) $session->store_id,
            campaignId: (int) $session->campaign_id,
            customerId: (int) $session->customer_id,
            gameSessionId: (int) $session->getKey(),
            properties: ['game_code' => $session->campaign?->game?->code],
        );

        if ($event->result->outcome === GameResult::OUTCOME_WIN) {
            $this->tracker->record(
                name: AnalyticsEvent::NAME_WIN,
                storeId: (int) $session->store_id,
                campaignId: (int) $session->campaign_id,
                customerId: (int) $session->customer_id,
                gameSessionId: (int) $session->getKey(),
                properties: ['reward_ref' => $event->result->rewardRef],
            );
        }
    }
}
