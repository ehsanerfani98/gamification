<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\Payment;
use App\Models\Store;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Settings\SiteSettingsService;
use Illuminate\Console\Command;

/**
 * پایش بتای ۵ فروشگاه — Sprint 8.
 *
 * وضعیت زنده بتا را یک‌جا نشان می‌دهد: حالت سندباکس‌ها، Adminها،
 * فروشگاه‌ها، اشتراک‌ها، کمپین‌ها، پرداخت‌ها و رخدادهای Audit اخیر —
 * همراه با گام بعدی پیشنهادی بر اساس وضعیت.
 */
final class BetaStatus extends Command
{
    protected $signature = 'beta:status';

    protected $description = 'گزارش وضعیت بتای ۵ فروشگاه (سندباکس‌ها، فروشگاه‌ها، کمپین‌ها، پرداخت‌ها)';

    public function handle(): int
    {
        $settings = app(SiteSettingsService::class)->get();

        $this->info('🚀 وضعیت بتای ۵ فروشگاه');
        $this->line(str_repeat('─', 46));

        // ── سندباکس‌ها ──────────────────────────────
        $this->line('سندباکس پیامک  : '.$this->yesNo($settings->sms_sandbox));
        $this->line('سندباکس پرداخت : '.$this->yesNo($settings->payment_sandbox));
        $this->line('درایورها (env) : پیامک='.config('gamification.sms.channel').' · پرداخت='.config('gamification.payments.gateway'));

        // ── Adminها ────────────────────────────────
        $admins = User::query()->where('role', 'admin')->count();
        $this->line('Adminهای پنل   : '.$admins.($admins === 0 ? '  ⚠️  ← php artisan admin:promote {phone}' : ''));

        // ── فروشگاه‌ها و اشتراک‌ها ───────────────────
        $stores = Store::query()->count();
        $paidStores = Subscription::query()
            ->where('status', Subscription::STATUS_ACTIVE)
            ->whereHas('plan', fn ($q) => $q->where('price_irt', '>', 0))
            ->distinct('store_id')
            ->count('store_id');
        $this->line('فروشگاه‌ها     : '.$stores.' (فعال روی Plan پولی: '.$paidStores.')');

        // ── کمپین‌ها ────────────────────────────────
        $published = Campaign::query()->where('status', Campaign::STATUS_PUBLISHED)->count();
        $drafts = Campaign::query()->where('status', Campaign::STATUS_DRAFT)->count();
        $this->line('کمپین‌ها       : '.$published.' منتشرشده، '.$drafts.' پیش‌نویس');

        // ── پرداخت‌ها ───────────────────────────────
        $paid = Payment::query()->where('status', Payment::STATUS_PAID)->count();
        $sandboxPaid = Payment::query()->where('status', Payment::STATUS_PAID)->where('meta->sandbox', true)->count();
        $this->line('پرداخت‌های موفق: '.$paid.' (سندباکس: '.$sandboxPaid.')');

        // ── Audit اخیر ─────────────────────────────
        $recent = AuditLog::query()->where('created_at', '>=', now()->subDays(7))->count();
        $this->line('رخداد Audit ۷ روز اخیر: '.$recent);

        $this->line(str_repeat('─', 46));

        // ── گام بعدی پیشنهادی ──────────────────────
        if ($admins === 0) {
            $this->warn('گام بعدی: ارتقای مدیر سایت → php artisan admin:promote {شماره‌ای که با آن وارد پنل شده‌اید}');
        } elseif (! $settings->sms_sandbox || ! $settings->payment_sandbox) {
            $this->warn('گام بعدی: پنل → تنظیمات سایت → روشن‌کردن دو سندباکس (پیامک و پرداخت).');
        } elseif ($stores < 5) {
            $this->warn('گام بعدی: Onboarding فروشگاه‌های پایلوت ('.$stores.' از ۵) — ببینید docs/BETA_ONBOARDING.md');
        } else {
            $this->info('همه ۵ فروشگاه پایلوت ثبت شده‌اند — بازخوردها را طبق docs/BETA_FEEDBACK_TEMPLATE.md جمع‌آوری کنید.');
        }

        return self::SUCCESS;
    }

    private function yesNo(bool $value): string
    {
        return $value ? 'روشن ✅' : 'خاموش';
    }
}
