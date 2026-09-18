<?php

namespace App\Infrastructure\Sms\Drivers;

use App\Infrastructure\Sms\SmsChannel;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * درایور پیامک IPPanel (فراز اس‌ام‌اس) — Sprint 7، انتخاب کاربر.
 *
 * اتصال به REST API سرویس IPPanel برای ارسال پیامک تک‌گیرنده (OTP).
 * Failure ها Exception می‌شوند تا صف (SendSmsJob با tries=3 و backoff پلکانی)
 * امکان Retry خودکار داشته باشد — مطابق فصل ۲-۴ سند معماری.
 *
 * تنظیمات (config/gamification.php → sms.ippanel):
 *   IPANEL_API_KEY      کلید API پنل
 *   IPANEL_ORIGINATOR   شماره فرستنده خط
 *   IPANEL_BASE_URL     پیش‌فرض https://api2.ippanel.com
 */
final class IppanelSmsChannel implements SmsChannel
{
    /** مسیر REST ارسال پیامک تکی — مستندات IPPanel */
    private const SEND_SINGLE_PATH = '/api/v1/sms/send/webservice/single';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $originator,
        private readonly string $baseUrl = 'https://api2.ippanel.com',
        private readonly int $timeout = 10,
    ) {}

    public function send(string $phone, string $message): void
    {
        try {
            $response = Http::baseUrl($this->baseUrl)
                ->timeout($this->timeout)
                ->withHeaders(['apikey' => $this->apiKey])
                ->acceptJson()
                ->asJson()
                ->post(self::SEND_SINGLE_PATH, [
                    'originator' => $this->originator,
                    'recipient' => $phone,
                    'message' => $message,
                ]);
        } catch (ConnectionException $exception) {
            // خطای شبکه → Exception تا صف Retry کند
            throw new RuntimeException('IPPanel SMS connection failed: '.$exception->getMessage(), 0, $exception);
        }

        $payload = $response->json() ?? [];

        // پاسخ موفق IPPanel: HTTP 2xx و meta.status=true (در برخی نسخه‌ها data.code=200)
        $accepted = $response->successful()
            && (($payload['meta']['status'] ?? false) === true
                || ($payload['data']['code'] ?? null) === 200);

        if (! $accepted) {
            throw new RuntimeException(sprintf(
                'IPPanel SMS failed (HTTP %d): %s',
                $response->status(),
                json_encode($payload, JSON_UNESCAPED_UNICODE) ?: 'non-json response',
            ));
        }
    }
}
