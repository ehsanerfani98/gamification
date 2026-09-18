<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * ارتقای کاربر به Admin — Sprint 8 (بتای ۵ فروشگاه).
 *
 * کاربری که با OTP وارد پنل شده (users رکورد دارد) با این دستور نقش admin می‌گیرد؛
 * سپس باید یک‌بار دیگر وارد شود تا توکن با ability «admin» صادر شود.
 */
final class PromoteAdmin extends Command
{
    protected $signature = 'admin:promote {phone : شماره موبایل کاربری که قبلاً یک‌بار با OTP وارد پنل شده}';

    protected $description = 'ارتقای کاربر پنل به نقش Admin (دسترسی بخش تنظیمات سایت)';

    public function handle(): int
    {
        $phone = (string) $this->argument('phone');

        $user = User::query()->where('phone', $phone)->first();

        if (! $user) {
            $this->error("کاربری با شماره {$phone} پیدا نشد — ابتدا یک‌بار با OTP وارد پنل شود.");

            return self::FAILURE;
        }

        if ($user->role === 'admin') {
            $this->info("کاربر {$phone} از قبل Admin است.");

            return self::SUCCESS;
        }

        $user->forceFill(['role' => 'admin'])->save();

        AuditLog::record('user.promoted_admin', $user, $user);

        $this->info("✅ کاربر {$phone} Admin شد.");
        $this->warn('برای فعال شدن دسترسی، یک‌بار خارج و دوباره وارد پنل شوید (صدور توکن جدید).');

        return self::SUCCESS;
    }
}
