<?php

namespace App\Infrastructure\Payment\Drivers;

use App\Infrastructure\Payment\PaymentGateway;
use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * دروازه پرداخت ZarinPal — Sprint 7، انتخاب کاربر (API نسخه ۴).
 *
 * جریان استاندارد پرداخت (فصل ۷-۳ سند معماری):
 *   ۱) request()  → POST payment/request.json → Authority + URL پرداخت (StartPay)
 *   ۲) کاربر در دروازه پرداخت می‌کند و به callback_url (GET) بازمی‌گردد
 *   ۳) verify()   → POST payment/verify.json — تأیید فقط سمت سرور با API دروازه،
 *                   هرگز با پارامترهای ورودی کاربر (اصل امنیتی فصل ۱۰)
 *
 * نکته مبلغ: مبالغ سیستم به تومان (amount_irt) ذخیره می‌شوند و ZarinPal
 * ریال می‌پذیرد — تبدیل ×۱۰ با config قابل تغییر است (ZARINPAL_TOMAN_TO_RIAL).
 *
 * کد ۱۰۰ = تأیید موفق؛ کد ۱۰۱ = قبلاً تأیید شده (Idempotent → موفق تلقی می‌شود).
 */
final class ZarinpalGateway implements PaymentGateway
{
    private const REQUEST_PATH = '/pg/v4/payment/request.json';

    private const VERIFY_PATH = '/pg/v4/payment/verify.json';

    /** کدهای موفق Verify: ۱۰۰ (موفق) و ۱۰۱ (قبلاً وریفای شده) */
    private const VERIFY_OK_CODES = [100, 101];

    public function __construct(
        private readonly string $merchantId,
        private readonly string $callbackUrl,
        private readonly string $baseUrl = 'https://payment.zarinpal.com',
        private readonly bool $tomanToRial = true,
        private readonly string $description = 'خرید اشتراک پلتفرم گیمیفیکیشن',
        private readonly int $timeout = 15,
    ) {}

    /** @return array{redirect_url: string, reference: string} */
    public function request(Payment $payment): array
    {
        $response = Http::baseUrl($this->baseUrl)
            ->timeout($this->timeout)
            ->acceptJson()
            ->asJson()
            ->post(self::REQUEST_PATH, [
                'merchant_id' => $this->merchantId,
                'amount' => $this->amountInRial($payment),
                'callback_url' => $this->callbackUrl,
                'description' => $this->description,
                'metadata' => [
                    'payment_id' => $payment->id,
                    'store_id' => $payment->store_id,
                ],
            ]);

        $payload = $response->json() ?? [];
        $authority = $payload['data']['authority'] ?? null;

        if (! $response->successful()
            || ($payload['data']['code'] ?? null) !== 100
            || ! is_string($authority)
            || $authority === '') {
            Log::warning('ZarinPal payment request failed', [
                'payment_id' => $payment->id,
                'response' => $payload,
            ]);

            throw new RuntimeException(sprintf(
                'ZarinPal payment request failed (HTTP %d): %s',
                $response->status(),
                json_encode($payload['errors'] ?? $payload, JSON_UNESCAPED_UNICODE) ?: 'non-json response',
            ));
        }

        return [
            'redirect_url' => $this->baseUrl.'/pg/StartPay/'.$authority,
            'reference' => $authority,
        ];
    }

    /** @param  array<string, mixed>  $callbackPayload */
    public function verify(Payment $payment, array $callbackPayload): bool
    {
        // ZarinPal با GET و پارامترهای Authority و Status به callback بازمی‌گردد
        $status = strtoupper((string) ($callbackPayload['status'] ?? $callbackPayload['Status'] ?? ''));
        $authority = (string) ($callbackPayload['authority'] ?? $callbackPayload['Authority'] ?? '');

        // دفاع امنیتی: callback باید OK باشد و Authority دقیقاً همان Authority ثبت‌شده
        // این پرداخت باشد — هر authority دیگری حتی با امضای معتبر رد می‌شود.
        if ($status !== 'OK'
            || $authority === ''
            || ! hash_equals((string) $payment->reference, $authority)) {
            return false;
        }

        $response = Http::baseUrl($this->baseUrl)
            ->timeout($this->timeout)
            ->acceptJson()
            ->asJson()
            ->post(self::VERIFY_PATH, [
                'merchant_id' => $this->merchantId,
                'amount' => $this->amountInRial($payment),
                'authority' => $authority,
            ]);

        $payload = $response->json() ?? [];
        $code = $payload['data']['code'] ?? null;

        if (in_array($code, self::VERIFY_OK_CODES, true)) {
            // ref_id شماره مرجع بانکی برای پیگیری — در لاگ نگه‌داری می‌شود
            Log::info('ZarinPal payment verified', [
                'payment_id' => $payment->id,
                'ref_id' => $payload['data']['ref_id'] ?? null,
            ]);

            return true;
        }

        Log::warning('ZarinPal verify failed', [
            'payment_id' => $payment->id,
            'response' => $payload,
        ]);

        return false;
    }

    /** مبلغ تراکنش به ریال برای ارسال به ZarinPal */
    private function amountInRial(Payment $payment): int
    {
        return (int) $payment->amount_irt * ($this->tomanToRial ? 10 : 1);
    }
}
