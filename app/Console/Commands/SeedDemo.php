<?php

namespace App\Console\Commands;

use App\Domain\Campaign\Actions\PublishCampaignAction;
use App\Models\Campaign;
use App\Models\CampaignRule;
use App\Models\Game;
use App\Models\Plan;
use App\Models\Reward;
use App\Models\RewardInventory;
use App\Models\Store;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * دمای قابل‌تست — Sprint 4 (Lighthouse) و بتای ۵ فروشگاه (Sprint 8).
 *
 * یک فروشگاه نمونه با کمپین «چرخ شانس» منتشرشده و دو جایزه (کوپن ۱۰٪ و امتیاز ۵۰)
 * به‌صورت Idempotent می‌سازد؛ اجرای تکراری داده جدید نمی‌سازد.
 * خروجی: لینک عمومی کمپین (/c/demo-wheel) برای تست PWA و اندازه‌گیری Lighthouse.
 */
final class SeedDemo extends Command
{
    protected $signature = 'demo:seed';

    protected $description = 'ساخت دموی idempotent: فروشگاه نمونه + کمپین demo-wheel منتشرشده با جایزه کوپن و امتیاز';

    private const DEMO_MERCHANT_PHONE = '09120000000';

    private const DEMO_STORE_SLUG = 'demo';

    private const DEMO_CAMPAIGN_SLUG = 'demo-wheel';

    public function handle(): int
    {
        // روی دیتابیس تازه (سرور/CI) Planها و بازی‌ها باید وجود داشته باشند
        if (Plan::query()->count() === 0 || Game::query()->count() === 0) {
            $this->call(DatabaseSeeder::class);
        }

        $store = DB::transaction(function (): Store {
            $user = User::query()->firstOrCreate(
                ['phone' => self::DEMO_MERCHANT_PHONE],
                ['name' => 'فروشگاه نمونه (دمو)', 'role' => 'merchant'],
            );

            $store = Store::query()->firstOrCreate(
                ['slug' => self::DEMO_STORE_SLUG],
                ['user_id' => $user->id, 'name' => 'فروشگاه نمونه (دمو)', 'status' => 'active'],
            );

            // فروشگاه دمو باید اشتراک فعال داشته باشد (پیش‌فرض: Plan رایگان — فصل ۷-۱)
            if ($store->activeSubscription() === null) {
                Subscription::query()->create([
                    'store_id' => $store->id,
                    'plan_id' => Plan::free()->id,
                    'status' => Subscription::STATUS_ACTIVE,
                    'starts_at' => now(),
                    'ends_at' => now()->addYear(),
                ]);
            }

            return $store;
        });

        $campaign = Campaign::query()->firstOrCreate(
            ['slug' => self::DEMO_CAMPAIGN_SLUG],
            [
                'store_id' => $store->id,
                'game_id' => Game::query()->where('code', 'wheel')->value('id'),
                'title' => 'چرخ شانس دمو',
                'status' => Campaign::STATUS_DRAFT,
                'theme' => ['primary_color' => '#7C3AED'],
            ],
        );

        // پیکربندی بازی — بدون آن، endpoint عمومی config خالی برمی‌گرداند
        if ($campaign->configuration === null) {
            $campaign->configuration()->create([
                'schema_version' => '1',
                'config' => $this->wheelConfig(),
            ]);
        }

        if ($campaign->status === Campaign::STATUS_DRAFT) {
            $this->createRewards($campaign);
            $this->attachDefaultRule($campaign);
            app(PublishCampaignAction::class)->handle($campaign);
        }

        $this->info('✅ دمو آماده است.');
        $this->line('   کمپین عمومی : '.url('/c/'.self::DEMO_CAMPAIGN_SLUG));
        $this->line('   پنل         : '.url('/panel'));
        $this->line('   ورود پنل    : شماره '.self::DEMO_MERCHANT_PHONE.' (OTP در لاگ / سندباکس در پاسخ API)');

        return self::SUCCESS;
    }

    /** دو جایزه کمپین دمو — مطابق سناریوی E2E (کوپن ۱۰٪ + امتیاز ۵۰) */
    private function createRewards(Campaign $campaign): void
    {
        $rewards = [
            ['ref' => 'r-10', 'type' => Reward::TYPE_PERCENTAGE, 'name' => '۱۰٪ تخفیف', 'params' => ['percent' => 10], 'weight' => 40, 'total_qty' => 500],
            ['ref' => 'r-p50', 'type' => Reward::TYPE_POINTS, 'name' => '۵۰ امتیاز', 'params' => ['points' => 50], 'weight' => 30, 'total_qty' => 1000],
        ];

        foreach ($rewards as $reward) {
            $model = $campaign->rewards()->firstOrCreate(
                ['ref' => $reward['ref']],
                [
                    'store_id' => $campaign->store_id,
                    'type' => $reward['type'],
                    'name' => $reward['name'],
                    'params' => $reward['params'],
                    'weight' => $reward['weight'],
                    'total_qty' => $reward['total_qty'],
                    'is_active' => true,
                ],
            );

            RewardInventory::query()->firstOrCreate(
                ['reward_id' => $model->id],
                ['remaining_qty' => $reward['total_qty']],
            );
        }
    }

    /** قانون پیش‌فرض: هر مشتری روزی یک‌بار (فصل ۵-۳ سند معماری) */
    private function attachDefaultRule(Campaign $campaign): void
    {
        CampaignRule::query()->firstOrCreate(
            ['campaign_id' => $campaign->id, 'type' => CampaignRule::TYPE_ONCE_DAILY],
            ['value' => null],
        );
    }

    /**
     * پیکربندی Wheel دمو — reward_refها با createRewards همخوان‌اند.
     * کلیدهای حساس (weight/reward_ref) در endpoint عمومی حذف می‌شوند (فصل ۸-۱).
     */
    private function wheelConfig(): array
    {
        return [
            'segments' => [
                ['label' => '۱۰٪ تخفیف', 'weight' => 40, 'reward_ref' => 'r-10', 'color' => '#F4B860'],
                ['label' => '۵۰ امتیاز', 'weight' => 30, 'reward_ref' => 'r-p50', 'color' => '#8B5CF6'],
                ['label' => 'بدون جایزه', 'weight' => 30, 'reward_ref' => null, 'color' => '#E5E7EB'],
            ],
            'animation_duration_ms' => 4200,
            'sound_enabled' => true,
        ];
    }
}
