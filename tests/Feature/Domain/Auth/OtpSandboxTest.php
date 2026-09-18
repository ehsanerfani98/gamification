<?php

namespace Tests\Feature\Domain\Auth;

use App\Domain\Authentication\Jobs\SendSmsJob;
use App\Support\Settings\SiteSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * سندباکس پیامک — Sprint 8 (بتای ۵ فروشگاه).
 * با روشن‌بودن سندباکس، پیامک واقعی/صف ارسال نمی‌شود و کد در پاسخ API برمی‌گردد.
 */
final class OtpSandboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_sms_sandbox_skips_dispatch_and_returns_code_in_response(): void
    {
        app(SiteSettingsService::class)->update(['sms_sandbox' => true]);

        Queue::fake();

        $response = $this->postJson('/api/v1/auth/otp/request', ['phone' => '09121110000']);

        $response->assertOk()
            ->assertJsonPath('data.sandbox', true);

        // کد در پاسخ برای تست ورود بدون اعتبار IPPanel
        $code = $response->json('data.debug_code');
        $this->assertNotNull($code);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);

        // هیچ Job پیامکی به صف رفته است (بدون هزینه SMS)
        Queue::assertNothingPushed();
    }

    public function test_without_sandbox_sms_job_is_dispatched(): void
    {
        app(SiteSettingsService::class)->update(['sms_sandbox' => false]);

        Queue::fake();

        $response = $this->postJson('/api/v1/auth/otp/request', ['phone' => '09121110000']);

        $response->assertOk()->assertJsonPath('data.sandbox', false);

        Queue::assertPushed(SendSmsJob::class, 1);
    }

    public function test_sandbox_applies_to_customer_login_purpose_too(): void
    {
        app(SiteSettingsService::class)->update(['sms_sandbox' => true]);

        Queue::fake();

        // purpose مشتری (ورود به کمپین) — همان مسیر سندباکس
        $this->postJson('/api/v1/auth/otp/request', ['phone' => '09121110000', 'purpose' => 'customer_login'])
            ->assertOk()
            ->assertJsonPath('data.sandbox', true);

        Queue::assertNothingPushed();
    }
}
