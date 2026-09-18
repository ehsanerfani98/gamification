<?php

namespace Tests\Feature\Domain\Analytics;

use App\Models\AnalyticsEvent;
use App\Models\Campaign;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Reward;
use App\Models\RewardInventory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCampaigns;
use Tests\Concerns\CreatesMerchants;
use Tests\TestCase;

/**
 * قیف Analytics کمپین — فصل ۱۰ سند معماری (Sprint 5):
 * View → Enter → Play → Win → Redeem به‌همراه نرخ تبدیل و مشتری یکتا.
 * جریان کامل واقعی از API عبور می‌کند تا رخدادهای دامنه واقعاً صادر شوند.
 */
final class CampaignAnalyticsTest extends TestCase
{
    use CreatesCampaigns, CreatesMerchants, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_full_funnel_is_tracked_and_reported(): void
    {
        // کمپین با برد تضمینی: یک Segment با وزن ۱۰۰ و جایزه کوپن موجود
        [$token, $store] = $this->createMerchantWithStore();

        $create = $this->withToken($token)
            ->withHeader('X-Store-Id', (string) $store['id'])
            ->postJson('/api/v1/campaigns', [
                'title' => 'کمپین قیف',
                'game_code' => 'wheel',
                'config' => [
                    'segments' => [
                        ['label' => 'تخفیف', 'weight' => 100, 'reward_ref' => 'r-coupon', 'color' => '#F4B860'],
                        // هر دو Segment به یک جایزه اشاره می‌کنند → برد قطعی
                        ['label' => 'جایزه ویژه', 'weight' => 1, 'reward_ref' => 'r-coupon', 'color' => '#61C0BF'],
                    ],
                    'animation_duration_ms' => 4200,
                ],
                'rules' => [],
            ])->assertCreated();

        $campaignId = $create->json('data.campaign.id');

        $this->withToken($token)
            ->withHeader('X-Store-Id', (string) $store['id'])
            ->postJson("/api/v1/campaigns/{$campaignId}/rewards", [
                'ref' => 'r-coupon',
                'type' => 'percentage',
                'name' => 'تخفیف ۲۰٪',
                'params' => ['percent' => 20],
                'weight' => 1,
                'total_qty' => 10,
            ])->assertCreated();

        $this->withToken($token)
            ->withHeader('X-Store-Id', (string) $store['id'])
            ->postJson("/api/v1/campaigns/{$campaignId}/publish")->assertOk();

        $campaign = Campaign::query()->findOrFail($campaignId);

        // مرحله ۱ — View
        $this->getJson("/api/v1/c/{$campaign->slug}")->assertOk();

        // مرحله ۲ — Enter با جریان واقعی OTP
        $phone = '09332224455';
        $code = $this->postJson("/api/v1/c/{$campaign->slug}/otp", ['phone' => $phone])
            ->assertOk()
            ->json('data.debug_code');

        $enter = $this->postJson("/api/v1/c/{$campaign->slug}/enter", [
            'phone' => $phone,
            'code' => $code,
        ])->assertOk();

        $customerToken = $enter->json('data.token');

        // مرحله ۳ و ۴ — Play و Win
        $session = $this->withToken($customerToken)
            ->postJson('/api/v1/play/sessions', ['campaign_slug' => $campaign->slug])
            ->assertOk();

        $playToken = $session->json('data.session.play_token');

        $result = $this->withToken($customerToken)
            ->postJson("/api/v1/play/sessions/{$playToken}/action", [
                'type' => 'spin',
                'payload' => [],
            ])->assertOk();

        $this->assertSame('win', $result->json('data.result.outcome'));

        // مرحله ۵ — Redeem در فروشگاه
        $couponCode = Coupon::query()->where('campaign_id', $campaignId)->firstOrFail()->code;

        $this->withToken($token)
            ->withHeader('X-Store-Id', (string) $store['id'])
            ->postJson('/api/v1/coupons/redeem', ['code' => $couponCode])->assertOk();

        // گزارش Analytics
        $report = $this->withToken($token)
            ->withHeader('X-Store-Id', (string) $store['id'])
            ->getJson("/api/v1/campaigns/{$campaignId}/analytics")
            ->assertOk()
            ->json('data');

        $this->assertSame(1, $report['funnel']['view']);
        $this->assertSame(1, $report['funnel']['enter']);
        $this->assertSame(1, $report['funnel']['play']);
        $this->assertSame(1, $report['funnel']['win']);
        $this->assertSame(1, $report['funnel']['redeem']);

        $this->assertSame(100.0, (float) $report['rates']['enter_rate']);
        $this->assertSame(100.0, (float) $report['rates']['play_rate']);
        $this->assertSame(100.0, (float) $report['rates']['win_rate']);
        $this->assertSame(100.0, (float) $report['rates']['redeem_rate']);

        $this->assertSame(1, $report['totals']['unique_customers']);
        $this->assertSame($campaign->slug, $report['campaign']['slug']);

        // سری زمانی روزانه — امروز با همه شمارنده‌ها
        $today = $report['daily'][0] ?? null;
        $this->assertNotNull($today);
        $this->assertSame(1, $today['views']);
        $this->assertSame(1, $today['enters']);
        $this->assertSame(1, $today['plays']);
        $this->assertSame(1, $today['wins']);
        $this->assertSame(1, $today['redeems']);
    }

