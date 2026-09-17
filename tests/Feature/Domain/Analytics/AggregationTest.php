<?php

namespace Tests\Feature\Domain\Analytics;

use App\Models\AnalyticsEvent;
use App\Models\Campaign;
use App\Models\CampaignDailyStat;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCampaigns;
use Tests\Concerns\CreatesMerchants;
use Tests\TestCase;

/**
 * تجمیع شبانه و پاک‌سازی رخدادها — فصل ۱۰ سند معماری (Sprint 5):
 * analytics:aggregate آمار روزانه هر کمپین را Idempotent بازمحاسبه و
 * رخدادهای قدیمی‌تر از دوره نگهداری را پاک می‌کند.
 */
final class AggregationTest extends TestCase
{
    use CreatesCampaigns, CreatesMerchants, RefreshDatabase;

    private Campaign $campaign;

    private array $customers = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        [, , $this->campaign] = $this->createPublishedCampaign();

        foreach (['09334440001', '09334440002'] as $phone) {
            $this->customers[$phone] = Customer::query()->create([
                'store_id' => $this->campaign->store_id,
                'phone' => $phone,
            ]);
        }
    }

    public function test_aggregates_events_into_daily_stats(): void
    {
        $today = now()->startOfDay();
        $yesterday = $today->copy()->subDay();

        [$customerA, $customerB] = array_values($this->customers);

        // امروز: View و Enter و Play و Win برای مشتری ۱
        foreach (['view', 'enter', 'play', 'win'] as $name) {
            $this->makeEvent($name, $today->copy()->addHours(10), $customerA);
        }

        // امروز: یک Redeem برای مشتری ۲ (بدون View)
        $this->makeEvent('redeem', $today->copy()->addHours(12), $customerB);

        // دیروز: دو View و یک Play (بدون برد)
        $this->makeEvent('view', $yesterday->copy()->addHours(9), $customerA);
        $this->makeEvent('view', $yesterday->copy()->addHours(11), $customerB);
        $this->makeEvent('play', $yesterday->copy()->addHours(12), $customerB);

        $this->artisan('analytics:aggregate')->assertExitCode(0);

        $todayRow = CampaignDailyStat::query()
            ->where('campaign_id', $this->campaign->id)
            ->whereDate('stat_date', $today->toDateString())
            ->firstOrFail();

        $this->assertSame(1, $todayRow->views);
        $this->assertSame(1, $todayRow->enters);
        $this->assertSame(1, $todayRow->plays);
        $this->assertSame(1, $todayRow->wins);
        $this->assertSame(1, $todayRow->redeems);
        $this->assertSame(2, $todayRow->unique_customers);

        $yesterdayRow = CampaignDailyStat::query()
            ->where('campaign_id', $this->campaign->id)
            ->whereDate('stat_date', $yesterday->toDateString())
            ->firstOrFail();

        $this->assertSame(2, $yesterdayRow->views);
        $this->assertSame(1, $yesterdayRow->plays);
        $this->assertSame(0, $yesterdayRow->wins);
        $this->assertSame(2, $yesterdayRow->unique_customers);
    }

    public function test_aggregation_is_idempotent(): void
    {
        [$customerA] = array_values($this->customers);

        $this->makeEvent('view', now()->copy()->addHours(10), $customerA);

        $this->artisan('analytics:aggregate')->assertExitCode(0);
        $this->artisan('analytics:aggregate')->assertExitCode(0);

        $this->assertDatabaseCount('campaign_daily_stats', 1);
        $this->assertDatabaseHas('campaign_daily_stats', ['views' => 1]);
    }

    public function test_events_older_than_retention_are_pruned(): void
    {
        // رخداد قدیمی — ۱۰۰ روز پیش (دوره نگهداری ۹۰ روز)
        $this->makeEvent('view', now()->subDays(100), null);
        // رخداد تازه
        $this->makeEvent('view', now()->subDays(10), null);

        $this->artisan('analytics:aggregate', ['--retention' => 90])->assertExitCode(0);

        $this->assertSame(1, AnalyticsEvent::query()->count());
        $this->assertTrue(
            AnalyticsEvent::query()->where('occurred_at', '>', now()->subDays(20))->exists(),
        );

        // رخداد قدیمی قبل از حذف، در آمار روزانه آرشیو شده است
        $this->assertDatabaseCount('campaign_daily_stats', 2);
    }

    public function test_events_without_campaign_are_not_aggregated_but_pruned(): void
    {
        // رخداد سطح Store بدون کمپین (مثل checkin)
        AnalyticsEvent::query()->create([
            'store_id' => $this->campaign->store_id,
            'name' => 'checkin',
            'customer_id' => null,
            'occurred_at' => now()->subDays(2),
            'created_at' => now(),
        ]);

        [$customerA] = array_values($this->customers);

        $this->makeEvent('view', now()->copy()->addHours(10), $customerA);

        $this->artisan('analytics:aggregate')->assertExitCode(0);

        $this->assertDatabaseCount('campaign_daily_stats', 1);
        $this->assertDatabaseCount('analytics_events', 2);
    }

    private function makeEvent(string $name, $at, ?Customer $customer): AnalyticsEvent
    {
        return AnalyticsEvent::query()->create([
            'store_id' => $this->campaign->store_id,
            'campaign_id' => $this->campaign->id,
            'customer_id' => $customer?->id,
            'name' => $name,
            'occurred_at' => $at,
            'created_at' => $at,
        ]);
    }
}
