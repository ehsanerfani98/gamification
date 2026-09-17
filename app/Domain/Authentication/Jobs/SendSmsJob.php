<?php

namespace App\Domain\Authentication\Jobs;

use App\Infrastructure\Sms\SmsChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * ارسال پیامک از صف — فصل ۲-۴: کارهای زمان‌بر به Queue سپرده می‌شوند
 * تا پاسخ API زیر ۲۰۰ میلی‌ثانیه بماند. Job با Retry پلکانی Idempotent است.
 */
final class SendSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly string $phone,
        public readonly string $message,
    ) {}

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [5, 30, 120];
    }

    public function handle(SmsChannel $sms): void
    {
        $sms->send($this->phone, $this->message);
    }
}
