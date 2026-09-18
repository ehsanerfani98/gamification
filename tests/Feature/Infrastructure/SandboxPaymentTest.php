<?php

namespace Tests\Feature\Infrastructure;

use App\Infrastructure\Payment\Drivers\SandboxGateway;
use App\Infrastructure\Payment\Drivers\ZarinpalGateway;
use App\Infrastructure\Payment\PaymentGateway;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Store;
use App\Support\Settings\SiteSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesMerchants;
use Tests\TestCase;

/**
 * دروازه پرداخت سندباکس — Sprint 8 (بتای ۵ فروشگاه).
 * همان جریان کامل دروازه واقعی (Request → صفحه پرداخت → callback → verify →
 * فعال‌سازی اشتراک + فاکتور) بدون تماس خارجی و بدون کلید.
 */
final class SandboxPaymentTest extends TestCase
{
    use CreatesMerchants, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    private function enablePaymentSandbox(): void
    {
        app(SiteSettingsService::class)->update(['payment_sandbox' => true]);
    }

    private function subscribeToBasic(string $token, array $store): Payment
    {
        $basic = Plan::query()->where('slug', 'basic')->first();

        $this->withToken($token)
            ->withHeader('X-Store-Id', (string) $store['id'])
            ->postJson('/api/v1/subscriptions', ['plan_id' => $basic->id])
            ->assertOk();

        return Payment::query()->where('store_id', $store['id'])->latest('id')->first();
    }

    public function test_payment_sandbox_binding_overrides_env_gateway(): void
    {
        config(['gamification.payments.gateway' => 'zarinpal']);

        $this->enablePaymentSandbox();
        $this->assertInstanceOf(SandboxGateway::class, app(PaymentGateway::class));

        // خاموشی سندباکس → env اعمال می‌شود
        app(SiteSettingsService::class)->update(['payment_sandbox' => false]);
        $this->assertInstanceOf(ZarinpalGateway::class, app(PaymentGateway::class));
    }

    public function test_subscribe_with_sandbox_returns_internal_checkout_url(): void
    {
        $this->enablePaymentSandbox();
        [$token, $store] = $this->createMerchantWithStore();

        $payment = $this->subscribeToBasic($token, $store);

        $this->assertSame(Payment::STATUS_PENDING, $payment->status);
        $this->assertStringStartsWith('SBX-', (string) $payment->reference);
        $this->assertTrue($payment->meta['sandbox']); // رد سندباکس برای Audit

        $response = $this->withToken($token)
            ->withHeader('X-Store-Id', (string) $store['id'])
            ->postJson('/api/v1/subscriptions', ['plan_id' => $payment->plan_id]);
        $response->assertOk();
        $this->assertStringContainsString('/payments/sandbox/', (string) $response->json('data.payment.redirect_url'));
    }

    public function test_sandbox_checkout_page_shows_payment_details(): void
    {
        $this->enablePaymentSandbox();
        [$token, $store] = $this->createMerchantWithStore();
        $payment = $this->subscribeToBasic($token, $store);

        $response = $this->get('/payments/sandbox/'.$payment->id);

        $response->assertOk()
            ->assertSee($store['name'])
            ->assertSee('199,000')
            ->assertSee((string) $payment->reference, false)
            ->assertSee('سندباکس');

        // پرداخت ناموجود → 404
        $this->get('/payments/sandbox/99999')->assertNotFound();
    }

