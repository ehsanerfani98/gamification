<?php

namespace App\Domain\Game\DTO;

/**
 * متادیتای یک پلاگین بازی — توسط GameInterface::metadata() ارائه می‌شود.
 */
final class GameMetadata
{
    public function __construct(
        /** کد یکتای بازی — همان کد جدول games (مثل wheel, dice, quiz) */
        public readonly string $code,
        /** نام نمایشی فارسی */
        public readonly string $name,
        /** دسته بازی — مطابق config/games.php (chance, skill, ...) */
        public readonly string $category,
        /** توضیح کوتاه برای Game Library پنل فروشگاه‌دار */
        public readonly string $description = '',
        public readonly string $version = '1.0.0',
    ) {}

    /** @return array<string, string> */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'category' => $this->category,
            'description' => $this->description,
            'version' => $this->version,
        ];
    }
}
