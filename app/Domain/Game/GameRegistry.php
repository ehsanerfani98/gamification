<?php

namespace App\Domain\Game;

use App\Domain\Game\Contracts\GameInterface;
use App\Support\Exceptions\ApiException;

/**
 * Game Registry — فصل ۵-۱ سند معماری.
 *
 * نگاشت «کد بازی» به «پلاگین» از config/games.php خوانده می‌شود.
 * افزودن بازی جدید فقط: ساخت پکیج در app/Domain/Game/Plugins + ثبت یک خط در config
 * + رکورد جدول games — هیچ تغییری در هسته لازم نیست (معیار پذیرش Code Review).
 */
final class GameRegistry
{
    /** @return array<string, class-string<GameInterface>> */
    public static function plugins(): array
    {
        $plugins = [];

        foreach ((array) config('games.plugins', []) as $code => $class) {
            if (! class_exists($class)) {
                continue; // پلاگین هنوز پیاده‌سازی نشده
            }

            if (! is_subclass_of($class, GameInterface::class)) {
                throw new \RuntimeException("Game plugin [{$class}] must implement GameInterface.");
            }

            $plugins[$code] = $class;
        }

        return $plugins;
    }

    public static function has(string $code): bool
    {
        return isset(self::plugins()[$code]);
    }

    /** نمونه پلاگین برای کد بازی؛ در نبود پلاگین 404 */
    public static function for(string $code): GameInterface
    {
        $class = self::plugins()[$code] ?? null;

        if ($class === null) {
            throw new ApiException('GAME_NOT_FOUND', 'بازی موردنظر یافت نشد یا فعال نیست.', 404);
        }

        return app($class);
    }
}
