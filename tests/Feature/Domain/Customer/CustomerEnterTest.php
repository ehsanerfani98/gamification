<?php

namespace Tests\Feature\Domain\Customer;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCampaigns;
use Tests\Concerns\CreatesMerchants;
use Tests\TestCase;

/**
 * سفر مشتری: کمپین عمومی → OTP → بازی — فصل ۹-۱ و ۸-۲.
 */
final class CustomerEnterTest extends TestCase
{
    use CreatesCampaigns, CreatesMerchants, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_public_campaign_endpoint_returns_safe_config(): void
    {
        [, $store, $campaign] = $this->createPublishedCampaign();

        $response = $this->getJson("/api/v1/c/{$campaign->slug}")->assertOk();

        $response->assertJsonPath('data.slug', $campaign->slug)
            ->assertJsonPath('data.is_playable', true)
            ->assertJsonPath('data.game.code', 'wheel');

        // وزن‌ها و مرجع جوایز هرگز به کلاینت افشا نمی‌شوند (فصل ۸-۱)
        $segments = $response->json('data.config.segments');
        $this->assertNotEmpty($segments);
        $this->assertArrayNotHasKey('weight', $segments[0]);
        $this->assertArrayNotHasKey('reward_ref', $segments[0]);
        $this->assertArrayHasKey('label', $segments[0]);
    }

    public function test_unknown_campaign_slug_returns_404(): void
    {
        $this->getJson('/api/v1/c/does-not-exist')->assertStatus(404);
    }

    public function test_customer_otp_flow_returns_limited_token(): void
    {
        [, , $campaign] = $this->createPublishedCampaign();

        $phone = '09331112233';

        $otp = $this->postJson("/api/v1/c/{$campaign->slug}/otp", ['phone' => $phone])
            ->assertOk();

        $code = $otp->json('data.debug_code');

        $enter = $this->postJson("/api/v1/c/{$campaign->slug}/enter", [
            'phone' => $phone,
            'code' => $code,
        ])->assertOk();

        $this->assertArrayHasKey('token', $enter->json('data'));
        $this->assertArrayHasKey('referral_code', $enter->json('data.customer'));
    }

    public function test_customer_token_cannot_access_merchant_routes(): void
    {
        [, $store, $campaign] = $this->createPublishedCampaign();
        $customerToken = $this->customerToken($store['id']);

        // توکن مشتری ability «merchant» ندارد → 403
        $this->withToken($customerToken)
            ->getJson('/api/v1/stores')
            ->assertStatus(403);
    }

    public function test_full_play_flow_end_to_end(): void
    {
        [, $store, $campaign] = $this->createPublishedCampaign();

        $phone = '09331112244';

        $code = $this->postJson("/api/v1/c/{$campaign->slug}/otp", ['phone' => $phone])
            ->json('data.debug_code');

        $customerToken = $this->postJson("/api/v1/c/{$campaign->slug}/enter", [
            'phone' => $phone,
            'code' => $code,
        ])->json('data.token');

        // شروع بازی
        $start = $this->withToken($customerToken)
            ->postJson('/api/v1/play/sessions', ['campaign_slug' => $campaign->slug])
            ->assertOk();

        $playToken = $start->json('data.session.play_token');

        // اکشن و نتیجه
        $action = $this->withToken($customerToken)
            ->postJson("/api/v1/play/sessions/{$playToken}/action", ['type' => 'spin'])
            ->assertOk();

        $this->assertContains($action->json('data.result.outcome'), ['win', 'no_reward']);
        $this->assertNotNull($action->json('data.signature'));

        // سهم روزانه تمام شده
        $this->withToken($customerToken)
            ->postJson('/api/v1/play/sessions', ['campaign_slug' => $campaign->slug])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'DAILY_LIMIT_REACHED');
    }
}
