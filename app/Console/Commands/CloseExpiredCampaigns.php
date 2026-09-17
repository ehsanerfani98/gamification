<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;

/**
 * بستن کمپین‌های منقضی — فصل ۱۰ سند معماری (Sprint 6).
 * کمپین‌های Published/Scheduled که ends_at آن‌ها گذشته به Expired می‌روند
 * (ماشین حالت Idempotent — فصل ۴-۳). زمان‌بندی: هر ۱۰ دقیقه.
 */
final class CloseExpiredCampaigns extends Command
{
    protected $signature = 'campaigns:close-expired';

    protected $description = 'بستن کمپین‌هایی که زمان پایان آن‌ها گذشته است (Published/Scheduled → Expired)';

    public function handle(): int
    {
        // دستور نگهدارنده سیستم‌محور است — باید همه Tenantها را ببیند
        TenantContext::forget();

        $expired = Campaign::query()
            ->whereIn('status', [Campaign::STATUS_PUBLISHED, Campaign::STATUS_SCHEDULED])
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', now())
            ->get();

        foreach ($expired as $campaign) {
            $campaign->expire();
        }

        $this->info("{$expired->count()} کمپین منقضی بسته شد.");

        return self::SUCCESS;
    }
}
