<?php

namespace Tests\Feature\Domain\Coupon;

use App\Domain\Coupon\Services\CouponCodeGenerator;
use App\Models\Coupon;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCampaigns;
use Tests\Concerns\CreatesMerchants;
use Tests\TestCase;

/**
 * کوپن: کد غیرقابل حدس، یکتایی، انقضا و Redemption اتمی — فصل ۶-۴ و ۳-۴.
 */
final class CouponTest extends TestCase
{
    use CreatesCampaigns, CreatesMerchants, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_generated_codes_use_unambiguous_alphabet(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $code = CouponCodeGenerator::generate();

            $this->assertMatchesRegularExpression('/^[A-HJ-NP-Z2-9]{8}$/', $code);
            $this->assertStringNotContainsString('0', $code);
            $this->assertStringNotContainsString('O', $code);
            $this->assertStringNotContainsString('1', $code);
            $this->assertStringNotContainsString('I', $code);
        }
    }

    public function test_generated_codes_support_brand_prefix_and_are_unique(): void
    {
        $codes = [];

        for ($i = 0; $i < 30; $i++) {
            $codes[] = CouponCodeGenerator::generate('SHOP-');
        }

        foreach ($codes as $code) {
            $this->assertStringStartsWith('SHOP-', $code);
        }

        $this->assertCount(30, array_unique($codes));
    }

    public function test_merchant_can_redeem_coupon_once(): void
    {
        [$token, $store] = $this->createMerchantWithStore();

        $coupon = Coupon::query()->create([
            'store_id' => $store['id'],
            'customer_id' => Customer::query()->create([
                'store_id' => $store['id'],
                'phone' => '09331115555',
            ])->id,
            'code' => CouponCodeGenerator::generate(),
            'type' => 'percentage',
            'value' => ['percent' => 15],
            'expires_at' => now()->addDays(7),
            'status' => Coupon::STATUS_ISSUED,
        ]);

        $response = $this->withToken($token)
            ->withHeader('X-Store-Id', (string) $store['id'])
            ->postJson('/api/v1/coupons/redeem', [
                'code' => $coupon->code,
                'amount_irt' => 500000,
                'order_ref' => 'ORD-101',
            ])
            ->assertOk();

        $this->assertSame('redeemed', $response->json('data.coupon.status'));

        $this->assertDatabaseHas('coupon_redemptions', [
            'coupon_id' => $coupon->id,
            'amount_irt' => 500000,
            'order_ref' => 'ORD-101',
        ]);

        // Redemption دوباره → رد (سوءاستفاده از کد — فصل ۲-۵)
        $this->withToken($token)
            ->withHeader('X-Store-Id', (string) $store['id'])
            ->postJson('/api/v1/coupons/redeem', ['code' => $coupon->code])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'COUPON_INVALID');
    }

    public function test_expired_coupon_is_rejected(): void
    {
        [$token, $store] = $this->createMerchantWithStore();

        $coupon = Coupon::query()->create([
            'store_id' => $store['id'],
            'customer_id' => Customer::query()->create([
                'store_id' => $store['id'],
                'phone' => '09331115666',
            ])->id,
            'code' => CouponCodeGenerator::generate(),
            'type' => 'fixed',
            'value' => ['amount_irt' => 50000],
            'expires_at' => now()->subDay(),
            'status' => Coupon::STATUS_ISSUED,
        ]);

        $this->withToken($token)
            ->withHeader('X-Store-Id', (string) $store['id'])
            ->postJson('/api/v1/coupons/redeem', ['code' => $coupon->code])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'COUPON_INVALID');
    }

    public function test_unknown_code_is_rejected_without_disclosure(): void
    {
        [$token, $store] = $this->createMerchantWithStore();

        $this->withToken($token)
            ->withHeader('X-Store-Id', (string) $store['id'])
            ->postJson('/api/v1/coupons/redeem', ['code' => 'ZZZZZZZZ'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'COUPON_INVALID');
    }
}
