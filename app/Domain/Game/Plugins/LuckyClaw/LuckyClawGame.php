<?php

namespace App\Domain\Game\Plugins\LuckyClaw;

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
 * پلاگین دستگیره شانس (Lucky Claw) — Sprint 4.
 *
 * مکانیک Arcade ماشین دستگیره: سرنوشت گرفتن جعبه با weighted RNG سمت سرور
 * تعیین می‌شود؛ مختصات هدف دستگیره (target_x/y)، سختی و مدت حرکت برای
 * انیمیشن تولید می‌شوند (grabbed = نتیجه واقعی، نه ورودی کلاینت).
 */
final class LuckyClawGame implements GameInterface
{
    public static function metadata(): GameMetadata
    {
        return new GameMetadata(
            code: 'claw',
            name: 'دستگیره شانس (Lucky Claw)',
            category: 'arcade',
            description: 'دستگیره را رها کنید؛ grabbed سمت سرور تعیین می‌شود.',
            version: '1.0.0',
        );
    }

    public static function configSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
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
                'difficulty' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 10],
                'duration_ms' => ['type' => 'integer', 'minimum' => 3000, 'maximum' => 10000],
            ],
            'required' => ['prizes'],
        ];
    }

    public function validateConfig(array $config): Validator
    {
        $validator = ValidatorFactory::make($config, [
            'prizes' => ['required', 'array', 'min:1', 'max:10'],
            'prizes.*.label' => ['required', 'string', 'max:40'],
            'prizes.*.weight' => ['required', 'integer', 'min:1', 'max:1000'],
            'prizes.*.reward_ref' => ['nullable', 'string', 'max:60'],
            'empty_weight' => ['nullable', 'integer', 'min:0'],
            'difficulty' => ['nullable', 'integer', 'between:1,10'],
            'duration_ms' => ['nullable', 'integer', 'between:3000,10000'],
        ], [
            'prizes.required' => 'حداقل یک جایزه لازم است.',
            'difficulty.between' => 'سختی باید بین ۱ تا ۱۰ باشد.',
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

        $display = [
            'grabbed' => $prize !== null,
            // مختصات هدف فقط برای انیمیشن حرکت دستگیره
            'target_x' => random_int(15, 85),
            'target_y' => random_int(15, 85),
            'duration_ms' => max(3000, min(10000, (int) ($config['duration_ms'] ?? 5000))),
            'difficulty' => max(1, min(10, (int) ($config['difficulty'] ?? 5))),
        ];

        if ($prize === null) {
            return GameResult::noReward(['outcome_key' => $key], $display);
        }

        $display['prize_label'] = (string) ($prize['label'] ?? '');

        return GameResult::win(
            empty($prize['reward_ref']) ? null : (string) $prize['reward_ref'],
            ['outcome_key' => $key],
            $display,
        );
    }

    public function remainingPlays(Campaign $campaign, Customer $customer): int
    {
        return app(CampaignRuleEngine::class)->remainingToday($campaign, $customer);
    }
}
