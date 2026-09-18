<?php

namespace Tests\Concerns;

use App\Models\Campaign;
use App\Models\Customer;
use App\Models\GameSession;
use App\Models\Reward;
use App\Models\RewardInventory;
use Illuminate\Testing\TestResponse;

/**
 * ابزارهای مشترک Sprint 2: ساخت کمپین منتشرشده با Wheel و توکن مشتری.
 */
trait CreatesCampaigns
{
    /** کمپین Wheel منتشرشده → [merchantToken, store, campaign] */
    protected function createPublishedCampaign(array $overrides = []): array
    {
        [$token, $store] = $this->createMerchantWithStore($overrides['phone'] ?? '09121110001');

        $config = $overrides['config'] ?? [
            'segments' => [
                ['label' => '۱۰٪ تخفیف', 'weight' => 40, 'reward_ref' => 'r-10', 'color' => '#F4B860'],
                ['label' => 'امتیاز ۵۰', 'weight' => 30, 'reward_ref' => 'r-p50'],
                ['label' => 'بدون جایزه', 'weight' => 30, 'reward_ref' => null],
            ],
            'animation_duration_ms' => 4200,
            'sound_enabled' => true,
        ];

        $create = $this->withToken($token)
            ->withHeader('X-Store-Id', (string) $store['id'])
            ->postJson('/api/v1/campaigns', [
                'title' => $overrides['title'] ?? 'کمپین تست',
                'game_code' => $overrides['game_code'] ?? 'wheel',
                'config' => $config,
                'rules' => $overrides['rules'] ?? [],
            ])->assertCreated();

        $campaignId = $create->json('data.campaign.id');

        if ($overrides['publish'] ?? true) {
            $this->withToken($token)
                ->withHeader('X-Store-Id', (string) $store['id'])
                ->postJson("/api/v1/campaigns/{$campaignId}/publish")->assertOk();
        }

        $campaign = Campaign::query()->with('configuration')->find($campaignId);

        return [$token, $store, $campaign];
    }

    /**
     * کد دعوت یکتا برای تست‌ها — شمارنده در سطح پروسه؛ تصادم با ستون unique غیرممکن می‌شود.
     * (قبلاً random_int با فضای ۹۰۰تایی بود و در اجرای کامل Suite گاهی تصادم می‌کرد — flaky CI)
     */
    private static int $referralCodeSeq = 0;

    protected function uniqueReferralCode(string $prefix = 'TST'): string
    {
        return $prefix.str_pad((string) ++self::$referralCodeSeq, 7, '0', STR_PAD_LEFT);
    }

    /** توکن مشتری بدون عبور از جریان OTP (برای سرعت تست‌ها) */
    protected function customerToken(int $storeId, string $phone = '09331112233'): string
    {
        $customer = Customer::query()->firstOrCreate(
            ['store_id' => $storeId, 'phone' => $phone],
            ['referral_code' => $this->uniqueReferralCode('TEST')],
        );

        return $customer->createToken('campaign', ['customer'])->plainTextToken;
    }

    protected function startGameSession(string $customerToken, string $slug, ?string $idempotencyKey = null): TestResponse
    {
        $headers = ['campaign_slug' => $slug];

        $request = $this->withToken($customerToken);

        if ($idempotencyKey !== null) {
            $request = $request->withHeader('Idempotency-Key', $idempotencyKey);
        }

        return $request->postJson('/api/v1/play/sessions', $headers);
    }

    protected function playAction(string $customerToken, string $playToken, array $payload = []): TestResponse
    {
        return $this->withToken($customerToken)
            ->postJson("/api/v1/play/sessions/{$playToken}/action", [
                'type' => 'spin',
                'payload' => $payload,
            ]);
    }

    /** ساخت جایزه + موجودی اولیه برای کمپین */
    protected function createReward(Campaign $campaign, array $attrs = []): Reward
    {
        $reward = $campaign->rewards()->create([
            'store_id' => $campaign->store_id,
            'ref' => $attrs['ref'] ?? 'r-test-'.random_int(1000, 9999),
            'type' => $attrs['type'] ?? Reward::TYPE_PERCENTAGE,
            'name' => $attrs['name'] ?? 'تخفیف تست',
            'params' => $attrs['params'] ?? ['percent' => 10],
            'weight' => $attrs['weight'] ?? 1,
            'total_qty' => array_key_exists('total_qty', $attrs) ? $attrs['total_qty'] : 100,
            'is_active' => $attrs['is_active'] ?? true,
        ]);

        RewardInventory::query()->create([
            'reward_id' => $reward->id,
            'remaining_qty' => array_key_exists('remaining_qty', $attrs)
                ? $attrs['remaining_qty']
                : ($reward->total_qty ?? 0),
        ]);

        return $reward;
    }

    /** ساخت Session مستقیم با مدل (برای تست‌های قطعی موتور) */
    protected function makeSession(Campaign $campaign, string $phone = '09331112233'): GameSession
    {
        $customer = Customer::query()->firstOrCreate(
            ['store_id' => $campaign->store_id, 'phone' => $phone],
            ['referral_code' => $this->uniqueReferralCode('TST')],
        );

        return GameSession::query()->create([
            'store_id' => $campaign->store_id,
            'campaign_id' => $campaign->id,
            'customer_id' => $customer->id,
            'play_token' => bin2hex(random_bytes(24)),
            'status' => 'started',
            'started_at' => now(),
            'token_expires_at' => now()->addMinutes(10),
        ]);
    }
}
