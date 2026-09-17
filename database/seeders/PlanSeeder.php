<?php

namespace Database\Seeders;

use App\Models\Game;
use App\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * سه Plan پیش‌فرض MVP — جدول فصل ۷-۱ سند معماری.
 * سقف‌ها داده‌ای هستند (features JSON) و Admin می‌تواند بدون انتشار نسخه تغییرشان دهد.
 * سقف null در JSON یعنی «بدون سقف».
 */
final class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'slug' => 'free',
                'name' => 'رایگان',
                'price' => 0,
                'sort' => 1,
                'features' => [
                    'max_active_campaigns' => 1,
                    'max_participants_per_month' => 200,
                    'max_rewards' => 5,
                    'customization' => 'basic',
                    'analytics' => 'summary',
                    'referral' => false,
                    'daily_games' => false,
                    'remove_branding' => false,
                ],
                'games' => ['wheel', 'dice'],
            ],
            [
                'slug' => 'basic',
                'name' => 'پایه',
                'price' => 199000,
                'sort' => 2,
                'features' => [
                    'max_active_campaigns' => 3,
                    'max_participants_per_month' => 2000,
                    'max_rewards' => 25,
                    'customization' => 'full',
                    'analytics' => 'funnel',
                    'referral' => false,
                    'daily_games' => false,
                    'remove_branding' => false,
                ],
                'games' => ['wheel', 'dice', 'scratch', 'pick-box', 'pick-card', 'lucky-ticket', 'quiz'],
            ],
            [
                'slug' => 'pro',
                'name' => 'حرفه‌ای',
                'price' => 499000,
                'sort' => 3,
                'features' => [
                    'max_active_campaigns' => 10,
                    'max_participants_per_month' => null,
                    'max_rewards' => null,
                    'customization' => 'full',
                    'analytics' => 'funnel_csv',
                    'referral' => true,
                    'daily_games' => true,
                    'remove_branding' => true,
                ],
                'games' => ['wheel', 'dice', 'scratch', 'pick-box', 'pick-card', 'lucky-ticket', 'quiz', 'memory', 'reaction', 'claw'],
            ],
        ];

        foreach ($plans as $plan) {
            $model = Plan::query()->updateOrCreate(
                ['slug' => $plan['slug']],
                [
                    'name' => $plan['name'],
                    'price_irt' => $plan['price'],
                    'billing_period' => $plan['price'] > 0 ? 'monthly' : 'none',
                    'features' => $plan['features'],
                    'is_active' => true,
                    'sort' => $plan['sort'],
                ],
            );

            $gameIds = Game::query()->whereIn('code', $plan['games'])->pluck('id');

            $model->games()->sync($gameIds);
        }
    }
}
