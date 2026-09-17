<?php

namespace App\Domain\Analytics\Actions;

use App\Models\AnalyticsEvent;
use App\Models\Campaign;

/**
 * گزارش قیف کمپین — فصل ۱۰ سند معماری:
 * View → Enter → Play → Win → Redeem به‌همراه نرخ تبدیل هر گام،
 * مشتری یکتا و سری زمانی روزانه (۳۰ روز اخیر).
 *
 * منبع حقیقت زنده، جدول analytics_events است؛ تجمیع شبانه فقط
 * برای نمودارهای بلندمدت در campaign_daily_stats ذخیره می‌شود.
 */
final class BuildCampaignFunnelReport
{
    public function handle(Campaign $campaign): array
    {
        $base = AnalyticsEvent::query()
            ->where('campaign_id', $campaign->getKey())
            ->where('occurred_at', '>=', $campaign->created_at ?? now()->subYears(5));

        $totals = (clone $base)
            ->selectRaw(implode(', ', [
                "SUM(CASE WHEN name = 'view' THEN 1 ELSE 0 END) AS views",
                "SUM(CASE WHEN name = 'enter' THEN 1 ELSE 0 END) AS enters",
                "SUM(CASE WHEN name = 'play' THEN 1 ELSE 0 END) AS plays",
                "SUM(CASE WHEN name = 'win' THEN 1 ELSE 0 END) AS wins",
                "SUM(CASE WHEN name = 'redeem' THEN 1 ELSE 0 END) AS redeems",
            ]))
            ->first();

        $uniqueCustomers = (clone $base)
            ->whereNotNull('customer_id')
            ->distinct('customer_id')
            ->count('customer_id');

        $from = now()->subDays(29)->startOfDay();

        $daily = (clone $base)
            ->where('occurred_at', '>=', $from)
            ->selectRaw(implode(', ', [
                'date(occurred_at) AS stat_date',
                "SUM(CASE WHEN name = 'view' THEN 1 ELSE 0 END) AS views",
                "SUM(CASE WHEN name = 'enter' THEN 1 ELSE 0 END) AS enters",
                "SUM(CASE WHEN name = 'play' THEN 1 ELSE 0 END) AS plays",
                "SUM(CASE WHEN name = 'win' THEN 1 ELSE 0 END) AS wins",
                "SUM(CASE WHEN name = 'redeem' THEN 1 ELSE 0 END) AS redeems",
            ]))
            ->groupBy('stat_date')
            ->orderBy('stat_date')
            ->get();

        $funnel = [
            'view' => (int) ($totals->views ?? 0),
            'enter' => (int) ($totals->enters ?? 0),
            'play' => (int) ($totals->plays ?? 0),
            'win' => (int) ($totals->wins ?? 0),
            'redeem' => (int) ($totals->redeems ?? 0),
        ];

        return [
            'campaign' => [
                'id' => $campaign->id,
                'title' => $campaign->title,
                'slug' => $campaign->slug,
                'status' => $campaign->status,
            ],
            'funnel' => $funnel,
            'rates' => $this->rates($funnel),
            'totals' => [
                'unique_customers' => $uniqueCustomers,
            ],
            'daily' => $daily->map(fn ($row): array => [
                'date' => (string) $row->stat_date,
                'views' => (int) $row->views,
                'enters' => (int) $row->enters,
                'plays' => (int) $row->plays,
                'wins' => (int) $row->wins,
                'redeems' => (int) $row->redeems,
            ])->values()->all(),
        ];
    }

    /** نرخ تبدیل هر گام نسبت به گام قبلی — درصد با یک اعشار */
    private function rates(array $funnel): array
    {
        $step = fn (string $from, string $to): float => $funnel[$from] > 0
            ? round($funnel[$to] / $funnel[$from] * 100, 1)
            : 0.0;

        return [
            'enter_rate' => $step('view', 'enter'),
            'play_rate' => $step('enter', 'play'),
            'win_rate' => $step('play', 'win'),
            'redeem_rate' => $step('win', 'redeem'),
        ];
    }
}
