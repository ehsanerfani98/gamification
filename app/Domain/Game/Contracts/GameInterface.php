<?php

namespace App\Domain\Game\Contracts;

use App\Domain\Game\DTO\GameMetadata;
use App\Domain\Game\DTO\GameResult;
use App\Domain\Game\DTO\PlayerAction;
use App\Models\Campaign;
use App\Models\Customer;
use App\Models\GameSession;
use Illuminate\Contracts\Validation\Validator;

/**
 * قرارداد تنها نقطه اتصال هر پلاگین بازی به هسته — فصل ۵ سند معماری.
 *
 * اصول:
 *  ۱. هر بازی ماژولی مستقل با UI، Configuration، Validation، قوانین و نتیجه خودش است.
 *  ۲. پلاگین هرگز مستقیم جایزه نمی‌سازد؛ نتیجه به Reward Engine واگذار می‌شود.
 *  ۳. نتیجه بازی همیشه سمت سرور با random_int تولید می‌شود؛ ورودی کلاینت اعتباری ندارد.
 */
interface GameInterface
{
    /** متادیتای بازی (کد، نام، دسته، نسخه) */
    public static function metadata(): GameMetadata;

    /** JSON Schema کامل پیکربندی — پنل Wizard فرم خود را به‌صورت پویا از همین Schema می‌سازد */
    public static function configSchema(): array;

    /** اعتبارسنجی اختصاصی پیکربندی + قوانین خاص بازی (لایه دفاع اول در Wizard، دوم پیش از Start) */
    public function validateConfig(array $config): Validator;

    /** شروع Session با بررسی قوانین مشارکت و ساخت توکن یک‌بارمصرف */
    public function startSession(Campaign $campaign, Customer $customer): GameSession;

    /** تولید نتیجه امن سمت سرور با weighted RNG و خروجی استاندارد GameResult */
    public function resolveResult(GameSession $session, PlayerAction $action): GameResult;

    /** تعداد بازی باقیمانده کاربر در این کمپین */
    public function remainingPlays(Campaign $campaign, Customer $customer): int;
}
