<?php

namespace App\Domain\Game\Plugins\Wheel;

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
 * پلاگین چرخ شانس (Lucky Wheel) — نمونه کامل اثبات معماری (فصل ۵).
 *
 * این پکیج هرگز مستقیم جایزه نمی‌سازد؛ فقط مرجع جایزه (reward_ref) را
 * در GameResult برمی‌گرداند و تصمیم نهایی با Reward Engine است (فصل ۶-۱).
 * انتخاب نتیجه با weighted RNG سمت سرور است و payload کلاینت نقشی ندارد.
 */
final class WheelGame implements GameInterface
{
    public static function metadata(): GameMetadata
    {
        return new GameMetadata(
            code: 'wheel',
            name: 'چرخ شانس (Lucky Wheel)',
            category: 'chance',
            description: 'چرخ شانس با Segmentهای وزنی؛ احتمال هر Segment مستقل و قابل تنظیم است.',
            version: '1.0.0',
        );
    }

    /** JSON Schema پیکربندی — پنل Wizard فرم را به‌صورت پویا می‌سازد (فصل ۵-۳) */
    public static function configSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'segments' => [
                    'type' => 'array',
                    'minItems' => 2,
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'label' => ['type' => 'string', 'maxLength' => 60],
                            'weight' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 1000],
                            'reward_ref' => ['type' => ['string', 'null']],
                            'color' => ['type' => 'string'],
                        ],
                        'required' => ['label', 'weight'],
                    ],
                ],
                'probability_mode' => ['type' => 'string', 'enum' => ['weighted']],
                'spins_per_user' => [
                    'type' => 'object',
                    'properties' => [
                        'daily' => ['type' => 'integer', 'minimum' => 1],
                        'total' => ['type' => ['integer', 'null']],
                    ],
                ],
                'animation_duration_ms' => ['type' => 'integer', 'minimum' => 1500, 'maximum' => 8000],
                'sound_enabled' => ['type' => 'boolean'],
            ],
            'required' => ['segments'],
        ];
    }

    public function validateConfig(array $config): Validator
    {
        $validator = ValidatorFactory::make($config, [
            'segments' => ['required', 'array', 'min:2', 'max:16'],
            'segments.*.label' => ['required', 'string', 'max:60'],
            'segments.*.weight' => ['required', 'integer', 'min:1', 'max:1000'],
            'segments.*.reward_ref' => ['nullable', 'string', 'max:60'],
            'segments.*.color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'probability_mode' => ['nullable', 'in:weighted'],
            'spins_per_user.daily' => ['nullable', 'integer', 'min:1', 'max:10'],
            'animation_duration_ms' => ['nullable', 'integer', 'between:1500,8000'],
            'sound_enabled' => ['nullable', 'boolean'],
        ], [
            'segments.required' => 'حداقل دو Segment برای چرخ لازم است.',
            'segments.min' => 'حداقل دو Segment برای چرخ لازم است.',
            'segments.*.weight.required' => 'وزن هر Segment الزامی است.',
            'segments.*.weight.min' => 'وزن هر Segment باید حداقل ۱ باشد.',
            'animation_duration_ms.between' => 'مدت انیمیشن باید بین ۱۵۰۰ تا ۸۰۰۰ میلی‌ثانیه باشد.',
        ]);

        // قواعد اختصاصی Wheel — فصل ۵-۳: مجموع وزن مثبت + حداقل یک Segment با جایزه
        $validator->after(function ($v) use ($config): void {
            $segments = $config['segments'] ?? [];

            if (is_array($segments) && collect($segments)->sum(fn ($s) => (int) ($s['weight'] ?? 0)) <= 0) {
                $v->errors()->add('segments', 'مجموع وزن‌ها باید مثبت باشد.');
            }

            $withReward = collect($segments)
                ->filter(fn ($s) => ! empty($s['reward_ref']))
                ->count();

            if ($withReward === 0) {
                $v->errors()->add('segments', 'حداقل یک Segment باید جایزه داشته باشد.');
            }
        });

        return $validator;
    }

    public function startSession(Campaign $campaign, Customer $customer): GameSession
    {
        return app(StartGameSessionAction::class)->handle($campaign, $customer);
    }

    /** انتخاب Segment فقط با weighted RNG سمت سرور — فصل ۲-۵ و ۶-۳ */
    public function resolveResult(GameSession $session, PlayerAction $action): GameResult
    {
        $segments = (array) ($session->campaign->configuration->config['segments'] ?? []);

        $index = (int) WeightedRandomizer::pick($segments);
        $segment = $segments[$index];

        $display = [
            'segment_index' => $index,
            'label' => $segment['label'] ?? '',
            'color' => $segment['color'] ?? null,
            'animation_duration_ms' => (int) ($session->campaign->configuration->config['animation_duration_ms'] ?? 4200),
        ];

        if (empty($segment['reward_ref'])) {
            return GameResult::noReward(
                ['segment_index' => $index],
                $display,
            );
        }

        return GameResult::win(
            (string) $segment['reward_ref'],
            ['segment_index' => $index],
            $display,
        );
    }

    public function remainingPlays(Campaign $campaign, Customer $customer): int
    {
        $ruleEngine = app(CampaignRuleEngine::class);

        return $ruleEngine->remainingToday($campaign, $customer);
    }
}
