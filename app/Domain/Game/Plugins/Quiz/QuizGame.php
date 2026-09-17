<?php

namespace App\Domain\Game\Plugins\Quiz;

use App\Domain\Campaign\Services\CampaignRuleEngine;
use App\Domain\Game\Actions\StartGameSessionAction;
use App\Domain\Game\Contracts\GameInterface;
use App\Domain\Game\DTO\GameMetadata;
use App\Domain\Game\DTO\GameResult;
use App\Domain\Game\DTO\PlayerAction;
use App\Models\Campaign;
use App\Models\Customer;
use App\Models\GameSession;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Facades\Validator as ValidatorFactory;

/**
 * پلاگین کوئیز (Quiz) — Sprint 4.
 *
 * تنها بازی دانش‌محور MVP: پاسخ‌های کاربر سمت سرور در برابر کلید پیکربندی
 * تصحیح می‌شود (Server-Side Grading). کلید پاسخ‌ها (correct_index) هرگز در
 * پیکربندی عمومی افشا نمی‌شود (فصل ۸-۱) و در نتیجه Session فقط پس از بازی
 * و صرفاً برای مرور (show_answers) برمی‌گردد.
 */
final class QuizGame implements GameInterface
{
    public static function metadata(): GameMetadata
    {
        return new GameMetadata(
            code: 'quiz',
            name: 'کوئیز (Quiz)',
            category: 'quiz',
            description: 'سؤالات چندگزینه‌ای با تصحیح سمت سرور؛ کلید پاسخ‌ها هرگز افشا نمی‌شود.',
            version: '1.0.0',
        );
    }

    public static function configSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'questions' => [
                    'type' => 'array',
                    'minItems' => 1,
                    'maxItems' => 20,
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'text' => ['type' => 'string', 'maxLength' => 200],
                            'options' => [
                                'type' => 'array',
                                'minItems' => 2,
                                'maxItems' => 6,
                                'items' => ['type' => 'string', 'maxLength' => 60],
                            ],
                            'correct_index' => ['type' => 'integer', 'minimum' => 0],
                            'time_limit_seconds' => ['type' => ['integer', 'null'], 'minimum' => 5, 'maximum' => 120],
                        ],
                        'required' => ['text', 'options', 'correct_index'],
                    ],
                ],
                'pass_score' => ['type' => 'integer', 'minimum' => 1],
                'reward_ref' => ['type' => ['string', 'null']],
                'show_answers' => ['type' => 'boolean'],
            ],
            'required' => ['questions', 'pass_score'],
        ];
    }

    public function validateConfig(array $config): Validator
    {
        $validator = ValidatorFactory::make($config, [
            'questions' => ['required', 'array', 'min:1', 'max:20'],
            'questions.*.text' => ['required', 'string', 'max:200'],
            'questions.*.options' => ['required', 'array', 'min:2', 'max:6'],
            'questions.*.options.*' => ['required', 'string', 'max:60'],
            'questions.*.correct_index' => ['required', 'integer', 'min:0'],
            'questions.*.time_limit_seconds' => ['nullable', 'integer', 'between:5,120'],
            'pass_score' => ['required', 'integer', 'min:1', 'max:20'],
            'reward_ref' => ['nullable', 'string', 'max:60'],
            'show_answers' => ['nullable', 'boolean'],
        ], [
            'questions.required' => 'حداقل یک سؤال لازم است.',
            'questions.min' => 'حداقل یک سؤال لازم است.',
            'pass_score.required' => 'حد نصاب قبولی الزامی است.',
        ]);

        $validator->after(function ($v) use ($config): void {
            $questions = $config['questions'] ?? [];

            foreach ($questions as $i => $question) {
                $optionsCount = is_array($question['options'] ?? null) ? count($question['options']) : 0;
                $correct = (int) ($question['correct_index'] ?? -1);

                if ($optionsCount > 0 && $correct >= $optionsCount) {
                    $v->errors()->add("questions.{$i}.correct_index", 'ایندکس پاسخ درست باید در بازه گزینه‌ها باشد.');
                }
            }

            $passScore = (int) ($config['pass_score'] ?? 0);

            if (is_array($questions) && $passScore > count($questions)) {
                $v->errors()->add('pass_score', 'حد نصاب قبولی نمی‌تواند بیش از تعداد سؤال‌ها باشد.');
            }
        });

        return $validator;
    }

    public function startSession(Campaign $campaign, Customer $customer): GameSession
    {
        return app(StartGameSessionAction::class)->handle($campaign, $customer);
    }

    /**
     * تصحیح کامل سمت سرور — پاسخ‌های خارج از بازه «غلط» حساب می‌شوند.
     * نمره و عبور از حد نصاب هیچ وابستگی به اعتماد به کلاینت ندارد.
     */
    public function resolveResult(GameSession $session, PlayerAction $action): GameResult
    {
        $config = (array) ($session->campaign->configuration->config ?? []);
        $questions = array_values(array_filter((array) ($config['questions'] ?? []), 'is_array'));
        $answers = is_array($action->payload['answers'] ?? null) ? array_values($action->payload['answers']) : [];

        $score = 0;
        $review = [];

        foreach ($questions as $i => $question) {
            $optionsCount = is_array($question['options'] ?? null) ? count($question['options']) : 0;
            $given = $answers[$i] ?? null;
            $isValid = is_int($given) && $given >= 0 && ($optionsCount === 0 || $given < $optionsCount);
            $correctIndex = (int) ($question['correct_index'] ?? -1);
            $isCorrect = $isValid && $given === $correctIndex;

            if ($isCorrect) {
                $score++;
            }

            $review[] = [
                'chosen' => $isValid ? $given : null,
                'correct_index' => $correctIndex,
                'ok' => $isCorrect,
            ];
        }

        $passScore = max(1, min(count($questions), (int) ($config['pass_score'] ?? count($questions))));
        $passed = $score >= $passScore;

        $display = [
            'score' => $score,
            'total' => count($questions),
            'pass_score' => $passScore,
            'passed' => $passed,
            // مرور پاسخ‌ها فقط در صورت فعال‌بودن show_answers (فصل ۸-۱)
            'review' => ! empty($config['show_answers']) ? $review : null,
        ];

        if (! $passed) {
            return GameResult::noReward(['score' => $score], $display);
        }

        $rewardRef = $config['reward_ref'] ?? null;

        return GameResult::win(
            is_string($rewardRef) && $rewardRef !== '' ? $rewardRef : null,
            ['score' => $score],
            $display,
        );
    }

    public function remainingPlays(Campaign $campaign, Customer $customer): int
    {
        return app(CampaignRuleEngine::class)->remainingToday($campaign, $customer);
    }
}
