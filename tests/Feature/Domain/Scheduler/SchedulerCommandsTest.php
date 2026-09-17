<?php

namespace Tests\Feature\Domain\Scheduler;

use App\Models\Campaign;
use App\Models\Customer;
use App\Models\GameSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCampaigns;
use Tests\Concerns\CreatesMerchants;
use Tests\TestCase;

/**
 * دستورات نگهدارنده — فصل ۱۰ سند معماری (Sprint 6):
 * بستن کمپین منقضی و انقضای Sessionهای راکد؛ هر دو Idempotent.
 */
final class SchedulerCommandsTest extends TestCase
{
    use CreatesCampaigns, CreatesMerchants, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_close_expired_campaigns_transitions_past_end(): void
    {
        // کمپین منقضی — Published با ends_at گذشته
        [, , $stale] = $this->createPublishedCampaign([
            'config' => [
                'segments' => [
                    ['label' => 'جایزه', 'weight' => 1, 'reward_ref' => 'r-x', 'color' => '#F4B860'],
                    ['label' => 'بدون', 'weight' => 1, 'reward_ref' => null, 'color' => '#888888'],
                ],
            ],
        ]);
        $stale->forceFill(['ends_at' => now()->subHour()])->save();

        // کمپین فعال — بدون پایان یا با پایان آینده
        [, , $active] = $this->createPublishedCampaign();

        $this->artisan('campaigns:close-expired')->assertExitCode(0);

        $this->assertSame(Campaign::STATUS_EXPIRED, $stale->refresh()->status);
        $this->assertSame(Campaign::STATUS_PUBLISHED, $active->refresh()->status);

        // Idempotent: اجرای دوباره تغییری نمی‌دهد
        $this->artisan('campaigns:close-expired')->assertExitCode(0);
        $this->assertSame(Campaign::STATUS_EXPIRED, $stale->refresh()->status);
    }

    public function test_sessions_expire_marks_stale_started_sessions(): void
    {
        [, , $campaign] = $this->createPublishedCampaign();

        $customer = Customer::query()->create([
            'store_id' => $campaign->store_id,
            'phone' => '09339990001',
        ]);

        $stale = GameSession::query()->create([
            'store_id' => $campaign->store_id,
            'campaign_id' => $campaign->id,
            'customer_id' => $customer->id,
            'play_token' => bin2hex(random_bytes(24)),
            'status' => GameSession::STATUS_STARTED,
            'started_at' => now()->subHour(),
            'token_expires_at' => now()->subMinutes(5),
        ]);

        $fresh = GameSession::query()->create([
            'store_id' => $campaign->store_id,
            'campaign_id' => $campaign->id,
            'customer_id' => $customer->id,
            'play_token' => bin2hex(random_bytes(24)),
            'status' => GameSession::STATUS_STARTED,
            'started_at' => now(),
            'token_expires_at' => now()->addMinutes(10),
        ]);

        $this->artisan('sessions:expire')->assertExitCode(0);

        $this->assertSame(GameSession::STATUS_EXPIRED, $stale->refresh()->status);
        $this->assertSame(GameSession::STATUS_STARTED, $fresh->refresh()->status);
    }
}
