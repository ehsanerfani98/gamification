<?php

namespace App\Support\Audit\Listeners;

use App\Domain\Subscription\Events\SubscriptionChanged;
use App\Models\AuditLog;

/** Audit تغییر وضعیت اشتراک — فصل ۱۰ سند معماری (Sprint 6) */
final class AuditSubscriptionChanged
{
    public function handle(SubscriptionChanged $event): void
    {
        AuditLog::record('subscription.changed', null, $event->subscription, [
            'from' => $event->from,
            'to' => $event->to,
            'store_id' => (int) $event->subscription->store_id,
        ]);
    }
}
