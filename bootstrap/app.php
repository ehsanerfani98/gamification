<?php

use App\Http\Middleware\ResolveStore;
use App\Http\Middleware\SetTenantContext;
use App\Support\Exceptions\ApiException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'tenant.context' => SetTenantContext::class,
            'resolve.store' => ResolveStore::class,
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
        ]);

        // زمینه Tenant روی همه مسیرهای API تعیین می‌شود — فصل ۲-۳
        $middleware->api(prepend: [SetTenantContext::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // قرارداد پاسخ خطا — فصل ۸-۱: { error: { code, message, fields } }
        $exceptions->render(function (ApiException $e, Request $request) {
            return response()->json([
                'error' => [
                    'code' => $e->errorCode,
                    'message' => $e->getMessage(),
                    'fields' => (object) $e->fields,
                ],
            ], $e->status);
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            return response()->json([
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'داده‌های ورودی معتبر نیستند.',
                    'fields' => $e->errors(),
                ],
            ], 422);
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            return response()->json([
                'error' => ['code' => 'UNAUTHENTICATED', 'message' => 'برای این درخواست باید وارد شوید.', 'fields' => (object) []],
            ], 401);
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            // Cross-Tenant و منبع ناموجود هر دو 404 — بدون افشای وجود منبع (فصل ۲-۵)
            return response()->json([
                'error' => ['code' => 'NOT_FOUND', 'message' => 'منبع موردنظر یافت نشد.', 'fields' => (object) []],
            ], 404);
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) {
            return response()->json([
                'error' => ['code' => 'FORBIDDEN', 'message' => 'اجازه انجام این عملیات را ندارید.', 'fields' => (object) []],
            ], 403);
        });

        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            return response()->json([
                'error' => ['code' => 'RATE_LIMITED', 'message' => 'درخواست‌های شما بیش از حد مجاز است؛ کمی بعد دوباره تلاش کنید.', 'fields' => (object) []],
            ], 429, $e->getHeaders());
        });
    })->create();
