<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * تعیین زمینه Tenant — فصل ۲-۳ سند معماری.
 *
 * در ابتدای هر Request:
 *  - Guardهای احراز هویت از نو ساخته می‌شوند تا توکن هر Request تازه resolve شود
 *    (در محیط تست که چند Request در یک پروسه اجرا می‌شوند، کش Guard باید خالی شود؛
 *    در production هر Request پروسه مستقل دارد و این فراخوانی هزینه‌ای ندارد).
 *  - زمینه Tenant رکورد قبلی پاک می‌شود.
 */
final class SetTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        app('auth')->forgetGuards();
        TenantContext::forget();

        return $next($request);
    }
}
