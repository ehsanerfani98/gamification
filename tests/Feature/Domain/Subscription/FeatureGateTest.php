<?php

namespace Tests\Feature\Domain\Subscription;

use App\Domain\Subscription\Actions\SubscribeAction;
use App\Domain\Subscription\Services\FeatureGate;
use App\Models\Game;
use App\Models\Plan;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesMerchants;
use Tests\TestCase;

/**
 * تست Feature Gating داده‌محور — فصل ۷ سند معماری:
 * سقف‌ها از features JSON و دسترسی بازی‌ها از plan_game خوانده می‌شود.
 */
final class FeatureGateTest extends TestCase
{
    use CreatesMerchants, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_free_plan_limits(): void
    {
        [, $store] = $this->createMerchantWithStore();
        $gate = FeatureGate::for(Store::find($store['id']));

        $this->assertSame('free', $gate->plan()->slug);
        $this->assertSame(1, $gate->maxActiveCampaigns());
        $this->assertSame(200, $gate->maxParticipantsPerMonth());
        $this->assertSame(5, $gate->maxRewards());
        $this->assertSame('summary', $gate->analyticsLevel());
        $this->assertFalse($gate->has('referral'));
    }

    public function test_free_plan_game_access(): void
    {
        [, $store] = $this->createMerchantWithStore();
        $gate = FeatureGate::for(Store::find($store['id']));

        $this->assertTrue($gate->canAccessGame(Game::query()->where('code', 'wheel')->first()));
        $this->assertTrue($gate->canAccessGame(Game::query()->where('code', 'dice')->first()));
        $this->assertFalse($gate->canAccessGame(Game::query()->where('code', 'quiz')->first()));
    }

    public function test_pro_plan_is_unlimited_and_unlocks_all_games(): void
    {
        [, $store] = $this->createMerchantWithStore();

        $pro = Plan::query()->where('slug', 'pro')->first();
        app(SubscribeAction::class)->activatePlan(Store::find($store['id']), $pro);

        $gate = FeatureGate::for(Store::find($store['id']));

        $this->assertSame('pro', $gate->plan()->slug);
        $this->assertSame(10, $gate->maxActiveCampaigns());
        $this->assertNull($gate->maxParticipantsPerMonth());
        $this->assertNull($gate->maxRewards());
        $this->assertSame('funnel_csv', $gate->analyticsLevel());
        $this->assertTrue($gate->has('referral'));
        $this->assertTrue($gate->has('remove_branding'));

        foreach (Game::all() as $game) {
            $this->assertTrue($gate->canAccessGame($game), "Game {$game->code} must be allowed on pro");
        }
    }

    public function test_expired_subscription_falls_back_to_free(): void
    {
        [, $store] = $this->createMerchantWithStore();

        $basic = Plan::query()->where('slug', 'basic')->first();
        $storeModel = Store::find($store['id']);
        app(SubscribeAction::class)->activatePlan($storeModel, $basic);

        // انقضای اشتراک → Feature Gating به سطح رایگان برمی‌گردد (فصل ۷-۳)
        $storeModel->activeSubscription()->expire();

        $gate = FeatureGate::for($storeModel->refresh());

        $this->assertSame('free', $gate->plan()->slug);
        $this->assertSame(1, $gate->maxActiveCampaigns());
        $this->assertFalse($gate->canAccessGame(Game::query()->where('code', 'quiz')->first()));
    }
}
