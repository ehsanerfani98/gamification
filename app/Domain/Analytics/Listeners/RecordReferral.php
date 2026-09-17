<?php

namespace App\Domain\Analytics\Listeners;

use App\Domain\Analytics\Services\AnalyticsTracker;
use App\Domain\Referral\Events\ReferralRegistered;
use App\Models\AnalyticsEvent;

/** Retention — ثبت دعوت موفق دوست */
final class RecordReferral
{
    public function __construct(private readonly AnalyticsTracker $tracker) {}

    public function handle(ReferralRegistered $event): void
    {
        $referral = $event->referral;

        $this->tracker->record(
            name: AnalyticsEvent::NAME_REFERRAL,
            storeId: (int) $referral->store_id,
            customerId: (int) $referral->referrer_id,
            properties: ['invited_id' => (int) $referral->invited_id],
        );
    }
}
