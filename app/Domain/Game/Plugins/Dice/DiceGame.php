<?php

namespace App\Domain\Game\Plugins\Dice;

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
 * پلاگین تاس شانس (Lucky Dice) — Sprint 4.
 *
 * تاس شش‌وجهی با وزن مستقل برای هر وجه؛ هر وجه می‌تواند reward_ref داشته
 * باشد یا صرفاً نمایشی باشد. انتخاب وجه فقط با weighted RNG سمت سرور است
 * و تاس دوم (در صورت فعال‌بودن) صرفاً جنبه نمایش دارد.
 */
final class DiceGame implements GameInterface
{
    public static function metadata(): GameMetadata
    {
        return new GameMetadata(
            code: 'dice',
            name: 'تاس شانس (Lucky Dice)',
            category: 'chance',
            description: 'تاس شش‌وجهی با وزن مستقل برای هر وجه؛ نتیجه فقط سمت سرور.',
            version: '1.0.0',
        );
    }

    public static function configSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'faces' => [
                    'type' => 'array',
                    'minItems' => 6,
                    'maxItems' => 6,
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'label' => ['type' => 'string', 'maxLength' => 20],
                            'weight' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 1000],
                            'reward_ref' => ['type' => ['string', 'null']],
                            'color' => ['type' => 'string'],
                        ],
                        'required' => ['label', 'weight'],
                    ],
                ],
                'dice_count' => ['type' => 'integer', 'enum' => [1, 2]],
                'animation_duration_ms' => ['type' => 'integer', 'minimum' => 1000, 'maximum' => 6000],
                'sound_enabled' => ['type' => 'boolean'],
            ],
            'required' => ['faces'],
        ];
    }

    public function validateConfig(array $config): Validator
    {
        $validator = ValidatorFactory::make($config, [
            'faces' => ['required', 'array', 'size:6'],
            'faces.*.label' => ['required', 'string', 'max:20'],
            'faces.*.weight' => ['required', 'integer', 'min:1', 'max:1000'],
            'faces.*.reward_ref' => ['nullable', 'string', 'max:60'],
            'faces.*.color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'dice_count' => ['nullable', 'integer', 'in:1,2'],
            'animation_duration_ms' => ['nullable', 'integer', 'between:1000,6000'],
            'sound_enabled' => ['nullable', 'boolean'],
        ], [
            'faces.required' => 'پیکربندی تاس باید شش وجه داشته باشد.',
            'faces.size' => 'تاس باید دقیقاً شش وجه داشته باشد.',
            'faces.*.weight.required' => 'وزن هر وجه الزامی است.',
            'faces.*.weight.min' => 'وزن هر وجه باید حداقل ۱ باشد.',
        ]);

        // قواعد اختصاصی Dice — مجموع وزن مثبت + حداقل یک وجه جایزه‌دار
        $validator->after(function ($v) use ($config): void {
            $faces = $config['faces'] ?? [];

            if (is_array($faces) && collect($faces)->sum(fn ($f) => (int) ($f['weight'] ?? 0)) <= 0) {
                $v->errors()->add('faces', 'مجموع وزن‌ها باید مثبت باشد.');
            }

            $withReward = collect($faces)
                ->filter(fn ($f) => ! empty($f['reward_ref']))
                ->count();

            if ($withReward === 0) {
                $v->errors()->add('faces', 'حداقل یک وجه باید جایزه داشته باشد.');
            }
        });

        return $validator;
    }

    public function startSession(Campaign $campaign, Customer $customer): GameSession
    {
        return app(StartGameSessionAction::class)->handle($campaign, $customer);
    }

    /** انتخاب وجه فقط با weighted RNG سمت سرور — فصل ۲-۵ و ۶-۳ */
    public function resolveResult(GameSession $session, PlayerAction $action): GameResult
    {
        $config = (array) ($session->campaign->configuration->config ?? []);
        $faces = array_values(array_filter((array) ($config['faces'] ?? []), 'is_array'));

        $index = (int) WeightedRandomizer::pick($faces);
        $face = $faces[$index];

        $display = [
            'face_index' => $index,
            'face_label' => (string) ($face['label'] ?? ''),
            'color' => $face['color'] ?? null,
            'dice_count' => (int) ($config['dice_count'] ?? 1),
            'animation_duration_ms' => (int) ($config['animation_duration_ms'] ?? 3000),
        ];

        if (empty($face['reward_ref'])) {
            return GameResult::noReward(['face_index' => $index], $display);
        }

        return GameResult::win((string) $face['reward_ref'], ['face_index' => $index], $display);
    }

    public function remainingPlays(Campaign $campaign, Customer $customer): int
    {
        return app(CampaignRuleEngine::class)->remainingToday($campaign, $customer);
    }
}
