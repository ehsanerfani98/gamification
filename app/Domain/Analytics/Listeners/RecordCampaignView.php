<?php

namespace App\Domain\Analytics\Listeners;

use App\Domain\Analytics\Services\AnalyticsTracker;
use App\Domain\Campaign\Events\CampaignViewed;
use App\Models\AnalyticsEvent;

/** قیف مرحله ۱ — بازدید لندینگ کمپین (GET /api/v1/c/{slug}) */
final class RecordCampaignView
{
    public function __construct(private readonly AnalyticsTracker $tracker) {}

    public function handle(CampaignViewed $event): void
    {
        $this->tracker->recordForCampaign(
            AnalyticsEvent::NAME_VIEW,
            $event->campaign,
        );
    }
}
