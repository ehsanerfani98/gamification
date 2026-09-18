<?php

namespace App\Domain\Subscription\Actions;

use App\Domain\Subscription\Events\SubscriptionChanged;
use App\Infrastructure\Payment\PaymentGateway;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Store;
use App\Models\Subscription;
use App\Support\Settings\SiteSettingsService;
use Illuminate\Support\Facades\DB;

/**
 * شروع خرید اشتراک — فصل ۷-۳ سند معماری.
 *
 * Plan رایگان مستقیم فعال می‌شود؛ سایر Planها یک Payment pending می‌سازند
 * و با callback دروازه (CompletePaymentAction) نهایی می‌شوند.
 */
final class SubscribeAction
{
    public function __construct(
        private readonly PaymentGateway $gateway,
    ) {}

    /** @return array{payment: ?Payment, subscription: ?Subscription, redirect_url: ?string} */
    public function handle(Store $store, Plan $plan): array
    {
        if ((int) $plan->price_irt === 0) {
            return [
                'payment' => null,
                'subscription' => $this->activatePlan($store, $plan),
                'redirect_url' => null,
            ];
        }

        $payment = DB::transaction(function () use ($store, $plan): Payment {
            $payment = $store->payments()->create([
                'plan_id' => $plan->id,
                'amount_irt' => $plan->price_irt,
                // دروازه مؤثر (تنظیمات پنل مقدم بر env) — Sprint 9
                'gateway' => rescue(fn () => app(SiteSettingsService::class)->paymentGateway(), config('gamification.payments.gateway', 'fake'), false),
                'status' => Payment::STATUS_PENDING,
                'meta' => [
                    'billing_period' => $plan->billing_period,
                    // رد سندباکس برای Audit — پرداخت آزمایشی وجهی جابه‌جا نمی‌کند (Sprint 8)
                    'sandbox' => rescue(fn () => app(SiteSettingsService::class)->paymentSandboxEnabled(), false, false),
                ],
            ]);

            $payload = $this->gateway->request($payment);

            $payment->forceFill(['reference' => $payload['reference']])->save();
            $payment->setAttribute('redirect_url', $payload['redirect_url']);

            return $payment;
        });

        AuditLog::record('payment.requested', auth()->user(), $payment);

        return [
            'payment' => $payment,
            'subscription' => null,
            'redirect_url' => $payment->getAttribute('redirect_url'),
        ];
    }

    /**
     * فعال‌سازی Plan برای Store — جایگزینی اشتراک‌های فعال قبلی.
     * هر گذار Event پیام‌های SubscriptionChanged منتشر می‌کند.
     */
    public function activatePlan(Store $store, Plan $plan): Subscription
    {
        $subscription = DB::transaction(function () use ($store, $plan): Subscription {
            $store->subscriptions()->active()
                ->get()
                ->each(fn (Subscription $s) => $s->cancel());

            $endsAt = null;

            if ((int) $plan->price_irt > 0) {
                $endsAt = $plan->billing_period === 'yearly'
                    ? now()->addYear()
                    : now()->addMonth();
            } // Plan رایگان بدون انقضا

            $subscription = $store->subscriptions()->create([
                'plan_id' => $plan->id,
                'status' => Subscription::STATUS_ACTIVE,
                'starts_at' => now(),
                'ends_at' => $endsAt,
            ]);

            SubscriptionChanged::dispatch($subscription, 'none', Subscription::STATUS_ACTIVE);

            return $subscription;
        });

        AuditLog::record('subscription.activated', auth()->user(), $subscription);

        return $subscription;
    }
}
