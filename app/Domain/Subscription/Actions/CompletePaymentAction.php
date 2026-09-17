<?php

namespace App\Domain\Subscription\Actions;

use App\Infrastructure\Payment\PaymentGateway;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Payment;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Facades\DB;

/**
 * نهایی‌سازی پرداخت پس از callback دروازه — فصل ۷-۳.
 *
 * تأیید فقط سمت سرور با API دروازه انجام می‌شود؛ در موفقیت:
 * پرداخت Paid، اشتراک فعال، فاکتور صادر و Event منتشر می‌شود — همه در یک Transaction.
 * Callback تکراری Idempotent پاسخ می‌دهد (Idempotency — فصل ۸-۳).
 */
final class CompletePaymentAction
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly SubscribeAction $subscribe,
    ) {}

    public function handle(Payment $payment, array $callbackPayload): Payment
    {
        if ($payment->status === Payment::STATUS_PAID) {
            return $payment; // Idempotent
        }

        if (! $this->gateway->verify($payment, $callbackPayload)) {
            $payment->markFailed();

            AuditLog::record('payment.failed', null, $payment);

            throw new ApiException('PAYMENT_FAILED', 'تأیید پرداخت ناموفق بود.', 402);
        }

        DB::transaction(function () use ($payment): void {
            $payment->markPaid();

            $this->subscribe->activatePlan($payment->store, $payment->plan);

            Invoice::create([
                'store_id' => $payment->store_id,
                'payment_id' => $payment->id,
                'number' => Invoice::nextNumber(),
                'amount_irt' => $payment->amount_irt,
                'issued_at' => now(),
            ]);
        });

        AuditLog::record('payment.completed', null, $payment);

        return $payment;
    }
}
