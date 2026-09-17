<?php

namespace Tests\Feature\Domain\Reward;

use App\Domain\Game\DTO\GameResult;
use App\Domain\Reward\RewardEngine;
use App\Models\Coupon;
use App\Models\PointTransaction;
use App\Models\Reward;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCampaigns;
use Tests\Concerns\CreatesMerchants;
use Tests\TestCase;

/**
 * خط لوله تصمیم Reward Engine — فصل ۶ سند معماری:
 * کاندیداها → فیلترها → انتخاب وزنی → قفل اتمی موجودی → صدور یا Fallback.
 */
final class RewardPipelineTest extends TestCase
{
    use CreatesCampaigns, CreatesMerchants, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_win_with_reward_ref_issues_coupon(): void
    {
        [, , $campaign] = $this->createPublishedCampaign();
        $reward = $this->createReward($campaign, ['ref' => 'r-10', 'type' => Reward::TYPE_PERCENTAGE]);
        $session = $this->makeSession($campaign);

        $result = app(RewardEngine::class)->resolveForSession(
            $campaign,
            $session,
            GameResult::win('r-10'),
        );

        $this->assertSame('issued', $result['status']);
        $this->assertSame('coupon', $result['issuance']['kind']);

        $code = $result['issuance']['code'];
        $this->assertMatchesRegularExpression('/^[A-HJ-NP-Z2-9]{8}$/', $code);

        $this->assertDatabaseHas(Coupon::class, [
            'reward_id' => $reward->id,
            'code' => $code,
            'status' => 'issued',
            'customer_id' => $session->customer_id,
        ]);

        // موجودی اتمی کم شد
        $this->assertSame(99, $reward->inventory()->first()->remaining_qty);
    }

    public function test_points_reward_credits_ledger(): void
    {
        [, , $campaign] = $this->createPublishedCampaign();
        $this->createReward($campaign, [
            'ref' => 'r-p50',
            'type' => Reward::TYPE_POINTS,
            'params' => ['points' => 50],
        ]);
        $session = $this->makeSession($campaign);

        $result = app(RewardEngine::class)->resolveForSession(
            $campaign,
            $session,
            GameResult::win('r-p50'),
        );

        $this->assertSame('issued', $result['status']);
        $this->assertSame('points', $result['issuance']['kind']);
        $this->assertSame(50, $result['issuance']['balance']);

        $this->assertDatabaseHas(PointTransaction::class, [
            'customer_id' => $session->customer_id,
            'delta' => 50,
            'type' => 'earn',
            'reference_type' => 'game_session',
            'reference_id' => $session->id,
        ]);
    }

    public function test_no_reward_result_issues_nothing(): void
    {
        [, , $campaign] = $this->createPublishedCampaign();
        $this->createReward($campaign);
        $session = $this->makeSession($campaign);

        $result = app(RewardEngine::class)->resolveForSession(
            $campaign,
            $session,
            GameResult::noReward(),
        );

        $this->assertNull($result);
        $this->assertDatabaseCount('coupons', 0);
    }

    public function test_depleted_reward_falls_back_to_other_reward(): void
    {
        [, , $campaign] = $this->createPublishedCampaign();
        // جایزه اصلی: موجودی تمام شده
        $this->createReward($campaign, ['ref' => 'r-main', 'total_qty' => 5, 'remaining_qty' => 0]);
        // جایزه جایگزین: بی‌نهایت
        $alt = $this->createReward($campaign, [
            'ref' => 'r-alt',
            'type' => Reward::TYPE_POINTS,
            'params' => ['points' => 10],
            'total_qty' => null,
            'remaining_qty' => 0,
        ]);
        $session = $this->makeSession($campaign);

        $result = app(RewardEngine::class)->resolveForSession(
            $campaign,
            $session,
            GameResult::win('r-main'),
        );

        // کاندید حذف و انتخاب وزنی روی باقیمانده تکرار می‌شود (فصل ۶-۴)
        $this->assertSame('issued', $result['status']);
        $this->assertSame('r-alt', $result['reward']['ref']);
    }

    public function test_all_depleted_returns_transparent_fallback(): void
    {
        [, , $campaign] = $this->createPublishedCampaign();
        $this->createReward($campaign, ['ref' => 'r-10', 'total_qty' => 2, 'remaining_qty' => 0]);
        $session = $this->makeSession($campaign);

        $result = app(RewardEngine::class)->resolveForSession(
            $campaign,
            $session,
            GameResult::win('r-10'),
        );

        // Fallback شفاف به «بدون جایزه» — هرگز جایزه خارج از بودجه (فصل ۶-۱)
        $this->assertSame('fallback_no_reward', $result['status']);
        $this->assertDatabaseCount('coupons', 0);
    }

    public function test_inventory_never_goes_negative(): void
    {
        [, , $campaign] = $this->createPublishedCampaign();
        $reward = $this->createReward($campaign, [
            'ref' => 'r-limited',
            'type' => Reward::TYPE_POINTS,
            'params' => ['points' => 5],
            'total_qty' => 2,
            'remaining_qty' => 2,
        ]);
        $engine = app(RewardEngine::class);

        $issued = 0;

        for ($i = 0; $i < 5; $i++) {
            $session = $this->makeSession($campaign, '09331112'.str_pad((string) $i, 2, '0', STR_PAD_LEFT).'99');

            $result = $engine->resolveForSession($campaign, $session, GameResult::win('r-limited'));

            if ($result !== null && $result['status'] === 'issued') {
                $issued++;
            }
        }

        $this->assertSame(2, $issued);
        $this->assertSame(0, $reward->inventory()->first()->remaining_qty);
    }
}
