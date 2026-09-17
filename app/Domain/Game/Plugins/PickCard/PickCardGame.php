<?php

namespace App\Domain\Game\Plugins\PickCard;

use App\Domain\Game\DTO\GameMetadata;
use App\Domain\Game\Plugins\Support\PickOutcomeGame;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Facades\Validator as ValidatorFactory;

/**
 * پلاگین انتخاب کارت (Pick a Card) — Sprint 4.
 *
 * همان مکانیک امن Pick a Box با ظاهر کارت: ۳ تا ۱۲ کارت رو‌به‌پایین،
 * نتیجه سمت سرور و برجسته‌سازی روی کارت انتخابی؛ طرح پشت کارت (back_design)
 * فقط نمایشی است.
 */
final class PickCardGame extends PickOutcomeGame
{
    public static function metadata(): GameMetadata
    {
        return new GameMetadata(
            code: 'pick-card',
            name: 'انتخاب کارت (Pick a Card)',
            category: 'pick',
            description: 'کارت‌های روبه‌پایین؛ نتیجه سمت سرور و برجسته‌سازی روی کارت انتخابی.',
            version: '1.0.0',
        );
    }

    public static function configSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'cards' => ['type' => 'integer', 'minimum' => 3, 'maximum' => 12],
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
                'back_design' => ['type' => 'string', 'maxLength' => 30],
            ],
            'required' => ['cards', 'prizes'],
        ];
    }

    public function validateConfig(array $config): Validator
    {
        $validator = ValidatorFactory::make($config, [
            'cards' => ['required', 'integer', 'between:3,12'],
            'prizes' => ['required', 'array', 'min:1', 'max:10'],
            'prizes.*.label' => ['required', 'string', 'max:40'],
            'prizes.*.weight' => ['required', 'integer', 'min:1', 'max:1000'],
            'prizes.*.reward_ref' => ['nullable', 'string', 'max:60'],
            'empty_weight' => ['nullable', 'integer', 'min:0'],
            'back_design' => ['nullable', 'string', 'max:30'],
        ], [
            'cards.required' => 'تعداد کارت‌ها الزامی است (۳ تا ۱۲).',
            'cards.between' => 'تعداد کارت‌ها باید بین ۳ تا ۱۲ باشد.',
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

    protected function optionCount(array $config): int
    {
        return max(3, min(12, (int) ($config['cards'] ?? 3)));
    }

    protected function itemKey(): string
    {
        return 'cards';
    }
}
