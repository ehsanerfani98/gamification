<?php

namespace App\Support\Tenancy;

use App\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ایزوله‌سازی چنداجارگی اجباری — فصل ۲-۳ سند معماری.
 *
 * هر مدلی که به Store تعلق دارد این Trait را use می‌کند:
 *  - Global Scope: تمام پرس‌وجوها فقط در قلمرو Store جاری (TenantContext)
 *  - Auto-Fill: store_id هنگام ساخت رکورد از TenantContext پر می‌شود
 *
 * تست Tenant Isolation جزو تست‌های اجباری هر اسپرینت است؛ تلاش برای دسترسی
 * به منبع فروشگاه دیگر باید 404 برگرداند (و نه 403) تا وجود منبع افشا نشود.
 */
trait BelongsToStore
{
    public static function bootBelongsToStore(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder): void {
            $storeId = TenantContext::storeId();

            if ($storeId !== null) {
                $builder->where($builder->getModel()->getTable().'.store_id', $storeId);
            }
        });

        static::creating(function ($model): void {
            if ($model->store_id === null && TenantContext::storeId() !== null) {
                $model->store_id = TenantContext::storeId();
            }
        });
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
