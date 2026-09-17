<?php

namespace App\Domain\Analytics\Listeners;

use App\Domain\Analytics\Services\AnalyticsTracker;
use App\Domain\Customer\Events\CustomerEnteredCampaign;
use App\Models\AnalyticsEvent;

/** قیف مرحله ۲ — ورود مشتری با تأیید OTP */
final class RecordCustomerEnter
{
    public function __construct(private readonly AnalyticsTracker $tracker) {}

    public function handle(CustomerEnteredCampaign $event): void
    {
        $this->tracker->recordForCampaign(
            AnalyticsEvent::NAME_ENTER,
            $event->campaign,
            $event->customer,
            ['is_new' => $event->isNew],
        );
    }
}
