<?php

namespace App\Domain\Game\Plugins\LuckyTicket;

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
 * پلاگین بلیط شانس (Lucky Ticket) — Sprint 4.
 *
 * قرعه فوری با حس بلیط: جایزه ابتدا با انتخاب وزنی سمت سرور تعیین می‌شود و
 * سپس «کد بلیط» با ارقام تصادفی (random_int) تولید و برای نمایش ارسال
 * می‌شود — کد بلیط شناسه نمایشی است و در تصمیم نقشی ندارد.
 */
final class LuckyTicketGame implements GameInterface
{
    public static function metadata(): GameMetadata
    {
        return new GameMetadata(
            code: 'lucky-ticket',
            name: 'بلیط شانس (Lucky Ticket)',
            category: 'lottery',
            description: 'بلیط با کد تصادفی سمت سرور؛ برنده شدن با انتخاب وزنی قرعه فوری.',
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
                'code_digits' => ['type' => 'integer', 'minimum' => 4, 'maximum' => 10],
                'prefix' => ['type' => 'string', 'maxLength' => 6],
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
            'code_digits' => ['nullable', 'integer', 'between:4,10'],
            'prefix' => ['nullable', 'string', 'max:6'],
        ], [
            'prizes.required' => 'حداقل یک جایزه قرعه لازم است.',
            'code_digits.between' => 'تعداد ارقام کد بلیط باید بین ۴ تا ۱۰ باشد.',
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

        $code = $this->generateCode(
            (string) ($config['prefix'] ?? ''),
            max(4, min(10, (int) ($config['code_digits'] ?? 6))),
        );

        $display = [
            'code' => $code,
            'prize_label' => $prize === null ? null : (string) ($prize['label'] ?? ''),
        ];

        if ($prize === null) {
            return GameResult::noReward(['code' => $code, 'outcome_key' => $key], $display);
        }

        return GameResult::win(
            empty($prize['reward_ref']) ? null : (string) $prize['reward_ref'],
            ['code' => $code, 'outcome_key' => $key],
            $display,
        );
    }

    public function remainingPlays(Campaign $campaign, Customer $customer): int
    {
        return app(CampaignRuleEngine::class)->remainingToday($campaign, $customer);
    }

    /** کد بلیط نمایشی — هر رقم با random_int (تصادف رمزنگارانه) */
    private function generateCode(string $prefix, int $digits): string
    {
        $code = $prefix;

        for ($i = 0; $i < $digits; $i++) {
            $code .= (string) random_int(0, 9);
        }

        return $code;
    }
}
