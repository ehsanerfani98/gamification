<?php

namespace Tests\Feature\Domain\Auth;

use App\Models\OtpCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesMerchants;
use Tests\TestCase;

/**
 * تست‌های OTP — فصل ۲-۵ و ۸-۳ سند معماری:
 * هش امن کد، سقف تلاش، انقضا، مصرف یک‌بارمصرف و Rate Limit پلکانی.
 */
final class OtpAuthTest extends TestCase
{
    use CreatesMerchants, RefreshDatabase;

    public function test_request_otp_stores_hashed_code_and_sends_sms(): void
    {
        $response = $this->postJson('/api/v1/auth/otp/request', [
            'phone' => '09121110000',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.sent', true)
            ->assertJsonPath('data.expires_in', 300);

        $code = $response->json('data.debug_code');
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);

        // کد هرگز خام ذخیره نمی‌شود (فصل ۲-۵)
        $otp = OtpCode::query()->where('phone', '09121110000')->first();
        $this->assertNotNull($otp);
        $this->assertNotSame($code, $otp->code_hash);
        $this->assertSame(0, $otp->attempts);
    }

    public function test_verify_creates_user_and_returns_token(): void
    {
        [, $data] = $this->otpCycle();

        $this->assertArrayHasKey('token', $data);
        $this->assertSame('09121110000', $data['user']['phone']);
        $this->assertSame('merchant', $data['user']['role']);
        $this->assertTrue($data['is_new_user']);
    }

    public function test_wrong_code_fails_and_increments_attempts(): void
    {
        $this->postJson('/api/v1/auth/otp/request', ['phone' => '09121110000']);

        $this->postJson('/api/v1/auth/otp/verify', ['phone' => '09121110000', 'code' => '000000'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'OTP_INVALID');

        $this->postJson('/api/v1/auth/otp/verify', ['phone' => '09121110000', 'code' => '000000'])
            ->assertStatus(422);

        $this->assertSame(2, OtpCode::query()->where('phone', '09121110000')->value('attempts'));
    }

    public function test_code_locks_after_max_attempts(): void
    {
        $response = $this->postJson('/api/v1/auth/otp/request', ['phone' => '09121110000']);
        $code = $response->json('data.debug_code');

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/otp/verify', ['phone' => '09121110000', 'code' => '000000']);
        }

        // پس از پنج تلاش ناموفق، حتی کد درست هم قفل است (فصل ۲-۵)
        $this->postJson('/api/v1/auth/otp/verify', ['phone' => '09121110000', 'code' => $code])
            ->assertStatus(423)
            ->assertJsonPath('error.code', 'OTP_LOCKED');
    }

    public function test_expired_code_is_rejected(): void
    {
        $response = $this->postJson('/api/v1/auth/otp/request', ['phone' => '09121110000']);
        $code = $response->json('data.debug_code');

        OtpCode::query()->where('phone', '09121110000')->update([
            'expires_at' => now()->subMinutes(10),
        ]);

        $this->postJson('/api/v1/auth/otp/verify', ['phone' => '09121110000', 'code' => $code])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'OTP_NOT_FOUND');
    }

    public function test_code_is_single_use(): void
    {
        $this->otpCycle();

        // مصرف دوم همان کد حتی با کد درست رد می‌شود (Replay — فصل ۲-۵)
        $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => '09121110000',
            'code' => '000001',
        ])->assertStatus(422);
    }

    public function test_otp_request_is_rate_limited_per_phone(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/auth/otp/request', ['phone' => '09121110000'])->assertOk();
        }

        // درخواست چهارم در همان ساعت → 429 (فصل ۸-۳: ۳ در ساعت هر شماره)
        $this->postJson('/api/v1/auth/otp/request', ['phone' => '09121110000'])
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'RATE_LIMITED');
    }

    public function test_existing_user_is_not_duplicated(): void
    {
        $this->merchantToken('09121110000');

        [, $second] = $this->otpCycle();

        $this->assertFalse($second['is_new_user']);
    }

    /** چرخه کامل OTP برای شماره و بازگشت پاسخ verify */
    private function otpCycle(string $phone = '09121110000'): array
    {
        $request = $this->postJson('/api/v1/auth/otp/request', ['phone' => $phone]);

        $verify = $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => $phone,
            'code' => $request->json('data.debug_code'),
        ])->assertOk();

        return [$request, $verify->json('data')];
    }
}
