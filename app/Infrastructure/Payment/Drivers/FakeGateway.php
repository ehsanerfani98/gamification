<?php

namespace App\Infrastructure\Payment\Drivers;

use App\Infrastructure\Payment\PaymentGateway;
use App\Models\Payment;

/**
 * دروازه پرداخت آزمایشی برای MVP و تست‌ها.
 * جریان واقعی (درخواست → ریدایرکت → callback → verify) را شبیه‌سازی می‌کند
 * تا بدون سرویس پرداخت واقعی، چرخه اشتراک قابل تست باشد.
 */
final class FakeGateway implements PaymentGateway
{
    /** @return array{redirect_url: string, reference: string} */
    public function request(Payment $payment): array
    {
        return [
            'redirect_url' => url('/payments/fake/'.$payment->id),
            'reference' => 'FAKE-'.$payment->id.'-'.bin2hex(random_bytes(4)),
        ];
    }

    /** @param  array<string, mixed>  $callbackPayload */
    public function verify(Payment $payment, array $callbackPayload): bool
    {
        return ($callbackPayload['status'] ?? '') === 'paid';
    }
}
