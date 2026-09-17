<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * بکاپ سازگار از پایگاه‌داده SQLite — فصل ۱۰ (آماده‌سازی استقرار، Sprint 6).
 *
 * با VACUUM INTO یک Snapshot یکپارچه (شامل WAL) می‌سازد که بدون توقف
 * سرویس قابل کپی است. خروجی‌ها در storage/app/private/backups نگه‌داری و
 * بر اساس سقف تعداد (پیش‌فرض ۱۴) پاک‌سازی می‌شوند. زمان‌بندی: روزانه ۰۴:۰۰.
 */
final class BackupDatabase extends Command
{
    protected $signature = 'database:backup {--keep=14 : حداکثر تعداد بکاپ نگه‌داری‌شده}';

    protected $description = 'ساخت بکاپ یکپارچه از پایگاه‌داده SQLite با VACUUM INTO و پاک‌سازی بکاپ‌های قدیمی';

    public function handle(): int
    {
        if (config('database.default') !== 'sqlite') {
            $this->warn('بکاپ فقط برای درایور sqlite پیاده‌سازی شده است؛ برای PostgreSQL از pg_dump استفاده کنید.');

            return self::INVALID;
        }

        $directory = storage_path('app/private/backups');

        if (! is_dir($directory) && ! mkdir($directory, 0775, true)) {
            $this->error('ساخت پوشه بکاپ ناموفق بود.');

            return self::FAILURE;
        }

        $path = $directory.'/backup-'.now()->format('Ymd-His').'.sqlite';

        // VACUUM INTO: اسنپ‌شات یکپارچه حتی در حین تراکنش‌های فعال (WAL)
        DB::statement('VACUUM INTO ?', [$path]);

        $this->info("بکاپ ساخته شد: {$path}");

        $this->pruneOldBackups($directory, max(1, (int) $this->option('keep')));

        return self::SUCCESS;
    }

    private function pruneOldBackups(string $directory, int $keep): void
    {
        $backups = glob($directory.'/backup-*.sqlite');

        if ($backups === false || count($backups) <= $keep) {
            return;
        }

        sort($backups);

        foreach (array_slice($backups, 0, count($backups) - $keep) as $old) {
            @unlink($old);

            $this->line('بکاپ قدیمی حذف شد: '.basename($old));
        }
    }
}
