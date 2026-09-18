<?php

namespace Tests\Feature\Infrastructure;

use App\Infrastructure\Payment\Drivers\ZarinpalGateway;
use App\Infrastructure\Payment\PaymentGateway;
use App\Models\Payment;
use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\Concerns\CreatesMerchants;
use Tests\TestCase;

/**
 * تست دروازه پرداخت ZarinPal — Sprint 7 (API v4).
 * صحت Request/Verify سمت سرور + دفاع‌های امنیتی (Authority نامطبق، NOK بدون تماس).
 */
final class ZarinpalGatewayTest extends TestCase
{
    use CreatesMerchants, RefreshDatabase;

    private ZarinpalGateway $gateway;

    private Payment $payment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        config([
            'gamification.payments.gateway' => 'zarinpal',
            'gamification.payments.zarinpal.merchant_id' => 'test-merchant-id',
            'gamification.payments.zarinpal.base_url' => 'https://payment.zarinpal.com',
            'gamification.payments.zarinpal.toman_to_rial' => true,
        ]);

        $this->gateway = app(PaymentGateway::class);

        [, $store] = $this->createMerchantWithStore();
        $basic = Plan::query()->where('slug', 'basic')->first();

        $this->payment = Payment::query()->create([
            'store_id' => $store['id'],
            'plan_id' => $basic->id,
            'amount_irt' => 199000, // تومان
            'gateway' => 'zarinpal',
            'status' => Payment::STATUS_PENDING,
            'reference' => 'A0000TESTAUTHORITY',
        ]);
    }

    public function test_request_returns_startpay_url_with_authority_reference(): void
    {
        Http::fake([
            'payment.zarinpal.com/pg/v4/payment/request.json' => Http::response([
                'data' => ['code' => 100, 'authority' => 'A0000NEWAUTHORITY', 'fee' => 20000],
                'errors' => [],
            ], 200),
        ]);

        $payload = $this->gateway->request($this->payment);

        $this->assertSame('A0000NEWAUTHORITY', $payload['reference']);
        $this->assertSame('https://payment.zarinpal.com/pg/StartPay/A0000NEWAUTHORITY', $payload['redirect_url']);

        // مبلغ تومانی ×۱۰ به ریال ارسال می‌شود + Merchant و callback صحیح
        Http::assertSent(function ($request) {
            return $request->url() === 'https://payment.zarinpal.com/pg/v4/payment/request.json'
                && $request['merchant_id'] === 'test-merchant-id'
                && $request['amount'] === 1990000
                && str_contains((string) $request['callback_url'], '/api/v1/payments/zarinpal/callback');
        });
    }

    public function test_request_failure_throws_runtime_exception(): void
    {
        Http::fake([
            'payment.zarinpal.com/pg/v4/payment/request.json' => Http::response([
                'data' => [],
                'errors' => ['code' => -9, 'message' => 'Invalid merchant'],
            ], 200),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid merchant');

        $this->gateway->request($this->payment);
    }

    public function test_verify_success_with_code_100(): void
    {
        Http::fake([
            'payment.zarinpal.com/pg/v4/payment/verify.json' => Http::response([
                'data' => ['code' => 100, 'ref_id' => 123456, 'card_pan' => '6037******'],
                'errors' => [],
            ], 200),
        ]);

        $this->assertTrue($this->gateway->verify($this->payment, [
            'Authority' => 'A0000TESTAUTHORITY',
            'Status' => 'OK',
        ]));

        // Verify حتماً سمت سرور با API انجام شده باشد (نه پارامترهای کاربر)
        Http::assertSent(function ($request) {
            return $request['authority'] === 'A0000TESTAUTHORITY'
                && $request['amount'] === 1990000;
        });
    }

    public function test_verify_code_101_is_idempotent_success(): void
    {
        // کد ۱۰۱ = قبلاً تأیید شده — باید موفق تلقی شود (Idempotent callback)
        Http::fake([
            'payment.zarinpal.com/pg/v4/payment/verify.json' => Http::response([
                'data' => ['code' => 101, 'ref_id' => 123456],
                'errors' => [],
            ], 200),
        ]);

        $this->assertTrue($this->gateway->verify($this->payment, [
            'authority' => 'A0000TESTAUTHORITY',
            'status' => 'OK',
        ]));
    }

    public function test_verify_rejects_nok_without_api_call(): void
    {
        // انصراف کاربر در دروازه (Status=NOK) — هیچ تماس Verify نباید زده شود
        Http::fake();

        $this->assertFalse($this->gateway->verify($this->payment, [
            'Authority' => 'A0000TESTAUTHORITY',
            'Status' => 'NOK',
        ]));

        Http::assertNothingSent();
    }

    public function test_verify_rejects_mismatched_authority_without_api_call(): void
    {
        // دفاع Cross-Payment: authority دیگر حتی با Status=OK رد می‌شود
        Http::fake();

        $this->assertFalse($this->gateway->verify($this->payment, [
            'Authority' => 'A0000DIFFERENT999',
            'Status' => 'OK',
        ]));

        Http::assertNothingSent();
    }

    public function test_verify_failure_code_returns_false(): void
    {
        // کد -۵۰ = مبلغ پرداخت‌شده با مبلغ کمتر برابر است
        Http::fake([
            'payment.zarinpal.com/pg/v4/payment/verify.json' => Http::response([
                'data' => [],
                'errors' => ['code' => -50, 'message' => 'Paid amount is not equal'],
            ], 200),
        ]);

        $this->assertFalse($this->gateway->verify($this->payment, [
            'Authority' => 'A0000TESTAUTHORITY',
            'Status' => 'OK',
        ]));
    }

    public function test_gateway_is_bound_by_config(): void
    {
        $this->assertInstanceOf(ZarinpalGateway::class, $this->gateway);

        config(['gamification.payments.gateway' => 'fake']);
        $this->assertNotInstanceOf(ZarinpalGateway::class, app(PaymentGateway::class));
    }
}
