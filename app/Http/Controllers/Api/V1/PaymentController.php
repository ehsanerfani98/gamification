<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Subscription\Actions\CompletePaymentAction;
use App\Http\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class PaymentController extends Controller
{
    use ApiResponse;

    /** POST /api/v1/payments/callback — بازگشت دروازه پرداخت (فصل ۸-۲) */
    public function callback(Request $request): JsonResponse
    {
        $data = $request->validate([
            'payment_id' => ['required', 'integer', 'exists:payments,id'],
            'status' => ['required', 'string', Rule::in(['paid', 'failed'])],
        ]);

        $payment = Payment::query()->findOrFail($data['payment_id']);

        if ($data['status'] === 'failed') {
            $payment->markFailed();

            AuditLog::record('payment.failed', null, $payment, ['via' => 'callback']);

            return $this->fail('PAYMENT_FAILED', 'پرداخت ناموفق بود.', 402);
        }

        $payment = app(CompletePaymentAction::class)->handle($payment, $request->all());

        $subscription = $payment->store->subscriptions()->latest('id')->first();

        return $this->ok([
            'payment' => [
                'id' => $payment->id,
                'status' => $payment->status,
                'paid_at' => $payment->paid_at?->toIso8601String(),
            ],
            'subscription' => $subscription?->only(['id', 'status', 'plan_id', 'starts_at', 'ends_at']),
        ]);
    }
}
