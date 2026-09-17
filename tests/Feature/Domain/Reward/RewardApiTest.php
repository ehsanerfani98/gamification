<?php

namespace Tests\Feature\Domain\Reward;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCampaigns;
use Tests\Concerns\CreatesMerchants;
use Tests\TestCase;

/**
 * API جایزه‌های کمپین — فصل ۸-۲ (مسیرهای Merchant).
 * رگرسیون: نبودِ import مدل Reward در RewardIssuerTypes باعث 500 می‌شد.
 */
class RewardApiTest extends TestCase
{
    use CreatesCampaigns, CreatesMerchants, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(); // Planها و بازی‌های MVP
    }

    public function test_index_returns_types_and_rewards(): void
    {
        [$token, $store, $campaign] = $this->createPublishedCampaign(['publish' => false]);

        $response = $this->withToken($token)
            ->withHeader('X-Store-Id', (string) $store['id'])
            ->getJson("/api/v1/campaigns/{$campaign->id}/rewards")
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['types', 'rewards'],
            ]);

        // ۹ نوع جایزه MVP — فصل ۶-۲
        $this->assertCount(9, $response->json('data.types'));
    }

    public function test_store_creates_reward_with_inventory(): void
    {
        [$token, $store, $campaign] = $this->createPublishedCampaign(['publish' => false]);

        $this->withToken($token)
            ->withHeader('X-Store-Id', (string) $store['id'])
            ->postJson("/api/v1/campaigns/{$campaign->id}/rewards", [
                'ref' => 'r-p15',
                'type' => 'percentage',
                'name' => 'کوپن ۱۵٪',
                'params' => ['value' => 15],
                'weight' => 20,
                'total_qty' => 50,
            ])
            ->assertCreated()
            ->assertJsonPath('data.reward.ref', 'r-p15')
            ->assertJsonPath('data.reward.remaining', 50);

        $this->withToken($token)
            ->withHeader('X-Store-Id', (string) $store['id'])
            ->getJson("/api/v1/campaigns/{$campaign->id}/rewards")
            ->assertOk()
            ->assertJsonCount(1, 'data.rewards');
    }

    public function test_duplicate_ref_is_rejected(): void
    {
        [$token, $store, $campaign] = $this->createPublishedCampaign(['publish' => false]);

        $payload = [
            'ref' => 'r-dup',
            'type' => 'points',
            'name' => '۵۰ امتیاز',
            'params' => ['points' => 50],
            'weight' => 10,
        ];

        $this->withToken($token)
            ->withHeader('X-Store-Id', (string) $store['id'])
            ->postJson("/api/v1/campaigns/{$campaign->id}/rewards", $payload)
            ->assertCreated();

        $this->withToken($token)
            ->withHeader('X-Store-Id', (string) $store['id'])
            ->postJson("/api/v1/campaigns/{$campaign->id}/rewards", $payload)
            ->assertStatus(422);
    }

    public function test_cross_tenant_campaign_is_404(): void
    {
        [$token, $store, $campaign] = $this->createPublishedCampaign(['publish' => false]);
        [$otherToken, $otherStore] = $this->createMerchantWithStore('09121119999');

        $this->withToken($otherToken)
            ->withHeader('X-Store-Id', (string) $otherStore['id'])
            ->getJson("/api/v1/campaigns/{$campaign->id}/rewards")
            ->assertNotFound();
    }
}
