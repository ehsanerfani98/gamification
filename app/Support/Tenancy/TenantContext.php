<?php

namespace App\Support\Tenancy;

/**
 * زمینه Tenant جاری در طول چرخه یک Request.
 *
 * در Middleware از شناسه فروشگاهِ استخراج‌شده از کاربر احرازشده
 * یا اسلاگ کمپین عمومی تعیین می‌شود و هیچ Endpointای بدون این
 * زمینه اجازه دسترسی به داده‌های Tenant-دار ندارد (فصل ۲-۳).
 */
final class TenantContext
{
    private static ?int $storeId = null;

    /** merchant | admin | customer | public */
    private static ?string $actor = null;

    public static function set(?int $storeId, ?string $actor = null): void
    {
        self::$storeId = $storeId;

        if ($actor !== null) {
            self::$actor = $actor;
        }
    }

    public static function storeId(): ?int
    {
        return self::$storeId;
    }

    public static function actor(): ?string
    {
        return self::$actor;
    }

    public static function forget(): void
    {
        self::$storeId = null;
        self::$actor = null;
    }
}
