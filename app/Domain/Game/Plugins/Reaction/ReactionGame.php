<?php

namespace App\Domain\Game\Plugins\Reaction;

use App\Domain\Campaign\Services\CampaignRuleEngine;
use App\Domain\Game\Actions\StartGameSessionAction;
use App\Domain\Game\Contracts\GameInterface;
use App\Domain\Game\DTO\GameMetadata;
use App\Domain\Game\DTO\GameResult;
use App\Domain\Game\DTO\PlayerAction;
use App\Domain\Game\Plugins\Support\WeightedOutcome;
use App\Models\Campaign;
use App\Models\Customer;
use App\Models\GameSession;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Facades\Validator as ValidatorFactory;

/**
 * پلاگین بازی سرعت (Reaction) — Sprint 4.
 *
 * آستانه زمان (threshold_ms) و تعداد راند فقط برای انیمیشن و چالش روانی
 * است؛ زمان‌های گزارشی کلاینت قابل‌اعتماد نیستند و در تصمیم نقش ندارند.
 * نتیجه با weighted RNG سمت سرور تعیین و زمان‌های ارسالی صرفاً در raw
 * برای Analytics ثبت می‌شوند (فصل ۲-۵).
 */
final class ReactionGame implements GameInterface
{
    public static function metadata(): GameMetadata
    {
        return new GameMetadata(
            code: 'reaction',
            name: 'بازی سرعت (Reaction)',
            category: 'skill',
            description: 'سریع‌تر بزنید! نتیجه سمت سرور؛ زمان‌های کلاینت فقط ثبت می‌شوند.',
            version: '1.0.0',
        );
    }

    public static function configSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'rounds' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 5],
                'threshold_ms' => ['type' => 'integer', 'minimum' => 200, 'maximum' => 2000],
                'prizes' => [
                    'type' => 'array',
                    'minItems' => 1,
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'label' => ['type' => 'string', 'maxLength' => 40],
                            'weight' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 1000],
                            'reward_ref' => ['type' => ['string', 'null']],
                        ],
                        'required' => ['label', 'weight'],
                    ],
                ],
                'empty_weight' => ['type' => 'integer', 'minimum' => 0],
            ],
            'required' => ['rounds', 'threshold_ms', 'prizes'],
        ];
    }

    public function validateConfig(array $config): Validator
    {
        $validator = ValidatorFactory::make($config, [
            'rounds' => ['required', 'integer', 'between:1,5'],
            'threshold_ms' => ['required', 'integer', 'between:200,2000'],
            'prizes' => ['required', 'array', 'min:1', 'max:10'],
            'prizes.*.label' => ['required', 'string', 'max:40'],
            'prizes.*.weight' => ['required', 'integer', 'min:1', 'max:1000'],
            'prizes.*.reward_ref' => ['nullable', 'string', 'max:60'],
            'empty_weight' => ['nullable', 'integer', 'min:0'],
        ], [
            'rounds.required' => 'تعداد راندها الزامی است (۱ تا ۵).',
            'threshold_ms.required' => 'آستانه زمان الزامی است (۲۰۰ تا ۲۰۰۰ میلی‌ثانیه).',
            'prizes.required' => 'حداقل یک جایزه لازم است.',
        ]);

        $validator->after(function ($v) use ($config): void {
            $prizes = $config['prizes'] ?? [];
            $total = collect($prizes)->sum(fn ($p) => (int) ($p['weight'] ?? 0))
                + (int) ($config['empty_weight'] ?? 0);

            if ($total <= 0) {
                $v->errors()->add('prizes', 'مجموع وزن‌ها باید مثبت باشد.');
            }

            $withReward = collect($prizes)
                ->filter(fn ($p) => ! empty($p['reward_ref']))
                ->count();

            if ($withReward === 0) {
                $v->errors()->add('prizes', 'حداقل یک جایزه باید reward_ref داشته باشد.');
            }
        });

        return $validator;
    }

    public function startSession(Campaign $campaign, Customer $customer): GameSession
    {
        return app(StartGameSessionAction::class)->handle($campaign, $customer);
    }

    public function resolveResult(GameSession $session, PlayerAction $action): GameResult
    {
        $config = (array) ($session->campaign->configuration->config ?? []);
        $prizes = array_values(array_filter((array) ($config['prizes'] ?? []), 'is_array'));

        [$key, $prize] = WeightedOutcome::pick($prizes, (int) ($config['empty_weight'] ?? 0));

        $rounds = max(1, min(5, (int) ($config['rounds'] ?? 1)));
        $threshold = max(200, min(2000, (int) ($config['threshold_ms'] ?? 500)));

        // زمان‌های گزارشی کلاینت فقط برای Analytics — اعتبار تصمیمی ندارند
        $reportedTimes = array_values(array_filter(
            is_array($action->payload['times_ms'] ?? null) ? $action->payload['times_ms'] : [],
            'is_int',
        ));

        $display = [
            'rounds' => $rounds,
            'threshold_ms' => $threshold,
            'won' => $prize !== null,
        ];

        if ($prize === null) {
            return GameResult::noReward(
                ['outcome_key' => $key, 'client_times_ms' => $reportedTimes],
                $display,
            );
        }

        return GameResult::win(
            empty($prize['reward_ref']) ? null : (string) $prize['reward_ref'],
            ['outcome_key' => $key, 'client_times_ms' => $reportedTimes],
            $display,
        );
    }

    public function remainingPlays(Campaign $campaign, Customer $customer): int
    {
        return app(CampaignRuleEngine::class)->remainingToday($campaign, $customer);
    }
}
