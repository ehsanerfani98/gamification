<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Subscription\Actions\CompletePaymentAction;
use App\Http\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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

    /**
     * GET /api/v1/payments/zarinpal/callback — بازگشت مرورگر از دروازه ZarinPal (Sprint 7).
     *
     * ZarinPal پس از پرداخت، کاربر را با پارامترهای Authority و Status به این آدرس
     * ریدایرکت می‌کند؛ تأیید نهایی همیشه سمت سرور با API وریفای انجام می‌شود.
     * خروجی: ریدایرکت مرورگر به پنل با پارامتر payment=paid|failed.
     */
    public function zarinpalCallback(Request $request): RedirectResponse
    {
        $authority = (string) $request->query('Authority', $request->query('authority', ''));
        $status = strtoupper((string) $request->query('Status', $request->query('status', '')));

        $payment = $authority !== ''
            ? Payment::query()->where('reference', $authority)->first()
            : null;

        // ریدایرکت به پنل — پارامترها داخل hash تا hash-router پنل آن‌ها را بخواند
        $redirectToPanel = function (bool $paid) use ($payment): RedirectResponse {
            $base = (string) config('gamification.payments.zarinpal.panel_return_url', '/panel#/subscription');

            // مسیر نسبی → URL مطلق (استاندارد Location header)
            if (str_starts_with($base, '/')) {
                $base = url($base);
            }

            $separator = str_contains($base, '?') ? '&' : '?';

            return redirect()->away($base.$separator.'payment='.($paid ? 'paid' : 'failed').('&payment_id='.($payment->id ?? 0)));
        };

        // Authority نامعتبر/ناشناس — بدون تغییر وضعیت، فقط بازگشت به پنل (بدون افشا)
        if (! $payment) {
            Log::warning('ZarinPal callback with unknown authority', ['authority' => $authority]);

            return $redirectToPanel(false);
        }

        // انصراف/خطای دروازه (Status=NOK) — تأیید سمت سرور لازم نیست
        if ($status !== 'OK') {
            $payment->markFailed();

            AuditLog::record('payment.failed', null, $payment, ['via' => 'zarinpal.callback']);

            return $redirectToPanel(false);
        }

        try {
            app(CompletePaymentAction::class)->handle($payment, [
                'authority' => $authority,
                'status' => 'OK',
            ]);
        } catch (\Throwable $exception) {
            // خطای وریفای (درگاه در دسترس نیست/مبلغ نامعتبر/…) — در ACTION پرداخت failed می‌شود
            Log::warning('ZarinPal callback completion failed', [
                'payment_id' => $payment->id,
                'error' => $exception->getMessage(),
            ]);

            return $redirectToPanel(false);
        }

        return $redirectToPanel(true);
    }
}
