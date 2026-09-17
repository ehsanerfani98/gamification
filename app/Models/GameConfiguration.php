<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['campaign_id', 'schema_version', 'config'])]
class GameConfiguration extends Model
{
    protected function casts(): array
    {
        return [
            'config' => 'array',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * کلیدهای حساس که هرگز در پیکربندی عمومی افشا نمی‌شوند (فصل ۸-۱):
     * وزن‌ها (تصمیم احتمالی)، مرجع جایزه (حساس مالی)، mode احتمال و
     * کلید پاسخ کوئیز (correct_index).
     */
    private const HIDDEN_PUBLIC_KEYS = ['weight', 'reward_ref', 'probability_mode', 'correct_index'];

    /** پیکربندی عمومی و امن برای کلاینت — حذف بازگشتی کلیدهای حساس (فصل ۸-۱) */
    public function toPublicArray(): array
    {
        return $this->stripSensitive($this->config ?? []);
    }

    /**
     * حذف بازگشتی کلیدهای حساس در هر عمق پیکربندی.
     *
     * مزیت نسبت به فهرست سفید ثابتِ هر بازی: پلاگین‌های جدید (مثل Quiz با
     * correct_index یا Scratch با symbols وزنی) بدون تغییر این کلاس پوشش
     * داده می‌شوند — افزودن بازی جدید باز هم «صفر تغییر در هسته» است.
     */
    private function stripSensitive(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $clean = [];

        foreach ($value as $key => $child) {
            if (is_string($key) && in_array($key, self::HIDDEN_PUBLIC_KEYS, true)) {
                continue;
            }

            $clean[$key] = $this->stripSensitive($child);
        }

        return $clean;
    }
}
