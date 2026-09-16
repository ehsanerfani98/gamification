<?php

namespace App\Domain\Game\DTO;

/**
 * اکشن عمومی کاربر در حین بازی.
 *
 * امنیت (فصل ۲-۵): هیچ فیلدی از payload کلاینت در تصمیم نتیجه به‌کار نمی‌رود؛
 * نتیجه فقط با weighted RNG سمت سرور تولید می‌شود. payload صرفاً برای
 * ثبت رخداد و منطق نمایش/امتیازدهی مهارتی استفاده می‌شود.
 */
final class PlayerAction
{
    public function __construct(
        /** نوع اکشن: spin | pick | scratch | answer | tap | ... */
        public readonly string $type,
        /** @var array<string, mixed> داده عمومی اکشن (بدون اعتبار امنیتی) */
        public readonly array $payload = [],
    ) {}
}
