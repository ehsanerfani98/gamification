<?php

namespace App\Domain\Game\Plugins\Scratch;

use App\Domain\Campaign\Services\CampaignRuleEngine;
use App\Domain\Game\Actions\StartGameSessionAction;
use App\Domain\Game\Contracts\GameInterface;
use App\Domain\Game\DTO\GameMetadata;
use App\Domain\Game\DTO\GameResult;
use App\Domain\Game\DTO\PlayerAction;
use App\Domain\Game\Services\WeightedRandomizer;
use App\Models\Campaign;
use App\Models\Customer;
use App\Models\GameSession;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Facades\Validator as ValidatorFactory;

/**
 * پلاگین کارت خراشیدنی (Scratch Card) — Sprint 4.
 *
 * کارت N خانه‌ای به‌طور کامل سمت سرور تولید می‌شود (نماد هر خانه با weighted
 * RNG) و شرط برد — رسیدن تعداد یک نماد به حد نصاب (match_required) — روی
 * همان کارت بررسی می‌شود. کلاینت فقط کارت آماده را «می‌خراشد» و هیچ داده‌ای
 * به سرور نمی‌فرستد؛ پس سطح حمله Replay/Manipulation در این بازی صفر است.
 */
final class ScratchGame implements GameInterface
{
    public static function metadata(): GameMetadata
    {
        return new GameMetadata(
            code: 'scratch',
            name: 'کارت خراشیدنی (Scratch Card)',
            category: 'scratch',
            description: 'کارت خانه‌دار با نمادهای وزنی؛ برد با رسیدن یک نماد به حد نصاب.',
            version: '1.0.0',
        );
    }

    public static function configSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'symbols' => [
                    'type' => 'array',
                    'minItems' => 2,
                    'maxItems' => 8,
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'label' => ['type' => 'string', 'maxLength' => 30],
                            'weight' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 1000],
                            'reward_ref' => ['type' => ['string', 'null']],
                            'color' => ['type' => 'string'],
                        ],
                        'required' => ['label', 'weight'],
                    ],
                ],
                'grid' => [
                    'type' => 'object',
                    'properties' => [
                        'rows' => ['type' => 'integer', 'minimum' => 2, 'maximum' => 4],
                        'cols' => ['type' => 'integer', 'minimum' => 2, 'maximum' => 4],
                    ],
                ],
                'match_required' => ['type' => 'integer', 'minimum' => 2],
                'animation_duration_ms' => ['type' => 'integer', 'minimum' => 800, 'maximum' => 6000],
            ],
            'required' => ['symbols'],
        ];
    }

    public function validateConfig(array $config): Validator
    {
        $validator = ValidatorFactory::make($config, [
            'symbols' => ['required', 'array', 'min:2', 'max:8'],
            'symbols.*.label' => ['required', 'string', 'max:30'],
            'symbols.*.weight' => ['required', 'integer', 'min:1', 'max:1000'],
            'symbols.*.reward_ref' => ['nullable', 'string', 'max:60'],
            'symbols.*.color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'grid.rows' => ['nullable', 'integer', 'between:2,4'],
            'grid.cols' => ['nullable', 'integer', 'between:2,4'],
            'match_required' => ['nullable', 'integer', 'min:2', 'max:16'],
            'animation_duration_ms' => ['nullable', 'integer', 'between:800,6000'],
        ], [
            'symbols.required' => 'حداقل دو نماد برای کارت لازم است.',
            'symbols.min' => 'حداقل دو نماد برای کارت لازم است.',
            'symbols.*.weight.required' => 'وزن هر نماد الزامی است.',
        ]);

        $validator->after(function ($v) use ($config): void {
            $symbols = $config['symbols'] ?? [];
            $rows = (int) ($config['grid']['rows'] ?? 3);
            $cols = (int) ($config['grid']['cols'] ?? 3);
            $cells = $rows * $cols;
            $match = (int) ($config['match_required'] ?? 3);

            if ($match > $cells) {
                $v->errors()->add('match_required', "حد نصاب برد نمی‌تواند بیش از تعداد خانه‌ها ({$cells}) باشد.");
            }

            $withReward = collect($symbols)
                ->filter(fn ($s) => ! empty($s['reward_ref']))
                ->count();

            if ($withReward === 0) {
                $v->errors()->add('symbols', 'حداقل یک نماد باید جایزه داشته باشد.');
            }
        });

        return $validator;
    }

    public function startSession(Campaign $campaign, Customer $customer): GameSession
    {
        return app(StartGameSessionAction::class)->handle($campaign, $customer);
    }

    /** کارت کامل سمت سرور ساخته می‌شود — هر خانه با weighted RNG (فصل ۶-۳) */
    public function resolveResult(GameSession $session, PlayerAction $action): GameResult
    {
        $config = (array) ($session->campaign->configuration->config ?? []);
        $symbols = array_values(array_filter((array) ($config['symbols'] ?? []), 'is_array'));
        $rows = max(2, min(4, (int) ($config['grid']['rows'] ?? 3)));
        $cols = max(2, min(4, (int) ($config['grid']['cols'] ?? 3)));
        $matchRequired = max(2, (int) ($config['match_required'] ?? 3));

        $cells = [];

        for ($i = 0; $i < $rows * $cols; $i++) {
            $cells[] = (int) WeightedRandomizer::pick($symbols);
        }

        $counts = array_count_values($cells);

        $winningIndex = null;

        foreach ($counts as $symbolIndex => $count) {
            if ($count >= $matchRequired && ! empty($symbols[$symbolIndex]['reward_ref'])) {
                $winningIndex = (int) $symbolIndex;

                break;
            }
        }

        $display = [
            'cells' => $cells,
            'symbols' => collect($symbols)->map(fn (array $symbol) => [
                'label' => (string) ($symbol['label'] ?? ''),
                'color' => $symbol['color'] ?? null,
            ])->all(),
            'rows' => $rows,
            'cols' => $cols,
            'match_required' => $matchRequired,
            'animation_duration_ms' => (int) ($config['animation_duration_ms'] ?? 2000),
        ];

        if ($winningIndex === null) {
            return GameResult::noReward(['cells' => $cells, 'counts' => $counts], $display);
        }

        $display['winning_symbol_index'] = $winningIndex;

        return GameResult::win(
            (string) $symbols[$winningIndex]['reward_ref'],
            ['cells' => $cells, 'winning_symbol_index' => $winningIndex],
            $display,
        );
    }

    public function remainingPlays(Campaign $campaign, Customer $customer): int
    {
        return app(CampaignRuleEngine::class)->remainingToday($campaign, $customer);
    }
}
