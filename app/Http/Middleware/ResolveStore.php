<?php

namespace App\Http\Middleware;

use App\Models\Store;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * تشخیص فروشگاه جاری درخواست‌های Merchant — فصل ۲-۳ سند معماری.
 *
 * فروشگاه از هدر X-Store-Id (یا اولین فروشگاه کاربر) فقط از میان
 * فروشگاه‌های متعلق به کاربر احرازشده resolve می‌شود؛ تلاش برای
 * دسترسی به Store دیگری 404 برمی‌گرداند (بدون افشای وجود منبع).
 * پس از آن TenantContext::storeId پر می‌شود و Global Scope
 * تمام کوئری‌های دامنه‌های Tenant-دار را محدود می‌کند.
 */
final class ResolveStore
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $requestedId = (int) ($request->header('X-Store-Id') ?: 0);

        $store = $user->stores()
            ->when($requestedId > 0, fn ($q) => $q->whereKey($requestedId))
            ->orderBy('id')
            ->first();

        // اگر Store درخواستی متعلق به کاربر نباشد → 404 (Cross-Tenant)
        if ($requestedId > 0 && ($store === null || (int) $store->getKey() !== $requestedId)) {
            abort(404);
        }

        if ($store === null) {
            abort(404, 'فروشگاهی یافت نشد. ابتدا یک فروشگاه بسازید.');
        }

        $request->attributes->set('store', $store);
        TenantContext::set((int) $store->getKey(), $user->role);

        return $next($request);
    }
}
