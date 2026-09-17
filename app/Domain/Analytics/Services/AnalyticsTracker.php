<?php

namespace App\Domain\Analytics\Services;

use App\Models\AnalyticsEvent;
use App\Models\Campaign;
use App\Models\Customer;
use Illuminate\Support\Facades\Log;

/**
 * ثبت رخداد رفتاری — فصل ۴-۳ و ۱۰ سند معماری.
 *
 * تنها نقطه ساخت AnalyticsEvent (Append-Only). Analytics هرگز نباید
 * جریان اصلی بازی/ورود را بشکند؛ برای همین خطاها Log و نادیده گرفته
 * می‌شوند (شکست Analytics نباید پاسخ 500 به مشتری بدهد).
 */
final class AnalyticsTracker
{
    public function record(
        string $name,
        int $storeId,
        ?int $campaignId = null,
        ?int $customerId = null,
        ?int $gameSessionId = null,
        array $properties = [],
    ): ?AnalyticsEvent {
        try {
            return AnalyticsEvent::query()->create([
                'store_id' => $storeId,
                'campaign_id' => $campaignId,
                'customer_id' => $customerId,
                'game_session_id' => $gameSessionId,
                'name' => $name,
                'properties' => $properties === [] ? null : $properties,
                'occurred_at' => now(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('analytics.record_failed', [
                'name' => $name,
                'store_id' => $storeId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /** میان‌بر برای رویدادهای متصل به کمپین */
    public function recordForCampaign(string $name, Campaign $campaign, ?Customer $customer = null, array $properties = []): ?AnalyticsEvent
    {
        return $this->record(
            name: $name,
            storeId: (int) $campaign->store_id,
            campaignId: (int) $campaign->getKey(),
            customerId: $customer?->getKey(),
            properties: $properties,
        );
    }
}
