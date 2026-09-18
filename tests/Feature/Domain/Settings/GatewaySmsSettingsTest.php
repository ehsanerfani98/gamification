<?php

namespace Tests\Feature\Domain\Settings;

use App\Infrastructure\Payment\Drivers\FakeGateway;
use App\Infrastructure\Payment\Drivers\SandboxGateway;
use App\Infrastructure\Payment\PaymentGateway;
use App\Infrastructure\Sms\Drivers\IppanelSmsChannel;
use App\Infrastructure\Sms\Drivers\LogSmsChannel;
use App\Infrastructure\Sms\SmsChannel;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\SiteSettings;
use App\Models\Store;
use App\Models\User;
use App\Support\Settings\SiteSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesMerchants;
use Tests\TestCase;

/**
 * متغیرهای درگاه پرداخت و پیامک در تنظیمات سایت — Sprint 9.
 *
 * ۱) همه متغیرهای IPPanel و ZarinPal از پنل (PATCH /site-settings) قابل ذخیره/بروزرسانی‌اند.
 * ۲) اولویت: سندباکس‌های داخلی → مقادیر DB → پیش‌فرض env/config.
 * ۳) سندباکس رسمی زرین‌پال (sandbox.zarinpal.com — همان API v4) با کلید zarinpal_sandbox.
 * ۴) کلید API پیامک فقط ماسک‌شده برمی‌گردد و مقدار خالی = حفظ کلید فعلی.
 */
