<?php

namespace Tests\Feature\Domain\Referral;

use App\Domain\Points\Actions\EarnPointsAction;
use App\Models\Customer;
use App\Models\PointTransaction;
use App\Models\Referral;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesCampaigns;
use Tests\Concerns\CreatesMerchants;
use Tests\TestCase;

/**
 * دعوت دوستان با جایزه پله‌ای — فصل ۱۰ سند معماری (Sprint 5):
 * کد دعوت قلمرو Store دارد، دعوت خودی و نامعتبر رد می‌شود، هر invited
 * فقط یک‌بار شمرده می‌شود و جوایز پله‌ای ۱/۳/۵ فقط یک‌بار پرداخت می‌شوند.
 */
final class ReferralTest extends TestCase
{
    use CreatesCampaigns, CreatesMerchants, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    /** [store, referrer, customerToken(mobile token of referrer)] */
    private function makeStoreWithReferrer(string $phone = '09125550000'): array
    {
        $user = User::query()->create([
            'phone' => '09'.random_int(100000000, 999999999),
            'role' => 'merchant',
        ]);

        $store = Store::query()->create([
            'user_id' => $user->id,
            'name' => 'فروشگاه دعوت',
            'slug' => 'ref-'.strtolower(Str::random(6)),
        ]);

        $referrer = Customer::query()->create([
            'store_id' => $store->id,
            'phone' => $phone,
            'referral_code' => $this->uniqueReferralCode('REF'),
        ]);

        return [$store, $referrer];
    }

    private function invitedToken(int $storeId, string $phone): array
    {
        $customer = Customer::query()->create([
            'store_id' => $storeId,
            'phone' => $phone,
            'referral_code' => $this->uniqueReferralCode('INV'),
        ]);

        return [$customer, $customer->createToken('campaign', ['customer'])->plainTextToken];
    }

    private function balance(Customer $customer): int
    {
        return app(EarnPointsAction::class)->balance($customer);
    }

    public function test_tier_one_is_awarded_on_first_referral(): void
    {
        [$store, $referrer] = $this->makeStoreWithReferrer();
        [$invited, $invitedToken] = $this->invitedToken($store->id, '09335550001');

        $response = $this->withToken($invitedToken)
            ->postJson('/api/v1/referrals/apply', ['code' => $referrer->referral_code])
            ->assertOk()
            ->json('data');

        $this->assertSame('completed', $response['status']);
        $this->assertSame(1, $response['total_referrals']);
        $this->assertSame(100, $response['awarded']['points']);

        $this->assertSame(100, $this->balance($referrer));
        $this->assertSame($referrer->id, $invited->refresh()->referred_by);

        // تراکنش Ledger با مرجع دعوت ثبت شده است
        $this->assertDatabaseHas('point_transactions', [
            'customer_id' => $referrer->id,
            'type' => PointTransaction::TYPE_EARN,
            'reference_type' => 'referral',
            'delta' => 100,
        ]);
    }

    public function test_tiers_three_and_five_are_awarded_once(): void
    {
        [$store, $referrer] = $this->makeStoreWithReferrer();

        // دعوت‌های ۱ تا ۵ — فقط پله‌های ۱، ۳ و ۵ جایزه می‌سازند
        foreach ([1, 2, 3, 4, 5] as $index) {
            [$invited, $invitedToken] = $this->invitedToken($store->id, "09335550{$index}0".random_int(0, 9));

            $response = $this->withToken($invitedToken)
                ->postJson('/api/v1/referrals/apply', ['code' => $referrer->referral_code])
                ->assertOk()
                ->json('data');

            $this->assertSame($index, $response['total_referrals']);

            if (in_array($index, [1, 3, 5], true)) {
                $this->assertNotNull($response['awarded'], "پله {$index} باید جایزه داشته باشد");
            } else {
                $this->assertNull($response['awarded'], "پله {$index} نباید جایزه داشته باشد");
            }
        }

        // ۱۰۰ (پله ۱) + ۳۰۰ (پله ۳) + ۵۰۰ (پله ۵) = ۹۰۰
        $this->assertSame(900, $this->balance($referrer));

        $this->assertDatabaseCount('point_transactions', 3);
    }

    public function test_duplicate_apply_is_idempotent_without_new_rewards(): void
    {
        [$store, $referrer] = $this->makeStoreWithReferrer();
        [, $invitedToken] = $this->invitedToken($store->id, '09335551111');

        $this->withToken($invitedToken)
            ->postJson('/api/v1/referrals/apply', ['code' => $referrer->referral_code])->assertOk();

        $second = $this->withToken($invitedToken)
            ->postJson('/api/v1/referrals/apply', ['code' => $referrer->referral_code])
            ->assertOk()
            ->json('data');

        $this->assertSame('already', $second['status']);
        $this->assertNull($second['awarded']);
        $this->assertSame(100, $this->balance($referrer));
        $this->assertDatabaseCount('referrals', 1);
    }

