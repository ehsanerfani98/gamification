<?php

namespace App\Domain\Game\Plugins\Memory;

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
 * پلاگین بازی حافظه (Memory) — Sprint 4.
 *
 * چیدمان کارت‌ها (board) سمت سرور با random_int به‌هم‌ریخته می‌شود و برای
 * انیمیشن ارسال می‌گردد. در MVP نتیجه برد/باخت با weighted RNG سمت سرور
 * تعیین می‌شود (Server-Authoritative)؛ سنجه‌های کلاینت (تعداد حرکت/زمان)
 * صرفاً در raw برای Analytics ثبت می‌شوند و در تصمیم نقشی ندارند.
 */
final class MemoryGame implements GameInterface
{
    public static function metadata(): GameMetadata
    {
        return new GameMetadata(
            code: 'memory',
            name: 'بازی حافظه (Memory)',
            category: 'skill',
            description: 'جفت‌های پنهان را پیدا کنید؛ چیدمان و نتیجه هر دو سمت سرور.',
            version: '1.0.0',
        );
    }

    public static function configSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'pairs' => ['type' => 'integer', 'minimum' => 4, 'maximum' => 12],
                'moves_limit' => ['type' => ['integer', 'null'], 'minimum' => 4, 'maximum' => 100],
                'time_limit_seconds' => ['type' => ['integer', 'null'], 'minimum' => 10, 'maximum' => 300],
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
            'required' => ['pairs', 'prizes'],
        ];
    }

    public function validateConfig(array $config): Validator
    {
        $validator = ValidatorFactory::make($config, [
            'pairs' => ['required', 'integer', 'between:4,12'],
            'moves_limit' => ['nullable', 'integer', 'between:4,100'],
            'time_limit_seconds' => ['nullable', 'integer', 'between:10,300'],
            'prizes' => ['required', 'array', 'min:1', 'max:10'],
            'prizes.*.label' => ['required', 'string', 'max:40'],
            'prizes.*.weight' => ['required', 'integer', 'min:1', 'max:1000'],
            'prizes.*.reward_ref' => ['nullable', 'string', 'max:60'],
            'empty_weight' => ['nullable', 'integer', 'min:0'],
        ], [
            'pairs.required' => 'تعداد جفت‌ها الزامی است (۴ تا ۱۲).',
            'pairs.between' => 'تعداد جفت‌ها باید بین ۴ تا ۱۲ باشد.',
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

        $pairs = max(4, min(12, (int) ($config['pairs'] ?? 6)));

        $display = [
            'board' => $this->shuffledBoard($pairs),
            'pairs' => $pairs,
            'moves_limit' => $config['moves_limit'] ?? null,
            'time_limit_seconds' => $config['time_limit_seconds'] ?? null,
        ];

        if ($prize === null) {
            return GameResult::noReward(['outcome_key' => $key], $display);
        }

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

    /** هر نماد دقیقاً دو بار؛ به‌هم‌ریختگی با Fisher–Yates و random_int */
    private function shuffledBoard(int $pairs): array
    {
        $deck = [];

        for ($symbol = 0; $symbol < $pairs; $symbol++) {
            $deck[] = $symbol;
            $deck[] = $symbol;
        }

        for ($i = count($deck) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$deck[$i], $deck[$j]] = [$deck[$j], $deck[$i]];
        }

        return $deck;
    }
}
