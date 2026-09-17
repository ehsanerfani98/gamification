<?php

namespace App\Console\Commands;

use App\Models\AnalyticsEvent;
use App\Models\CampaignDailyStat;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;

/**
 * تجمیع شبانه و پاک‌سازی رخدادها — فصل ۱۰ سند معماری (Sprint 5).
 *
 *  ۱) برای هر (کمپین، روز) رخدادها را در campaign_daily_stats بازمحاسبه
 *     و به‌صورت Idempotent ذخیره می‌کند (unique: campaign, stat_date)
 *  ۲) رخدادهای قدیمی‌تر از دوره نگهداری (پیش‌فرض ۹۰ روز) را پاک می‌کند
 *
 * زمان‌بندی: routes/console.php → ساعت ۰۳:۰۰ بامداد.
 */
final class AggregateAnalytics extends Command
{
    protected $signature = 'analytics:aggregate {--retention= : دوره نگهداری رخدادها به روز (پیش‌فرض از config)}';

    protected $description = 'تجمیع رخدادهای Analytics در آمار روزانه کمپین‌ها و پاک‌سازی رخدادهای قدیمی';

    public function handle(): int
    {
        // دستور نگهدارنده سیستم‌محور است — باید همه Tenantها را ببیند
        TenantContext::forget();

        $retention = (int) ($this->option('retention') ?: config('gamification.analytics.retention_days', 90));

        $this->aggregateDailyStats();
        $deleted = $this->pruneEvents($retention);

        $this->info("تجمیع کامل شد — {$deleted} رخداد قدیمی‌تر از {$retention} روز پاک شد.");

        return self::SUCCESS;
    }

    private function aggregateDailyStats(): void
    {
        $rows = AnalyticsEvent::query()
            ->whereNotNull('campaign_id')
            ->selectRaw(implode(', ', [
                'campaign_id',
                'store_id',
                'date(occurred_at) AS stat_date',
                "SUM(CASE WHEN name = 'view' THEN 1 ELSE 0 END) AS views",
                "SUM(CASE WHEN name = 'enter' THEN 1 ELSE 0 END) AS enters",
                "SUM(CASE WHEN name = 'play' THEN 1 ELSE 0 END) AS plays",
                "SUM(CASE WHEN name = 'win' THEN 1 ELSE 0 END) AS wins",
                "SUM(CASE WHEN name = 'redeem' THEN 1 ELSE 0 END) AS redeems",
                "SUM(CASE WHEN name = 'checkin' THEN 1 ELSE 0 END) AS checkins",
                'COUNT(DISTINCT customer_id) AS unique_customers',
            ]))
            ->groupBy('campaign_id', 'store_id', 'stat_date')
            ->get();

        foreach ($rows as $row) {
            // whereDate برای تطبیق امن بین SQLite و PostgreSQL (فصل ۳-۵)
            $stat = CampaignDailyStat::query()
                ->where('campaign_id', (int) $row->campaign_id)
                ->whereDate('stat_date', $row->stat_date)
                ->first();

            if ($stat !== null) {
                $stat->fill([
                    'store_id' => (int) $row->store_id,
                    'views' => (int) $row->views,
                    'enters' => (int) $row->enters,
                    'plays' => (int) $row->plays,
                    'wins' => (int) $row->wins,
                    'redeems' => (int) $row->redeems,
                    'checkins' => (int) $row->checkins,
                    'unique_customers' => (int) $row->unique_customers,
                ])->save();

                continue;
            }

            CampaignDailyStat::query()->create([
                'campaign_id' => (int) $row->campaign_id,
                'stat_date' => $row->stat_date,
                'store_id' => (int) $row->store_id,
                'views' => (int) $row->views,
                'enters' => (int) $row->enters,
                'plays' => (int) $row->plays,
                'wins' => (int) $row->wins,
                'redeems' => (int) $row->redeems,
                'checkins' => (int) $row->checkins,
                'unique_customers' => (int) $row->unique_customers,
            ]);
        }

        $this->info("آمار روزانه برای {$rows->count()} سطر (کمپین × روز) بازمحاسبه شد.");
    }

    private function pruneEvents(int $retentionDays): int
    {
        $cut = now()->subDays($retentionDays);

        return AnalyticsEvent::query()->prunable($cut)->delete();
    }
}
