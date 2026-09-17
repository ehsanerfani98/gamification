<?php

namespace Tests\Feature\Security;

use App\Domain\Game\Services\ResultSigner;
use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Reward;
use App\Models\RewardInventory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCampaigns;
use Tests\Concerns\CreatesMerchants;
use Tests\TestCase;

/**
 * ماتریس تست امنیتی — فصل ۱۰ سند معماری (Sprint 6).
 *
 * هفت سناریوی حمله که هر انتشار باید از آن‌ها عبور کند:
 *  ۱) Replay — مصرف مجدد توکن Session
 *  ۲) دستکاری ورودی کلاینت — تلاش برای تحمیل نتیجه/جایزه
 *  ۳) جعل امضای HMAC نتیجه
 *  ۴) Brute Force روی OTP (Rate Limit + قفل تلاش)
 *  ۵) دسترسی Cross-Tenant (پنهان‌کاری منابع، نه 403)
 *  ۶) بالا رفتن سطح دسترسی (توکن مشتری/فروشگاه‌دار/ناشناس)
 *  ۷) حدس و مصرف دوباره کد کوپن (نبود نشت اطلاعات)
 */
final class SecurityMatrixTest extends TestCase
{
    use CreatesCampaigns, CreatesMerchants, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    /** سناریو ۱ — Replay: توکن Session فقط یک‌بار مصرف می‌شود */
    public function test_replay_attack_is_rejected(): void
    {
        [, , $campaign] = $this->createPublishedCampaign();

        $token = $this->customerToken((int) $campaign->store_id);
        $playToken = $this->startGameSession($token, $campaign->slug)->assertOk()->json('data.session.play_token');

        $this->playAction($token, $playToken)->assertOk();

        // حمله Replay: همان توکن، همان اکشن
        $this->playAction($token, $playToken)
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'SESSION_CONSUMED');
    }

    /** سناریو ۲ — دستکاری ورودی: ورودی کلاینت هرگز نتیجه نمی‌سازد */
    public function test_client_payload_cannot_force_a_win(): void
    {
        [$token, $store] = $this->createMerchantWithStore();

        $campaignId = $this->withToken($token)
            ->withHeader('X-Store-Id', (string) $store['id'])
            ->postJson('/api/v1/campaigns', [
                'title' => 'کمپین ضدتقلب',
                'game_code' => 'wheel',
                'config' => [
                    'segments' => [
                        ['label' => 'جایزه', 'weight' => 1, 'reward_ref' => 'r-tamper', 'color' => '#F4B860'],
                        ['label' => 'بدون جایزه', 'weight' => 1000, 'reward_ref' => null, 'color' => '#888888'],
                    ],
                ],
                'rules' => [],
            ])->assertCreated()->json('data.campaign.id');

        $this->withToken($token)
            ->withHeader('X-Store-Id', (string) $store['id'])
            ->postJson("/api/v1/campaigns/{$campaignId}/rewards", [
                'ref' => 'r-tamper',
                'type' => 'percentage',
                'name' => 'تخفیف',
                'params' => ['percent' => 50],
                'weight' => 1,
                'total_qty' => 5,
            ])->assertCreated();

        $this->withToken($token)
            ->withHeader('X-Store-Id', (string) $store['id'])
            ->postJson("/api/v1/campaigns/{$campaignId}/publish")->assertOk();

        $campaign = Campaign::query()->findOrFail($campaignId);

        // بودجه صفر می‌شود → حتی Segment جایزه هم Fallback می‌خورد
        RewardInventory::query()
            ->where('reward_id', Reward::query()->where('campaign_id', $campaignId)->where('ref', 'r-tamper')->firstOrFail()->id)
            ->update(['remaining_qty' => 0]);

        $customerToken = $this->customerToken((int) $campaign->store_id);
        $playToken = $this->startGameSession($customerToken, $campaign->slug)->assertOk()->json('data.session.play_token');

        // Payload دستکاری‌شده: تلاش برای تحمیل برد
        $result = $this->playAction($customerToken, $playToken, [
            'force_win' => true,
            'outcome' => 'win',
            'reward_id' => 999999,
            'reward_ref' => 'r-tamper',
            'selected_index' => 0,
            'weight' => 999999,
        ])->assertOk()->json('data.result');

        $this->assertSame('no_reward', $result['outcome']);
        $this->assertNull($result['reward']);
    }

    /** سناریو ۳ — امضای HMAC: نتیجه قابل اثبات و غیرقابل دستکاری است */
    public function test_result_signature_is_verifiable_and_tamper_proof(): void
    {
        [, , $campaign] = $this->createPublishedCampaign();

        $token = $this->customerToken((int) $campaign->store_id);
        $playToken = $this->startGameSession($token, $campaign->slug)->assertOk()->json('data.session.play_token');

        $payload = $this->playAction($token, $playToken)->assertOk()->json('data');

        // امضای دست‌نخورده معتبر است
        $this->assertTrue(ResultSigner::verify($payload['result'], $payload['signature']));

        // دستکاری نتیجه → امضا باطل می‌شود
        $tampered = $payload['result'];
        $tampered['outcome'] = 'win';
        $tampered['reward_ref'] = 'r-hacked';

        $this->assertFalse(ResultSigner::verify($tampered, $payload['signature']));
    }

    /** سناریو ۴ — Brute Force روی OTP: Rate Limit + قفل تلاش + Audit */
    public function test_otp_bruteforce_is_rate_limited_and_locked(): void
    {
        // ۳ درخواست در ساعت برای هر شماره؛ چهارمی 429
        $victim = '09129990001';

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/auth/otp/request', ['phone' => $victim])->assertOk();
        }

        $this->postJson('/api/v1/auth/otp/request', ['phone' => $victim])
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'RATE_LIMITED');

        // قفل تلاش: پنج کد اشتباه → ششمین تلاش 423
        $phone = '09129990002';
        $code = $this->postJson('/api/v1/auth/otp/request', ['phone' => $phone])->assertOk()->json('data.debug_code');

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/v1/auth/otp/verify', ['phone' => $phone, 'code' => '000000'])
                ->assertStatus(422)
                ->assertJsonPath('error.code', 'OTP_INVALID');
        }

        $this->postJson('/api/v1/auth/otp/verify', ['phone' => $phone, 'code' => $code])
            ->assertStatus(423)
            ->assertJsonPath('error.code', 'OTP_LOCKED');

        // تلاش‌های ناموفق کامل در Audit ثبت شده‌اند
        $this->assertSame(5, AuditLog::query()->where('action', 'auth.otp_failed')->count());
    }

    /** سناریو ۵ — Cross-Tenant: منابع Store دیگر 404 هستند (بدون افشا) */
    public function test_cross_tenant_resources_are_hidden(): void
    {
        [$ownerToken, $ownerStore, $campaign] = $this->createPublishedCampaign();

        [$attackerToken, $attackerStore] = $this->createMerchantWithStore('09129990111');

        $attempts = [
            ['GET', "/api/v1/campaigns/{$campaign->id}"],
            ['PATCH', "/api/v1/campaigns/{$campaign->id}"],
            ['GET', "/api/v1/campaigns/{$campaign->id}/analytics"],
            ['GET', "/api/v1/campaigns/{$campaign->id}/rewards"],
            ['GET', "/api/v1/stores/{$ownerStore['id']}"],
        ];

        foreach ($attempts as [$method, $url]) {
            $response = $this->withToken($attackerToken)
                ->withHeader('X-Store-Id', (string) $attackerStore['id'])
                ->json($method, $url);

            $this->assertSame(
                404,
                $response->getStatusCode(),
                "{$method} {$url} باید 404 بدهد (نه 403) تا وجود منبع افشا نشود.",
            );
        }
    }

    /** سناریو ۶ — بالا رفتن سطح دسترسی: هر توکن فقط در دامنه خودش */
    public function test_privilege_escalation_is_blocked(): void
    {
        [, $store] = $this->createMerchantWithStore();
        $merchantToken = $this->merchantToken('09129990222');

        $customer = Customer::query()->create([
            'store_id' => $store['id'],
            'phone' => '09129990333',
        ]);
        $customerToken = $customer->createToken('campaign', ['customer'])->plainTextToken;

        // توکن مشتری روی Endpoint فروشگاه‌دار — احراز می‌شود اما ability «merchant» ندارد → 403
        $this->withToken($customerToken)->getJson('/api/v1/stores')->assertStatus(403);

        // توکن مشتری روی ساخت Store
        $this->withToken($customerToken)
            ->postJson('/api/v1/stores', ['name' => 'Store Hack'])
            ->assertStatus(403);

        // توکن فروشگاه‌دار روی Endpoint مشتری
        $this->withToken($merchantToken)
            ->getJson('/api/v1/me/rewards')
            ->assertStatus(401);

        // ناشناس روی Endpointهای حساس
        $this->postJson('/api/v1/referrals/apply', ['code' => 'X'])->assertStatus(401);
        $this->postJson('/api/v1/daily/checkin')->assertStatus(401);
    }

    /** سناریو ۷ — کوپن: بدون نشت اطلاعات و تک‌مصرف اتمی */
    public function test_coupon_resists_guessing_and_double_redeem(): void
    {
        [$token, $store] = $this->createMerchantWithStore();

        $coupon = Coupon::query()->create([
            'store_id' => $store['id'],
            'customer_id' => Customer::query()->create([
                'store_id' => $store['id'],
                'phone' => '09129990444',
            ])->id,
            'code' => 'ABCD2345',
            'type' => 'percentage',
            'value' => ['percent' => 15],
            'expires_at' => now()->addDays(7),
            'status' => Coupon::STATUS_ISSUED,
        ]);

        $redeem = fn (string $code) => $this->withToken($token)
            ->withHeader('X-Store-Id', (string) $store['id'])
            ->postJson('/api/v1/coupons/redeem', ['code' => $code]);

        // کد ناموجود
        $unknown = $redeem('ZZZZ9999')->assertStatus(422)->json('error.code');
        $this->assertSame('COUPON_INVALID', $unknown);

        // مصرف موفق
        $redeem($coupon->code)->assertOk();

        // مصرف دوباره → همان خطای کد ناموجود (تک‌مصرف اتمی — فصل ۶-۴)
        $again = $redeem($coupon->code)->assertStatus(422)->json('error.code');
        $this->assertSame($unknown, $again);

        // کوپن منقضی نیز بدون افشای وضعیت
        $expired = Coupon::query()->create([
            'store_id' => $store['id'],
            'customer_id' => $coupon->customer_id,
            'code' => 'WXYZ6789',
            'type' => 'percentage',
            'value' => ['percent' => 10],
            'expires_at' => now()->subDay(),
            'status' => Coupon::STATUS_ISSUED,
        ]);

        $this->assertSame('COUPON_INVALID', $redeem($expired->code)->assertStatus(422)->json('error.code'));
    }
}
