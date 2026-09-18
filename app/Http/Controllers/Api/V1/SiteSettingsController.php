<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Support\Settings\SiteSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * تنظیمات سایت — فقط Admin (پنل → بخش تنظیمات سایت) — Sprint 8.
 *
 * سندباکس پیامک و پرداخت برای بتای ۵ فروشگاه: مدیر سایت بدون کلید واقعی
 * سرویس‌ها، کل جریان را تست می‌کند؛ تغییر وضعیت در Audit ثبت می‌شود.
 */
final class SiteSettingsController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly SiteSettingsService $settings,
    ) {}

    /** GET /api/v1/site-settings */
    public function show(): JsonResponse
    {
        $settings = $this->settings->get();

        return $this->ok([
            'sms_sandbox' => $settings->sms_sandbox,
            'payment_sandbox' => $settings->payment_sandbox,
        ]);
    }

    /** PATCH /api/v1/site-settings */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sms_sandbox' => ['required', 'boolean'],
            'payment_sandbox' => ['required', 'boolean'],
        ]);

        $settings = $this->settings->update($data);

        AuditLog::record('site.settings_updated', $request->user(), $settings, $data);

        return $this->ok([
            'sms_sandbox' => $settings->sms_sandbox,
            'payment_sandbox' => $settings->payment_sandbox,
        ]);
    }
}
