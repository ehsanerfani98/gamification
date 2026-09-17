<?php

namespace Tests\Feature\Domain\Game;

use App\Domain\Game\GameRegistry;
use App\Domain\Game\Services\ResultSigner;
use App\Models\CampaignParticipation;
use App\Models\GameSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCampaigns;
use Tests\Concerns\CreatesMerchants;
use Tests\TestCase;

/**
 * چرخه Session سمت سرور و دفاع‌های ضدتقلب — فصل ۵-۴ و ۲-۵ و ۸-۳.
 */
final class GameSessionTest extends TestCase
{
    use CreatesCampaigns, CreatesMerchants, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_start_returns_one_time_token(): void
    {
        [, $store, $campaign] = $this->createPublishedCampaign();
        $customerToken = $this->customerToken($store['id']);

        $response = $this->startGameSession($customerToken, $campaign->slug)->assertOk();

        $token = $response->json('data.session.play_token');
        $this->assertMatchesRegularExpression('/^[0-9a-f]{48}$/', $token);
        $this->assertSame('started', $response->json('data.session.status'));
        $this->assertNotNull($response->json('data.session.token_expires_at'));

        $this->assertDatabaseHas(GameSession::class, [
            'campaign_id' => $campaign->id,
            'status' => 'started',
        ]);
    }

    public function test_replay_of_consumed_token_is_rejected(): void
    {
        [, $store, $campaign] = $this->createPublishedCampaign();
        $customerToken = $this->customerToken($store['id']);

        $playToken = $this->startGameSession($customerToken, $campaign->slug)
            ->json('data.session.play_token');

        // اکشن اول → نتیجه
        $this->playAction($customerToken, $playToken)->assertOk();

        // اکشن دوم با همان توکن → SESSION_CONSUMED (فصل ۱۰-۲: سناریوی Replay)
        $this->playAction($customerToken, $playToken)
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'SESSION_CONSUMED');
    }

    public function test_result_signature_is_valid_hmac(): void
    {
        [, $store, $campaign] = $this->createPublishedCampaign();
        $customerToken = $this->customerToken($store['id']);

        $playToken = $this->startGameSession($customerToken, $campaign->slug)
            ->json('data.session.play_token');

        $response = $this->playAction($customerToken, $playToken)->assertOk();

        $result = $response->json('data.result');
        $signature = $response->json('data.signature');

        // امضای درست تأیید و نسخه دستکاری‌شده رد می‌شود (فصل ۵-۲)
        $this->assertTrue(ResultSigner::verify($result, $signature));
        $this->assertFalse(ResultSigner::verify([...$result, 'reward_ref' => 'HACKED'], $signature));

        $this->assertContains($result['outcome'], ['win', 'no_reward']);
    }

    public function test_client_payload_cannot_influence_result(): void
    {
        [, $store, $campaign] = $this->createPublishedCampaign();
        $customerToken = $this->customerToken($store['id']);

        $playToken = $this->startGameSession($customerToken, $campaign->slug)
            ->json('data.session.play_token');

        // کلاینت «Segment برنده» را ادعا می‌کند — فیلد باید نادیده گرفته شود (فصل ۱۰-۲)
        $response = $this->playAction($customerToken, $playToken, [
            'desired_segment' => 0,
            'force_win' => true,
            'reward_id' => 999,
        ])->assertOk();

        $result = $response->json('data.result');
        $this->assertArrayNotHasKey('force_win', $result);
        $this->assertNotSame(999, $result['reward_ref'] ?? null);
    }

    public function test_daily_limit_blocks_second_session(): void
    {
        [, $store, $campaign] = $this->createPublishedCampaign();
        $customerToken = $this->customerToken($store['id']);

        $this->startGameSession($customerToken, $campaign->slug)->assertOk();

        // Start دوم در همان روز → DAILY_LIMIT_REACHED (فصل ۱۰-۲)
        $this->startGameSession($customerToken, $campaign->slug)
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'DAILY_LIMIT_REACHED');
    }

    public function test_idempotency_key_returns_same_session(): void
    {
        [, $store, $campaign] = $this->createPublishedCampaign();
        $customerToken = $this->customerToken($store['id']);

        $first = $this->startGameSession($customerToken, $campaign->slug, 'key-abc-1')
            ->assertOk()
            ->json('data.session.play_token');

        // همان کلید → همان Session (تپ مضاعف، فصل ۸-۳)
        $second = $this->startGameSession($customerToken, $campaign->slug, 'key-abc-1')
            ->assertOk()
            ->json('data.session.play_token');

        $this->assertSame($first, $second);
    }

    public function test_participation_recorded_once_per_day(): void
    {
        [, $store, $campaign] = $this->createPublishedCampaign();
        $customerToken = $this->customerToken($store['id']);

        $playToken = $this->startGameSession($customerToken, $campaign->slug)
            ->json('data.session.play_token');

        $this->playAction($customerToken, $playToken)->assertOk();

        $this->assertDatabaseCount('campaign_participations', 1);

        $participation = CampaignParticipation::query()
            ->where('campaign_id', $campaign->id)
            ->first();
        $this->assertNotNull($participation);
        $this->assertTrue($participation->played_on->isToday());
    }

    public function test_cross_tenant_campaign_returns_404(): void
    {
        [, , $campaignA] = $this->createPublishedCampaign(['phone' => '09121110001']);
        [$tokenB, $storeB] = $this->createMerchantWithStore('09121110002');
        $otherToken = $this->customerToken($storeB['id'], '09331119988');

        // مشتریِ Store دیگر → 404 بدون افشای وجود کمپین
        $this->startGameSession($otherToken, $campaignA->slug)
            ->assertStatus(404);
    }

    public function test_draft_campaign_is_not_playable(): void
    {
        [, $store, $campaign] = $this->createPublishedCampaign(['publish' => false]);
        $customerToken = $this->customerToken($store['id']);

        $this->startGameSession($customerToken, $campaign->slug)
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'CAMPAIGN_NOT_ACTIVE');
    }

    public function test_expired_session_token_rejected(): void
    {
        [, $store, $campaign] = $this->createPublishedCampaign();
        $customerToken = $this->customerToken($store['id']);

        $playToken = $this->startGameSession($customerToken, $campaign->slug)
            ->json('data.session.play_token');

        GameSession::query()->where('play_token', $playToken)->update([
            'token_expires_at' => now()->subMinutes(30),
        ]);

        $this->playAction($customerToken, $playToken)
            ->assertStatus(410)
            ->assertJsonPath('error.code', 'SESSION_EXPIRED');
    }

    public function test_registry_maps_wheel_plugin(): void
    {
        $this->assertTrue(GameRegistry::has('wheel'));
        $this->assertFalse(GameRegistry::has('dice')); // هنوز پیاده نشده
        $this->assertSame('wheel', GameRegistry::for('wheel')::metadata()->code);
    }
}
