<?php

namespace Tests\Feature\Domain\Subscription;

use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesMerchants;
use Tests\TestCase;

/**
 * جریان E2E خرید اشتراک با دروازه ZarinPal — Sprint 7.
 * subscribe → StartPay → callback مرورگر (Authority/Status) → verify سمت سرور → فعال‌سازی.
 */
final class ZarinpalPaymentFlowTest extends TestCase
{
    use CreatesMerchants, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        config([
            'gamification.payments.gateway' => 'zarinpal',
            'gamification.payments.zarinpal.merchant_id' => 'test-merchant-id',
            'gamification.payments.zarinpal.panel_return_url' => '/panel#/subscription',
        ]);
    }

    /** کمپین خرید: POST /subscriptions با فیک Request دروازه → پرداخت pending با reference=Authority */
    private function subscribeToBasic(): array
    {
        [$token, $store] = $this->createMerchantWithStore();
        $basic = Plan::query()->where('slug', 'basic')->first();

        Http::fake([
            'payment.zarinpal.com/pg/v4/payment/request.json' => Http::response([
                'data' => ['code' => 100, 'authority' => 'A0000FLOWAUTHORITY'],
                'errors' => [],
            ], 200),
        ]);

        $response = $this->withToken($token)
            ->withHeader('X-Store-Id', (string) $store['id'])
            ->postJson('/api/v1/subscriptions', ['plan_id' => $basic->id]);

        $response->assertOk();

        return [$store, $basic];
    }

    public function test_subscribe_returns_zarinpal_startpay_url(): void
    {
        [$store, $basic] = $this->subscribeToBasic();

        $payment = Payment::query()->where('store_id', $store['id'])->latest('id')->first();

        $this->assertNotNull($payment);
        $this->assertSame('A0000FLOWAUTHORITY', $payment->reference);
        $this->assertSame('zarinpal', $payment->gateway);
        $this->assertSame(Payment::STATUS_PENDING, $payment->status);
    }

    public function test_browser_callback_with_ok_status_activates_subscription(): void
    {
        [$store, $basic] = $this->subscribeToBasic();

        Http::fake([
            'payment.zarinpal.com/pg/v4/payment/verify.json' => Http::response([
                'data' => ['code' => 100, 'ref_id' => 987654],
                'errors' => [],
            ], 200),
        ]);

        // ZarinPal مرورگر را با GET و Authority/Status بازمی‌گرداند
        $response = $this->get('/api/v1/payments/zarinpal/callback?Authority=A0000FLOWAUTHORITY&Status=OK');

        $response->assertRedirect();

        $location = (string) $response->headers->get('Location');
        $this->assertStringContainsString('/panel#/subscription', $location);
        $this->assertStringContainsString('payment=paid', $location);

        $payment = Payment::query()->where('store_id', $store['id'])->latest('id')->first();
        $this->assertSame(Payment::STATUS_PAID, $payment->status);
        $this->assertNotNull($payment->paid_at);

        // اشتراک basic فعال + فاکتور صادر شده
        $storeModel = Store::find($store['id']);
        $subscription = $storeModel->activeSubscription();
        $this->assertSame('basic', $subscription->plan->slug);
        $this->assertDatabaseCount(Invoice::class, 1);
    }

    public function test_duplicate_browser_callback_is_idempotent(): void
    {
        [$store, $basic] = $this->subscribeToBasic();

        Http::fake([
            'payment.zarinpal.com/pg/v4/payment/verify.json' => Http::response([
                'data' => ['code' => 101, 'ref_id' => 987654],
                'errors' => [],
            ], 200),
        ]);

        foreach ([1, 2] as $_) {
            $response = $this->get('/api/v1/payments/zarinpal/callback?Authority=A0000FLOWAUTHORITY&Status=OK');
            $response->assertRedirect();

            $this->assertStringContainsString('payment=paid', (string) $response->headers->get('Location'));
        }

        // callback تکراری هرگز دو اشتراک یا دو فاکتور نمی‌سازد (فصل ۸-۳)
        $this->assertDatabaseCount(Invoice::class, 1);
        $this->assertDatabaseCount('subscriptions', 2); // free اولیه + basic
    }

    public function test_browser_callback_with_nok_marks_payment_failed(): void
    {
        [$store, $basic] = $this->subscribeToBasic();

        Http::fake(); // NOK نباید هیچ تماس Verify بزند

        $response = $this->get('/api/v1/payments/zarinpal/callback?Authority=A0000FLOWAUTHORITY&Status=NOK');

        $response->assertRedirect();
        $this->assertStringContainsString('payment=failed', (string) $response->headers->get('Location'));

        $payment = Payment::query()->where('store_id', $store['id'])->latest('id')->first();
        $this->assertSame(Payment::STATUS_FAILED, $payment->status);

        // اشتراک همچنان free است
        $this->assertSame('free', Store::find($store['id'])->activeSubscription()->plan->slug);
        $this->assertDatabaseHas(AuditLog::class, ['action' => 'payment.failed']);
    }

    public function test_callback_with_unknown_authority_redirects_failed_without_side_effect(): void
    {
        $this->subscribeToBasic();

        Http::fake();

        $response = $this->get('/api/v1/payments/zarinpal/callback?Authority=A0000UNKNOWN000000&Status=OK');

        $response->assertRedirect();
        $this->assertStringContainsString('payment=failed', (string) $response->headers->get('Location'));

        // هیچ پرداختی تغییر وضعیت نداده و اشتراکی فعال نشده
        $this->assertSame(Payment::STATUS_PENDING, Payment::query()->latest('id')->first()->status);
        $this->assertDatabaseCount(Invoice::class, 0);
    }

    public function test_verify_api_failure_redirects_failed(): void
    {
        [$store, $basic] = $this->subscribeToBasic();

        // دروازه مبلغ نامعتبر را پاس می‌کند → Verify code=-50 → پرداخت failed
        Http::fake([
            'payment.zarinpal.com/pg/v4/payment/verify.json' => Http::response([
                'data' => [],
                'errors' => ['code' => -50, 'message' => 'Paid amount is not equal'],
            ], 200),
        ]);

        $response = $this->get('/api/v1/payments/zarinpal/callback?Authority=A0000FLOWAUTHORITY&Status=OK');

        $response->assertRedirect();
        $this->assertStringContainsString('payment=failed', (string) $response->headers->get('Location'));

        $payment = Payment::query()->where('store_id', $store['id'])->latest('id')->first();
        $this->assertSame(Payment::STATUS_FAILED, $payment->status);
        $this->assertSame('free', Store::find($store['id'])->activeSubscription()->plan->slug);
    }
}
