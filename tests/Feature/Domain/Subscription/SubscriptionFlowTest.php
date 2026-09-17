<?php

namespace Tests\Feature\Domain\Subscription;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesMerchants;
use Tests\TestCase;

/**
 * سناریوی E2E خرید اشتراک — Definition of Done فصل ۱۰ (Sprint 1):
 * ثبت‌نام → ساخت Store → خرید اشتراک → پرداخت → فعال‌سازی و فاکتور.
 */
final class SubscriptionFlowTest extends TestCase
{
    use CreatesMerchants, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(); // GameSeeder + PlanSeeder
    }

    public function test_plans_are_listed_with_features_and_games(): void
    {
        [$token, $store] = $this->createMerchantWithStore();

        $response = $this->withToken($token)
            ->withHeader('X-Store-Id', (string) $store['id'])
            ->getJson('/api/v1/plans');

        $response->assertOk();
        $plans = collect($response->json('data.plans'));
        $this->assertSame(['free', 'basic', 'pro'], $plans->pluck('slug')->all());
        $this->assertSame(1, $plans->firstWhere('slug', 'free')['features']['max_active_campaigns']);
    }

    public function test_subscribe_to_paid_plan_creates_pending_payment(): void
    {
        [$token, $store] = $this->createMerchantWithStore();
        $basic = Plan::query()->where('slug', 'basic')->first();

        $response = $this->withToken($token)
            ->withHeader('X-Store-Id', (string) $store['id'])
            ->postJson('/api/v1/subscriptions', ['plan_id' => $basic->id]);

        $response->assertOk()
            ->assertJsonPath('data.payment.status', 'pending');

        $this->assertNotNull($response->json('data.payment.redirect_url'));
        $this->assertStringStartsWith('FAKE-', $response->json('data.payment.reference'));

        $this->assertDatabaseHas(Payment::class, [
            'store_id' => $store['id'],
            'plan_id' => $basic->id,
            'status' => 'pending',
            'amount_irt' => 199000,
        ]);
    }

    public function test_paid_callback_activates_subscription_and_creates_invoice(): void
    {
        [$token, $store] = $this->createMerchantWithStore();
        $basic = Plan::query()->where('slug', 'basic')->first();

        $paymentId = $this->withToken($token)
            ->withHeader('X-Store-Id', (string) $store['id'])
            ->postJson('/api/v1/subscriptions', ['plan_id' => $basic->id])
            ->json('data.payment.id');

        // callback دروازه (FakeGateway: status=paid)
        $response = $this->postJson('/api/v1/payments/callback', [
            'payment_id' => $paymentId,
            'status' => 'paid',
        ]);

        $response->assertOk()->assertJsonPath('data.payment.status', 'paid');

        $storeModel = Store::find($store['id']);
        $subscription = $storeModel->activeSubscription();

        $this->assertNotNull($subscription);
        $this->assertSame('basic', $subscription->plan->slug);
        $this->assertTrue($subscription->ends_at->isFuture());

        $this->assertDatabaseCount('invoices', 1);
        $this->assertMatchesRegularExpression('/^INV-\d{6}-\d{5}$/', Invoice::first()->number);
    }

    public function test_duplicate_callback_is_idempotent(): void
    {
        [$token, $store] = $this->createMerchantWithStore();
        $basic = Plan::query()->where('slug', 'basic')->first();

        $paymentId = $this->withToken($token)
            ->withHeader('X-Store-Id', (string) $store['id'])
            ->postJson('/api/v1/subscriptions', ['plan_id' => $basic->id])
            ->json('data.payment.id');

        foreach ([1, 2] as $_) {
            $this->postJson('/api/v1/payments/callback', [
                'payment_id' => $paymentId,
                'status' => 'paid',
            ])->assertOk();
        }

        // callback تکراری هرگز دو اشتراک یا دو فاکتور نمی‌سازد (فصل ۸-۳)
        $this->assertDatabaseCount('invoices', 1);
        $this->assertDatabaseCount('subscriptions', 2); // free اولیه + basic
    }

    public function test_failed_callback_marks_payment_failed_without_subscription(): void
    {
        [$token, $store] = $this->createMerchantWithStore();
        $basic = Plan::query()->where('slug', 'basic')->first();

        $paymentId = $this->withToken($token)
            ->withHeader('X-Store-Id', (string) $store['id'])
            ->postJson('/api/v1/subscriptions', ['plan_id' => $basic->id])
            ->json('data.payment.id');

        $this->postJson('/api/v1/payments/callback', [
            'payment_id' => $paymentId,
            'status' => 'failed',
        ])->assertStatus(402)->assertJsonPath('error.code', 'PAYMENT_FAILED');

        $storeModel = Store::find($store['id']);
        $this->assertSame('free', $storeModel->activeSubscription()->plan->slug);
    }

    public function test_free_plan_activates_directly_without_payment(): void
    {
        [$token, $store] = $this->createMerchantWithStore();
        $free = Plan::query()->where('slug', 'free')->first();

        $response = $this->withToken($token)
            ->withHeader('X-Store-Id', (string) $store['id'])
            ->postJson('/api/v1/subscriptions', ['plan_id' => $free->id]);

        $response->assertOk()
            ->assertJsonPath('data.subscription.status', 'active')
            ->assertJsonPath('data.payment', null);
    }

    public function test_show_returns_current_features(): void
    {
        [$token, $store] = $this->createMerchantWithStore();

        $response = $this->withToken($token)
            ->withHeader('X-Store-Id', (string) $store['id'])
            ->getJson('/api/v1/subscriptions');

        $response->assertOk()
            ->assertJsonPath('data.subscription.plan.slug', 'free')
            ->assertJsonPath('data.subscription.status', 'active');

        $this->assertSame(1, $response->json('data.features.max_active_campaigns'));
    }
}
