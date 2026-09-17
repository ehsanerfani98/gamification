<?php

namespace Tests\Concerns;

use App\Models\Campaign;
use App\Models\Customer;
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

    /** توکن مشتری بدون عبور از جریان OTP (برای سرعت تست‌ها) */
    protected function customerToken(int $storeId, string $phone = '09331112233'): string
    {
        $customer = Customer::query()->firstOrCreate(
            ['store_id' => $storeId, 'phone' => $phone],
            ['referral_code' => 'TEST'.random_int(100, 999)],
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
}
