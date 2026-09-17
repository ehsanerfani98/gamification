<?php

namespace App\Domain\Game\Services;

use App\Support\Exceptions\ApiException;

/**
 * انتخاب وزنی امن با random_int (تصادف رمزنگارانه) — فصل ۶-۳ سند معماری.
 *
 * وزن‌ها توسط فروشگاه‌دار تعریف می‌شوند؛ سقف‌های سیستمی به‌عنوان لایه
 * حفاظتی پیش از این انتخاب اعمال می‌شوند (Reward Engine — فصل ۶-۴).
 */
final class WeightedRandomizer
{
    /**
     * @param  array<int|string, array<string, mixed>>  $items  هر آیتم باید کلید weight داشته باشد
     * @return int|string کلید آیتم انتخاب‌شده
     */
    public static function pick(array $items, string $weightKey = 'weight'): int|string
    {
        $total = 0;

        foreach ($items as $item) {
            $total += (int) ($item[$weightKey] ?? 0);
        }

        if ($total <= 0) {
            throw new ApiException('INVALID_WEIGHTS', 'مجموع وزن‌ها باید مثبت باشد.');
        }

        $roll = random_int(1, $total);
        $accumulator = 0;

        foreach ($items as $key => $item) {
            $accumulator += (int) ($item[$weightKey] ?? 0);

            if ($roll <= $accumulator) {
                return $key;
            }
        }

        return array_key_last($items);
    }
}
