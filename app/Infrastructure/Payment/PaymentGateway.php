<?php

namespace App\Infrastructure\Payment;

use App\Models\Payment;

/**
 * قرارداد انتزاعی دروازه پرداخت — فصل ۷-۳ سند معماری.
 *
 * سرویس‌دهنده‌های ایرانی (ZarinPal, IDPay, …) با پیاده‌سازی همین قرارداد
 * تعویض می‌شوند؛ تراکنش با شناسه مرجع و وضعیت ذخیره می‌شود.
 */
interface PaymentGateway
{
    /**
     * شروع پرداخت — خروجی شامل URL ریدایرکت کاربر و شناسه مرجع دروازه.
     *
     * @return array{redirect_url: string, reference: string}
     */
    public function request(Payment $payment): array;

    /**
     * تأیید پرداخت پس از callback — باید در سمت سرور با API دروازه بررسی شود،
     * نه صرفاً با پارامترهای ورودی کاربر.
     *
     * @param  array<string, mixed>  $callbackPayload
     */
    public function verify(Payment $payment, array $callbackPayload): bool;
}
