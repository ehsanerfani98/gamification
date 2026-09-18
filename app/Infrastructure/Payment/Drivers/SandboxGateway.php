<?php

namespace App\Infrastructure\Payment\Drivers;

use App\Infrastructure\Payment\PaymentGateway;
use App\Models\Payment;

/**
 * دروازه پرداخت سندباکس — بتای ۵ فروشگاه (Sprint 8، درخواست کاربر).
 *
 * شبیه‌ساز داخلی با «همان جریان کامل» دروازه واقعی:
 *   request() → Authority داخلی + صفحه پرداخت آزمایشی (/payments/sandbox/{id})
 *   کاربر روی «پرداخت موفق/ناموفق» کلیک می‌کند →
 *   GET /api/v1/payments/zarinpal/callback?Authority=…&Status=OK|NOK (همان مسیر ZarinPal)
 *   verify() → بدون تماس خارجی؛ فقط تطبیق Authority
 *
 * نتیجه: کل مسیر callback → CompletePaymentAction → اشتراک → فاکتور → Audit
 * دقیقاً مثل ZarinPal واقعی تست می‌شود؛ تنها منشأ نتیجه کلیک کاربر است.
 * کلید واقعی ZarinPal بعداً فقط با env فعال می‌شود (PAYMENT_GATEWAY=zarinpal).
 */
final class SandboxGateway implements PaymentGateway
{
    public function request(Payment $payment): array
    {
        // Authority داخلی با پیشوند SBX — در production هرگز ساخته نمی‌شود (سندباکس خاموش)
        $authority = 'SBX-'.bin2hex(random_bytes(8));

        return [
            'redirect_url' => url('/payments/sandbox/'.$payment->id.'?authority='.$authority),
            'reference' => $authority,
        ];
    }

    /** @param  array<string, mixed>  $callbackPayload */
    public function verify(Payment $payment, array $callbackPayload): bool
    {
        $status = strtoupper((string) ($callbackPayload['status'] ?? ''));
        $authority = (string) ($callbackPayload['authority'] ?? '');

        // همان دفاع امنیتی دروازه واقعی: Status=OK + Authority منطبق با رکورد
        return $status === 'OK'
            && $authority !== ''
            && hash_equals((string) $payment->reference, $authority);
    }
}