    public function test_campaign_without_events_reports_zero_funnel(): void
    {
        [$token, $store] = $this->createMerchantWithStore();

        $campaignId = $this->withToken($token)
            ->withHeader('X-Store-Id', (string) $store['id'])
            ->postJson('/api/v1/campaigns', [
                'title' => 'پیش‌نویس بدون رخداد',
                'game_code' => 'wheel',
                'config' => [
                    'segments' => [
                        ['label' => 'بدون جایزه', 'weight' => 100, 'reward_ref' => 'r-nonexistent', 'color' => '#888888'],
                        ['label' => 'هم بدون جایزه', 'weight' => 1, 'reward_ref' => null, 'color' => '#999999'],
                    ],
                ],
                'rules' => [],
            ])->assertCreated()->json('data.campaign.id');

        $report = $this->withToken($token)
            ->withHeader('X-Store-Id', (string) $store['id'])
            ->getJson("/api/v1/campaigns/{$campaignId}/analytics")
            ->assertOk()
            ->json('data');

        $this->assertSame(0, $report['funnel']['view']);
        $this->assertSame(0, $report['funnel']['enter']);
        $this->assertSame(0, $report['funnel']['play']);
        $this->assertSame(0, $report['funnel']['win']);
        $this->assertSame(0, $report['funnel']['redeem']);

        // نرخ تبدیل در حالت بدون داده صفر است — نه تقسیم بر صفر
        $this->assertSame(0.0, (float) $report['rates']['enter_rate']);
        $this->assertSame(0, $report['totals']['unique_customers']);
        $this->assertSame([], $report['daily']);
    }

    public function test_analytics_is_tenant_isolated(): void
    {
        [$ownerToken, $ownerStore, $campaign] = $this->createPublishedCampaign();

        // رخداد در Store صاحب کمپین
        AnalyticsEvent::query()->create([
            'store_id' => $campaign->store_id,
            'campaign_id' => $campaign->id,
            'name' => 'view',
            'occurred_at' => now(),
            'created_at' => now(),
        ]);

        // فروشگاه دیگر — دسترسی به کمپین Store دیگر 404 است
        [$otherToken, $otherStore] = $this->createMerchantWithStore('09124440000');

        $this->withToken($otherToken)
            ->withHeader('X-Store-Id', (string) $otherStore['id'])
            ->getJson("/api/v1/campaigns/{$campaign->id}/analytics")
            ->assertNotFound();

        // صاحب کمپین رخداد خودش را می‌بیند
        $this->withToken($ownerToken)
            ->withHeader('X-Store-Id', (string) $ownerStore['id'])
            ->getJson("/api/v1/campaigns/{$campaign->id}/analytics")
            ->assertOk()
            ->assertJsonPath('data.funnel.view', 1);
    }

