<?php

namespace App\Domain\Game\Plugins\Support;

use App\Domain\Game\Services\WeightedRandomizer;

/**
 * انتخاب «نتیجه‌اول» برای بازی‌های Outcome-First — Sprint 4 (فصل ۵-۳).
 *
 * در بازی‌های Pick/Arcade/Skill، ابتدا برد یا باخت فقط با weighted RNG سمت
 * سرور تعیین می‌شود و سپس نتیجه روی گزینه‌ای که کاربر انتخاب کرده «برجسته»
 * می‌شود؛ بنابراین ورودی کلاینت هیچ نقشی در سرنوشت بازی ندارد (فصل ۲-۵).
 */
final class WeightedOutcome
{
    /**
     * @param  array<int, array<string, mixed>>  $prizes  فهرست جایزه‌های ممکن (هر آیتم باید weight داشته باشد)
     * @param  int  $emptyWeight  وزن «بدون جایزه»؛ صفر یعنی همیشه از میان جوایز انتخاب شود
     * @return array{0: int|string, 1: array<string, mixed>|null} [کلید انتخاب‌شده، جایزه یا null]
     */
    public static function pick(array $prizes, int $emptyWeight): array
    {
        $items = $prizes;

        if ($emptyWeight > 0) {
            $items['empty'] = ['weight' => $emptyWeight];
        }

        $key = WeightedRandomizer::pick($items);

        return [$key, $key === 'empty' ? null : $prizes[$key]];
    }
}
