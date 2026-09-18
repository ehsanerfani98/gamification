<?php

namespace Tests\Feature\Domain\Settings;

use App\Models\SiteSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesMerchants;
use Tests\TestCase;

/**
 * تنظیمات سایت — Sprint 8 (بتای ۵ فروشگاه).
 * دسترسی فقط Admin + تغییر سندباکس پیامک/پرداخت + دستور admin:promote.
 */
final class SiteSettingsTest extends TestCase
{
    use CreatesMerchants, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(); // PlanSeeder برای ساخت Store
    }

    /** توکن Admin: کاربر با نقش admin (Idempotent) وارد پنل می‌شود — توکن با ability admin */
    private function adminToken(string $phone = '09123330000'): string
    {
        User::query()->firstOrCreate(['phone' => $phone], ['role' => 'admin']);

        return $this->merchantToken($phone);
    }

    public function test_guest_is_unauthorized(): void
    {
        $this->getJson('/api/v1/site-settings')->assertUnauthorized();
        $this->patchJson('/api/v1/site-settings', ['sms_sandbox' => true, 'payment_sandbox' => true])->assertUnauthorized();
    }

    public function test_merchant_cannot_access_site_settings(): void
    {
        [$token, $store] = $this->createMerchantWithStore();

        $this->withToken($token)->getJson('/api/v1/site-settings')->assertForbidden();
        $this->withToken($token)
            ->patchJson('/api/v1/site-settings', ['sms_sandbox' => true, 'payment_sandbox' => false])
            ->assertForbidden();
    }

    public function test_admin_reads_default_settings(): void
    {
        $this->withToken($this->adminToken())
            ->getJson('/api/v1/site-settings')
            ->assertOk()
            ->assertJsonPath('data.sms_sandbox', false)
            ->assertJsonPath('data.payment_sandbox', false);
    }

    public function test_admin_can_toggle_sandboxes(): void
    {
        $this->withToken($this->adminToken())
            ->patchJson('/api/v1/site-settings', ['sms_sandbox' => true, 'payment_sandbox' => true])
            ->assertOk()
            ->assertJsonPath('data.sms_sandbox', true)
            ->assertJsonPath('data.payment_sandbox', true);

        $this->assertDatabaseHas(SiteSettings::class, [
            'sms_sandbox' => true,
            'payment_sandbox' => true,
        ]);

        // خاموش‌کردن مجدد (گذار رفت‌وبرگشتی)
        $this->withToken($this->adminToken())
            ->patchJson('/api/v1/site-settings', ['sms_sandbox' => false, 'payment_sandbox' => false])
            ->assertOk()
            ->assertJsonPath('data.payment_sandbox', false);
    }

    public function test_update_validates_boolean_values(): void
    {
        $this->withToken($this->adminToken())
            ->patchJson('/api/v1/site-settings', ['sms_sandbox' => 'yes-please', 'payment_sandbox' => true])
            ->assertStatus(422)
            ->assertJsonStructure(['error' => ['code', 'fields']]);
    }

    public function test_auth_me_returns_role(): void
    {
        [$token, $store] = $this->createMerchantWithStore();

        $this->withToken($token)->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.user.role', 'merchant');

        $this->withToken($this->adminToken())->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.user.role', 'admin');
    }

    public function test_admin_promote_command_grants_admin_role(): void
    {
        // کاربر ابتدا یک‌بار با OTP وارد پنل شده (رکورد users ساخته شده)
        [$token, $store] = $this->createMerchantWithStore('09124440000');

        $this->artisan('admin:promote', ['phone' => '09124440000'])
            ->assertSuccessful();

        $this->assertDatabaseHas(User::class, ['phone' => '09124440000', 'role' => 'admin']);

        // شماره ناشناس → خطا
        $this->artisan('admin:promote', ['phone' => '09125550000'])->assertFailed();
    }
}
