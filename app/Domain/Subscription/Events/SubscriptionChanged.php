<?php

namespace App\Domain\Subscription\Events;

use App\Models\Subscription;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Domain Event تغییر وضعیت اشتراک — فصل ۴-۳ سند معماری.
 * مصرف‌کنندگان: Game Engine (به‌روزرسانی Feature Gating) و Billing.
 * هیچ دامنه‌ای نباید مستقیم به Subscription وابسته شود؛ فقط به همین Event گوش می‌دهد.
 */
final class SubscriptionChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Subscription $subscription,
        public readonly string $from,
        public readonly string $to,
    ) {}
}