    public function test_win_without_budget_is_not_counted_as_win(): void
    {
        // برد بدون موجودی → نتیجه نهایی no_reward → قیف win ثبت نمی‌شود
        [$token, $store] = $this->createMerchantWithStore();

        $campaignId = $this->withToken($token)
            ->withHeader('X-Store-Id', (string) $store['id'])
            ->postJson('/api/v1/campaigns', [
                'title' => 'کمپین بدون بودجه',
                'game_code' => 'wheel',
                'config' => [
                    'segments' => [
                        ['label' => 'تخفیف', 'weight' => 100, 'reward_ref' => 'r-empty', 'color' => '#F4B860'],
                        ['label' => 'بدون جایزه', 'weight' => 1, 'reward_ref' => null, 'color' => '#888888'],
                    ],
                ],
                'rules' => [],
            ])->assertCreated()->json('data.campaign.id');

        $this->withToken($token)
            ->withHeader('X-Store-Id', (string) $store['id'])
            ->postJson("/api/v1/campaigns/{$campaignId}/rewards", [
                'ref' => 'r-empty',
                'type' => 'percentage',
                'name' => 'تخفیف بدون موجودی',
                'params' => ['percent' => 10],
                'weight' => 1,
                'total_qty' => 1,
            ])->assertCreated();

        // بودجه به پایان رسیده — موجودی صفر (Fallback شفاف — فصل ۶-۱)
        RewardInventory::query()
            ->where('reward_id', Reward::query()->where('campaign_id', $campaignId)->where('ref', 'r-empty')->firstOrFail()->id)
            ->update(['remaining_qty' => 0]);

        $this->withToken($token)
            ->withHeader('X-Store-Id', (string) $store['id'])
            ->postJson("/api/v1/campaigns/{$campaignId}/publish")->assertOk();

        $campaign = Campaign::query()->findOrFail($campaignId);

        $this->getJson("/api/v1/c/{$campaign->slug}")->assertOk();

        $phone = '09332227788';
        $code = $this->postJson("/api/v1/c/{$campaign->slug}/otp", ['phone' => $phone])
            ->assertOk()->json('data.debug_code');

        $customerToken = $this->postJson("/api/v1/c/{$campaign->slug}/enter", [
            'phone' => $phone,
            'code' => $code,
        ])->assertOk()->json('data.token');

        $playToken = $this->withToken($customerToken)
            ->postJson('/api/v1/play/sessions', ['campaign_slug' => $campaign->slug])
            ->assertOk()->json('data.session.play_token');

        $result = $this->withToken($customerToken)
            ->postJson("/api/v1/play/sessions/{$playToken}/action", [
                'type' => 'spin',
                'payload' => [],
            ])->assertOk();

        // برد بدون بودجه به no_reward برمی‌گردد (Fallback شفاف — فصل ۶-۱)
        $this->assertSame('no_reward', $result->json('data.result.outcome'));

        $report = $this->withToken($token)
            ->withHeader('X-Store-Id', (string) $store['id'])
            ->getJson("/api/v1/campaigns/{$campaignId}/analytics")
            ->assertOk()
            ->json('data');

        $this->assertSame(1, $report['funnel']['play']);
        $this->assertSame(0, $report['funnel']['win']);
        $this->assertSame(0.0, (float) $report['rates']['win_rate']);
    }

    public function test_checkin_events_are_store_level_without_campaign(): void
    {
        [$token, $store] = $this->createMerchantWithStore();

        $customer = Customer::query()->create([
            'store_id' => $store['id'],
            'phone' => '09333335555',
            'referral_code' => $this->uniqueReferralCode('CHK'),
        ]);

        $customerToken = $customer->createToken('campaign', ['customer'])->plainTextToken;

        $this->withToken($customerToken)
            ->postJson('/api/v1/daily/checkin')->assertOk();

        $event = AnalyticsEvent::query()
            ->where('name', 'checkin')
            ->where('store_id', $store['id'])
            ->first();

        $this->assertNotNull($event);
        $this->assertNull($event->campaign_id);
        $this->assertSame($customer->id, $event->customer_id);
    }
}
