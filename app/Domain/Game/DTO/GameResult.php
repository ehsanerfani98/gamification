<?php

namespace App\Domain\Game\DTO;

/**
 * خروجی استاندارد resolveResult — ساختاری نسخه‌دار (فصل ۵-۲ سند معماری).
 *
 * type: نوع نتیجه (Win یا NoReward)
 * raw: مقدار خام (مثلاً Segment انتخاب‌شده یا امتیاز مهارتی)
 * display: داده نمایش برای انیمیشن — کلاینت فقط «چه چیزی را نمایش دهد» دریافت می‌کند،
 *          نه «چه چیزی برگزینده شود».
 */
final class GameResult
{
    public const OUTCOME_WIN = 'win';

    public const OUTCOME_NO_REWARD = 'no_reward';

    public function __construct(
        public readonly string $outcome,
        /** مرجع جایزه در کمپین (rewards.ref) — در حالت NoReward مقدار null */
        public readonly ?string $rewardRef,
        /** @var array داده خام نتیجه */
        public readonly array $raw = [],
        /** @var array داده نمایش برای انیمیشن */
        public readonly array $display = [],
    ) {}

    public static function win(?string $rewardRef, array $raw = [], array $display = []): self
    {
        return new self(self::OUTCOME_WIN, $rewardRef, $raw, $display);
    }

    public static function noReward(array $raw = [], array $display = []): self
    {
        return new self(self::OUTCOME_NO_REWARD, null, $raw, $display);
    }

    public function isWin(): bool
    {
        return $this->outcome === self::OUTCOME_WIN;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'outcome' => $this->outcome,
            'reward_ref' => $this->rewardRef,
            'raw' => $this->raw,
            'display' => $this->display,
        ];
    }
}
