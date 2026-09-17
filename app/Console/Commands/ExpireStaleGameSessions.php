<?php

namespace App\Console\Commands;

use App\Models\GameSession;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;

/**
 * انقضای Sessionهای راکد — فصل ۱۰ و ۵-۴ سند معماری (Sprint 6).
 * Sessionهای Started که عمر توکن یک‌بارمصرف آن‌ها گذشته به Expired می‌روند؛
 * اکشن بعدی روی آن‌ها با SESSION_EXPIRED رد می‌شود. زمان‌بندی: هر ۵ دقیقه.
 */
final class ExpireStaleGameSessions extends Command
{
    protected $signature = 'sessions:expire';

    protected $description = 'انقضای Sessionهای بازی که عمر توکن آن‌ها گذشته اما اکشنی دریافت نکرده‌اند';

    public function handle(): int
    {
        // دستور نگهدارنده سیستم‌محور است — باید همه Tenantها را ببیند
        TenantContext::forget();

        $count = GameSession::query()
            ->where('status', GameSession::STATUS_STARTED)
            ->where('token_expires_at', '<=', now())
            ->update(['status' => GameSession::STATUS_EXPIRED]);

        $this->info("{$count} Session منقضی علامت‌گذاری شد.");

        return self::SUCCESS;
    }
}