    public function test_sandbox_checkout_links_use_query_string_callback(): void
    {
        // رگرسیون: url($path, $array) پارامترها را segment مسیر می‌کند → callback سندباکس 404 می‌شد
        $this->enablePaymentSandbox();
        [$token, $store] = $this->createMerchantWithStore();
        $payment = $this->subscribeToBasic($token, $store);

        $html = (string) $this->get('/payments/sandbox/'.$payment->id)->getContent();

        // مقایسه فقط path + query (مستقل از دامنه/پورت) — & در HTML به &amp; escape می‌شود
        $expected = parse_url(route('payments.zarinpal.callback'), PHP_URL_PATH)
            .'?'.http_build_query(['Authority' => $payment->reference, 'Status' => 'OK']);
        $this->assertStringContainsString(htmlspecialchars($expected, ENT_QUOTES), $html);

        // لینک نباید به‌صورت path segment باشد (سلول باگ قبلی)
        $this->assertStringNotContainsString('/callback/'.$payment->reference, $html);

        // لینک «پرداخت موفق» باید واقعاً به 404 نخورد و redirect پنل بدهد
        $followed = $this->get(route('payments.zarinpal.callback', [
            'Authority' => $payment->reference,
            'Status' => 'OK',
        ]));
        $followed->assertRedirect();
        $this->assertStringContainsString('payment=paid', (string) $followed->headers->get('Location'));
    }

    public function test_sandbox_ok_callback_activates_subscription_and_creates_invoice(): void
    {
        $this->enablePaymentSandbox();
        [$token, $store] = $this->createMerchantWithStore();
        $payment = $this->subscribeToBasic($token, $store);

        // کلیک «پرداخت موفق» در صفحه سندباکس → همان callback دروازه واقعی (query string مثل دروازه)
        $response = $this->get('/api/v1/payments/zarinpal/callback?'.http_build_query([
            'Authority' => $payment->reference,
            'Status' => 'OK',
        ]));

        $response->assertRedirect();
        $this->assertStringContainsString('payment=paid', (string) $response->headers->get('Location'));

        $payment->refresh();
        $this->assertSame(Payment::STATUS_PAID, $payment->status);

        $this->assertSame('basic', Store::find($store['id'])->activeSubscription()->plan->slug);
        $this->assertDatabaseCount(Invoice::class, 1);
    }

    public function test_sandbox_nok_callback_marks_payment_failed(): void
    {
        $this->enablePaymentSandbox();
        [$token, $store] = $this->createMerchantWithStore();
        $payment = $this->subscribeToBasic($token, $store);

        $response = $this->get('/api/v1/payments/zarinpal/callback?'.http_build_query([
            'Authority' => $payment->reference,
            'Status' => 'NOK',
        ]));

        $response->assertRedirect();
        $this->assertStringContainsString('payment=failed', (string) $response->headers->get('Location'));

        $this->assertSame(Payment::STATUS_FAILED, $payment->refresh()->status);
        $this->assertSame('free', Store::find($store['id'])->activeSubscription()->plan->slug);
        $this->assertDatabaseCount(Invoice::class, 0);
    }

    public function test_sandbox_callback_rejects_mismatched_authority(): void
    {
        $this->enablePaymentSandbox();
        [$token, $store] = $this->createMerchantWithStore();
        $payment = $this->subscribeToBasic($token, $store);

        // Authority جعلی متفاوت با reference رکورد → بدون تغییر وضعیت
        $response = $this->get('/api/v1/payments/zarinpal/callback?'.http_build_query([
            'Authority' => 'SBX-FAKEAUTH0000',
            'Status' => 'OK',
        ]));

        $response->assertRedirect();
        $this->assertStringContainsString('payment=failed', (string) $response->headers->get('Location'));
        $this->assertSame(Payment::STATUS_PENDING, $payment->refresh()->status);
        $this->assertDatabaseCount(Invoice::class, 0);
    }

    public function test_sandbox_off_keeps_fake_gateway_behavior(): void
    {
        // سندباکس خاموش + gateway=fake (پیش‌فرض env) → رفتار قبلی حفظ می‌شود
        [$token, $store] = $this->createMerchantWithStore();
        $payment = $this->subscribeToBasic($token, $store);

        $this->assertStringStartsWith('FAKE-', (string) $payment->reference);
        $this->assertFalse($payment->meta['sandbox']);
    }
}