    public function test_self_referral_is_rejected(): void
    {
        [$store, $referrer] = $this->makeStoreWithReferrer();

        $referrerToken = $referrer->createToken('campaign', ['customer'])->plainTextToken;

        // کد دعوت خودش را ثبت می‌کند
        $this->withToken($referrerToken)
            ->postJson('/api/v1/referrals/apply', ['code' => $referrer->referral_code])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'REFERRAL_INVALID');

        $this->assertDatabaseCount('referrals', 0);
    }

    public function test_invalid_code_is_rejected(): void
    {
        [$store] = $this->makeStoreWithReferrer();
        [, $invitedToken] = $this->invitedToken($store->id, '09335552222');

        $this->withToken($invitedToken)
            ->postJson('/api/v1/referrals/apply', ['code' => 'NOPE9999'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'REFERRAL_INVALID');
    }

    public function test_referral_code_is_store_scoped(): void
    {
        [$storeA, $referrerA] = $this->makeStoreWithReferrer();
        [$storeB, $referrerB] = $this->makeStoreWithReferrer('09125551111');

        // مشتری Store B با کد مشتری Store A → رد (قلمرو Store)
        [$invitedB, $invitedBToken] = $this->invitedToken($storeB->id, '09335553333');

        $this->withToken($invitedBToken)
            ->postJson('/api/v1/referrals/apply', ['code' => $referrerA->referral_code])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'REFERRAL_INVALID');

        $this->assertSame(0, $this->balance($referrerA));
        $this->assertSame(0, $this->balance($referrerB));
    }

    public function test_invalid_referral_at_enter_does_not_block_entry(): void
    {
        [$token, $store, $campaign] = $this->createPublishedCampaign([
            'phone' => '09125559000',
        ]);

        // کد دعوت نامعتبر — ورود باید موفق بماند (اصطکاک صفر ورود)
        $code = $this->postJson("/api/v1/c/{$campaign->slug}/otp", ['phone' => '09335554444'])
            ->assertOk()->json('data.debug_code');

        $response = $this->postJson("/api/v1/c/{$campaign->slug}/enter", [
            'phone' => '09335554444',
            'code' => $code,
            'referral_code' => 'WRONG99',
        ])->assertOk();

        $this->assertNotNull($response->json('data.token'));
        $this->assertDatabaseCount('referrals', 0);
    }

    public function test_valid_referral_at_enter_completes_referral(): void
    {
        // دعوت‌کننده مشتری همان Storeِ کمپین است
        [$token, $store, $campaign] = $this->createPublishedCampaign(['phone' => '09125558000']);

        $referrer = Customer::query()->create([
            'store_id' => $store['id'],
            'phone' => '09125558111',
            'referral_code' => $this->uniqueReferralCode('REF'),
        ]);

        $code = $this->postJson("/api/v1/c/{$campaign->slug}/otp", ['phone' => '09335555555'])
            ->assertOk()->json('data.debug_code');

        $this->postJson("/api/v1/c/{$campaign->slug}/enter", [
            'phone' => '09335555555',
            'code' => $code,
            'referral_code' => $referrer->referral_code,
        ])->assertOk();

        $invited = Customer::query()->where('store_id', $store['id'])
            ->where('phone', '09335555555')->firstOrFail();

        $this->assertDatabaseHas('referrals', [
            'store_id' => $store['id'],
            'referrer_id' => $referrer->id,
            'invited_id' => $invited->id,
        ]);

        $this->assertSame(100, $this->balance($referrer));
    }

    public function test_referral_row_binds_referrer_and_invited(): void
    {
        [$store, $referrer] = $this->makeStoreWithReferrer();
        [$invited, $invitedToken] = $this->invitedToken($store->id, '09335556666');

        $this->withToken($invitedToken)
            ->postJson('/api/v1/referrals/apply', ['code' => $referrer->referral_code])->assertOk();

        $referral = Referral::query()->firstOrFail();

        $this->assertSame($referrer->id, $referral->referrer_id);
        $this->assertSame($invited->id, $referral->invited_id);
        $this->assertSame(Referral::STATUS_COMPLETED, $referral->status);
        $this->assertNotNull($referral->completed_at);
    }
}