final class GatewaySmsSettingsTest extends TestCase
{
    use CreatesMerchants, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(); // PlanSeeder برای ساخت Store
    }

    /** توکن Admin: کاربر با نقش admin وارد پنل می‌شود — توکن با ability admin */
    private function adminToken(string $phone = '09123330000'): string
    {
        User::query()->firstOrCreate(['phone' => $phone], ['role' => 'admin']);

        return $this->merchantToken($phone);
    }

    /** پاسخ موفق IPPanel — برای جلوگیری از تماس واقعی حین تست‌ها */
    private function fakeIppanel(): void
    {
        Http::fake([
            'api2.ippanel.com/*' => Http::response([
                'data' => ['code' => 200, 'message_id' => 1234],
                'meta' => ['status' => true, 'message' => 'انجام شد'],
            ], 200),
        ]);
    }

    public function test_admin_can_save_all_ippanel_sms_variables(): void
    {
        // پس از ذخیره sms_channel=ippanel، جاب پیامک درخواست OTP به IPPanel می‌رود — فیک می‌کنیم
        $this->fakeIppanel();

        $this->withToken($this->adminToken())
            ->patchJson('/api/v1/site-settings', [
                'sms_channel' => 'ippanel',
                'ippanel_api_key' => 'secret-api-key-1234',
                'ippanel_originator' => '+983000505',
                'ippanel_base_url' => 'https://api2.ippanel.com',
            ])
            ->assertOk()
            ->assertJsonPath('data.sms_channel', 'ippanel')
            ->assertJsonPath('data.ippanel_originator', '+983000505')
            ->assertJsonPath('data.effective.sms_channel', 'ippanel');

        $this->assertDatabaseHas(SiteSettings::class, [
            'sms_channel' => 'ippanel',
            'ippanel_originator' => '+983000505',
        ]);

        // کلید خام هرگز در پاسخ نمی‌آید — فقط ماسک (۴ کاراکتر آخر)
        $this->withToken($this->adminToken())
            ->getJson('/api/v1/site-settings')
            ->assertOk()
            ->assertJsonPath('data.ippanel_api_key_masked', '••••••••1234')
            ->assertJsonMissing(['ippanel_api_key' => 'secret-api-key-1234']);
    }

    public function test_admin_can_save_all_zarinpal_payment_variables(): void
    {
        $this->withToken($this->adminToken())
            ->patchJson('/api/v1/site-settings', [
                'payment_gateway' => 'zarinpal',
                'zarinpal_merchant_id' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
                'zarinpal_sandbox' => true,
                'zarinpal_toman_to_rial' => false,
                'zarinpal_callback_url' => 'https://example.com/callback',
                'zarinpal_description' => 'تست خرید اشتراک',
            ])
            ->assertOk()
            ->assertJsonPath('data.payment_gateway', 'zarinpal')
            ->assertJsonPath('data.zarinpal_sandbox', true)
            ->assertJsonPath('data.zarinpal_toman_to_rial', false)
            ->assertJsonPath('data.effective.zarinpal_base_url', 'https://sandbox.zarinpal.com');

        $this->assertDatabaseHas(SiteSettings::class, [
            'payment_gateway' => 'zarinpal',
            'zarinpal_merchant_id' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
            'zarinpal_sandbox' => true,
        ]);
    }

    public function test_empty_api_key_keeps_existing_value(): void
    {
        $admin = $this->adminToken();

        $this->withToken($admin)
            ->patchJson('/api/v1/site-settings', ['ippanel_api_key' => 'first-secret-key-9876'])
            ->assertOk();

        // بروزرسانی دیگر فیلدها با کلید خالی → کلید قبلی حفظ می‌شود
        $this->withToken($admin)
            ->patchJson('/api/v1/site-settings', [
                'ippanel_api_key' => '',
                'ippanel_originator' => '+983000999',
            ])
            ->assertOk()
            ->assertJsonPath('data.ippanel_api_key_masked', '••••••••9876');

        $this->assertSame('first-secret-key-9876', SiteSettings::current()->ippanel_api_key);
        $this->assertSame('+983000999', SiteSettings::current()->ippanel_originator);
    }

    public function test_invalid_gateway_and_channel_values_are_rejected(): void
    {
        $this->withToken($this->adminToken())
            ->patchJson('/api/v1/site-settings', [
                'sms_channel' => 'kavenegar',
                'payment_gateway' => 'mellat',
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['error' => ['code', 'fields']]);
    }

    public function test_null_values_fall_back_to_env_defaults(): void
    {
        config([
            'gamification.sms.channel' => 'log',
            'gamification.payments.gateway' => 'fake',
            'gamification.payments.zarinpal.base_url' => 'https://payment.zarinpal.com',
        ]);

        // در بازه‌ای که درایور ippanel از پنل ست شده، جاب پیامک OTP فیک می‌شود
        $this->fakeIppanel();

        // مقادیر پنل ست می‌شوند…
        $this->withToken($this->adminToken())
            ->patchJson('/api/v1/site-settings', [
                'sms_channel' => 'ippanel',
                'payment_gateway' => 'zarinpal',
            ])
            ->assertOk();

        // …و سپس null می‌شوند → fallback به env
        $this->withToken($this->adminToken())
            ->patchJson('/api/v1/site-settings', [
                'sms_channel' => null,
                'payment_gateway' => null,
            ])
            ->assertOk()
            ->assertJsonPath('data.effective.sms_channel', 'log')
            ->assertJsonPath('data.effective.payment_gateway', 'fake')
            ->assertJsonPath('data.effective.zarinpal_base_url', 'https://payment.zarinpal.com');
    }

    public function test_binding_uses_db_settings_for_sms_channel(): void
    {
        // env روی log است اما DB درایور ippanel با کلید خودش را دارد
        config(['gamification.sms.channel' => 'log']);

        SiteSettings::current()->update([
            'sms_channel' => 'ippanel',
            'ippanel_api_key' => 'db-driven-key',
            'ippanel_originator' => '+983000111',
        ]);

        $this->assertInstanceOf(IppanelSmsChannel::class, app(SmsChannel::class));

        Http::fake([
            'api2.ippanel.com/*' => Http::response([
                'data' => ['code' => 200],
                'meta' => ['status' => true],
            ], 200),
        ]);

        app(SmsChannel::class)->send('09121234567', 'test');

        Http::assertSent(fn ($request) => $request->header('apikey') === ['db-driven-key']
            && $request['originator'] === '+983000111');
    }

    public function test_binding_uses_db_settings_for_payment_gateway(): void
    {
        config(['gamification.payments.gateway' => 'fake']);

        SiteSettings::current()->update([
            'payment_gateway' => 'zarinpal',
            'zarinpal_merchant_id' => 'db-merchant-id',
        ]);

        Http::fake([
            'payment.zarinpal.com/pg/v4/payment/request.json' => Http::response([
                'data' => ['code' => 100, 'authority' => 'A000DBDRIVENAUTHORITY'],
                'errors' => [],
            ], 200),
        ]);

        [$token, $store] = $this->createMerchantWithStore();
        $basic = Plan::query()->where('slug', 'basic')->first();

        $response = $this->withToken($token)
            ->withHeader('X-Store-Id', (string) $store['id'])
            ->postJson('/api/v1/subscriptions', ['plan_id' => $basic->id]);

        $response->assertOk();

        Http::assertSent(fn ($request) => $request['merchant_id'] === 'db-merchant-id');
    }

    public function test_zarinpal_official_sandbox_switches_base_url(): void
    {
        // دروازه zarinpal با سندباکس رسمی زرین‌پال از تنظیمات پنل
        SiteSettings::current()->update([
            'payment_gateway' => 'zarinpal',
            'zarinpal_merchant_id' => 'sandbox-merchant-id',
            'zarinpal_sandbox' => true,
        ]);

        $service = app(SiteSettingsService::class);
        $this->assertSame('https://sandbox.zarinpal.com', $service->zarinpalBaseUrl());

        Http::fake([
            'sandbox.zarinpal.com/pg/v4/payment/request.json' => Http::response([
                'data' => ['code' => 100, 'authority' => 'S00000000000000000000000000000SANDBOX1'],
                'errors' => [],
            ], 200),
        ]);

        [$token, $store] = $this->createMerchantWithStore();
        $basic = Plan::query()->where('slug', 'basic')->first();

        $response = $this->withToken($token)
            ->withHeader('X-Store-Id', (string) $store['id'])
            ->postJson('/api/v1/subscriptions', ['plan_id' => $basic->id]);

        $response->assertOk();

        // درخواست به محیط آزمایشگاهی رسمی زرین‌پال رفته و merchant از DB خوانده شده
        Http::assertSent(function ($request) {
            return str_starts_with($request->url(), 'https://sandbox.zarinpal.com/pg/v4/payment/request.json')
                && $request['merchant_id'] === 'sandbox-merchant-id';
        });

        $payment = Store::find($store['id'])->payments()->latest('id')->first();
        $this->assertSame('S00000000000000000000000000000SANDBOX1', $payment->reference);
    }

    public function test_zarinpal_sandbox_verified_with_same_v4_flow(): void
    {
        // وریفای روی سندباکس رسمی هم همان جریان v4 را اجرا می‌کند (code=100/101)
        SiteSettings::current()->update([
            'payment_gateway' => 'zarinpal',
            'zarinpal_sandbox' => true,
        ]);

        Http::fake([
            'sandbox.zarinpal.com/pg/v4/payment/request.json' => Http::response([
                'data' => ['code' => 100, 'authority' => 'A0000SBXVERIFYAUTH'],
                'errors' => [],
            ], 200),
            'sandbox.zarinpal.com/pg/v4/payment/verify.json' => Http::response([
                'data' => ['code' => 100, 'ref_id' => 555555],
                'errors' => [],
            ], 200),
        ]);

        [$token, $store] = $this->createMerchantWithStore();
        $basic = Plan::query()->where('slug', 'basic')->first();

        $this->withToken($token)
            ->withHeader('X-Store-Id', (string) $store['id'])
            ->postJson('/api/v1/subscriptions', ['plan_id' => $basic->id])
            ->assertOk();

        $this->get('/api/v1/payments/zarinpal/callback?Authority=A0000SBXVERIFYAUTH&Status=OK')
            ->assertRedirect();

        $payment = Store::find($store['id'])->payments()->latest('id')->first();
        $this->assertSame(Payment::STATUS_PAID, $payment->status);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'sandbox.zarinpal.com/pg/v4/payment/verify.json'));
    }

    public function test_internal_payment_sandbox_still_wins_over_settings(): void
    {
        // سندباکس داخلی روشن → حتی با payment_gateway=zarinpal در DB، شبیه‌ساز داخلی بایند می‌شود
        SiteSettings::current()->update([
            'payment_sandbox' => true,
            'payment_gateway' => 'zarinpal',
            'zarinpal_sandbox' => true,
        ]);

        $this->assertInstanceOf(SandboxGateway::class, app(PaymentGateway::class));
    }

    public function test_fake_gateway_default_when_settings_empty(): void
    {
        config(['gamification.payments.gateway' => 'fake']);

        $this->assertInstanceOf(FakeGateway::class, app(PaymentGateway::class));
        $this->assertInstanceOf(LogSmsChannel::class, app(SmsChannel::class));
    }

    public function test_settings_update_is_audited_without_raw_api_key(): void
    {
        $this->withToken($this->adminToken())
            ->patchJson('/api/v1/site-settings', [
                'ippanel_api_key' => 'very-secret-key-0000',
                'payment_gateway' => 'zarinpal',
            ])
            ->assertOk();

        $this->assertDatabaseHas(AuditLog::class, ['action' => 'site.settings_updated']);

        // کلید خام هرگز در Audit ثبت نمی‌شود
        $audit = AuditLog::query()->where('action', 'site.settings_updated')->latest('id')->first();
        $this->assertStringNotContainsString('very-secret-key-0000', (string) json_encode($audit->toArray()));
    }
}
