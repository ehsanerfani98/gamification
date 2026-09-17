<?php

namespace Tests\Feature\Domain\Customer;

use App\Domain\Coupon\Services\CouponCodeGenerator;
use App\Domain\Points\Actions\EarnPointsAction;
use App\Models\Coupon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCampaigns;
use Tests\Concerns\CreatesMerchants;
use Tests\TestCase;

/** GET /me/rewards — کدها و امتیازهای مشتری (فصل ۸-۲) */
final class MeRewardsTest extends TestCase
{
    use CreatesCampaigns, CreatesMerchants, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_customer_sees_own_coupons_and_points(): void
    {
        [$token, $store, $campaign] = $this->createPublishedCampaign(['phone' => '09121110001']);
        $customerToken = $this->customerToken($store['id'], '09331112345');

        /** @var Customer $customer */
        $customer = \App\Models\Customer::query()->where('store_id', $store['id'])
            ->where('phone', '09331112345')->first();

        $coupon = Coupon::query()->create([
            'store_id' => $store['id'],
            'campaign_id' => $campaign->id,
            'customer_id' => $customer->id,
            'code' => CouponCodeGenerator::generate(),
            'type' => 'percentage',
            'value' => ['percent' => 20],
            'expires_at' => now()->addDays(10),
            'status' => Coupon::STATUS_ISSUED,
        ]);

        app(EarnPointsAction::class)->handle($customer, 120);

        $response = $this->withToken($customerToken)
            ->getJson('/api/v1/me/rewards')
            ->assertOk();

        $codes = collect($response->json('data.coupons'))->pluck('code');
        $this->assertContains($coupon->code, $codes);
        $this->assertSame(120, $response->json('data.points'));
    }

    public function test_empty_rewards_for_new_customer(): void
    {
        [$token, $store] = $this->createPublishedCampaign(['phone' => '09121110002']);
        $customerToken = $this->customerToken($store['id'], '09331112346');

        $response = $this->withToken($customerToken)
            ->getJson('/api/v1/me/rewards')
            ->assertOk();

        $this->assertCount(0, $response->json('data.coupons'));
        $this->assertSame(0, $response->json('data.points'));
    }
}
