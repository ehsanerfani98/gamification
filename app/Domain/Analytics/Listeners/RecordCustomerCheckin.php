<?php

namespace App\Domain\Analytics\Listeners;

use App\Domain\Analytics\Services\AnalyticsTracker;
use App\Domain\Retention\Events\CustomerCheckedIn;
use App\Models\AnalyticsEvent;

/** Retention — چک‌این روزانه مشتری (فقط سطح Store؛ کمپین ندارد) */
final class RecordCustomerCheckin
{
    public function __construct(private readonly AnalyticsTracker $tracker) {}

    public function handle(CustomerCheckedIn $event): void
    {
        $checkin = $event->checkin;

        $this->tracker->record(
            name: AnalyticsEvent::NAME_CHECKIN,
            storeId: (int) $checkin->store_id,
            customerId: (int) $checkin->customer_id,
            properties: ['streak' => $checkin->streak, 'points' => $event->pointsAwarded],
        );
    }
}
