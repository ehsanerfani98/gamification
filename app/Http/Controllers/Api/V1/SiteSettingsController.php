<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Support\Settings\SiteSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * تنظیمات سایت — فقط Admin (پنل → بخش تنظیمات سایت) — Sprint 8 و 9.
 *
 * سندباکس پیامک و پرداخت برای بتای ۵ فروشگاه + متغیرهای درایور پیامک/پرداخت:
 * درایور و کلیدهای IPPanel/ZarinPal (و سندباکس رسمی زرین‌پال) همه از همین
 * Endpoint قابل ذخیره و بروزرسانی‌اند — بدون تغییر env سرور.
 *
 * امنیت: کلید API پیامک فقط ماسک‌شده در پاسخ برمی‌گردد؛ در ذخیره، مقدار
 * «خالی» برای کلید یعنی حفظ مقدار قبلی (جلوگیری از پاک‌شدن تصادفی کلید).
 * هر تغییر در Audit با رخداد site.settings_updated ثبت می‌شود (بدون کلید خام).
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
            // سندباکس‌های داخلی (بتا)
            'sms_sandbox' => $settings->sms_sandbox,
            'payment_sandbox' => $settings->payment_sandbox,

            // ── پیامک (IPPanel) ──
            'sms_channel' => $settings->sms_channel,
            'ippanel_api_key_masked' => $settings->maskedIppanelApiKey(),
            'ippanel_originator' => $settings->ippanel_originator,
            'ippanel_base_url' => $settings->ippanel_base_url,

            // ── درگاه پرداخت (ZarinPal) ──
            'payment_gateway' => $settings->payment_gateway,
            'zarinpal_merchant_id' => $settings->zarinpal_merchant_id,
            'zarinpal_sandbox' => (bool) $settings->zarinpal_sandbox,
            'zarinpal_base_url' => $settings->zarinpal_base_url,
            'zarinpal_toman_to_rial' => $settings->zarinpal_toman_to_rial,
            'zarinpal_callback_url' => $settings->zarinpal_callback_url,
            'zarinpal_description' => $settings->zarinpal_description,

            // مقادیر مؤثر (DB یا پیش‌فرض env) — برای نمایش وضعیت واقعی در پنل
            'effective' => [
                'sms_channel' => $this->settings->smsChannel(),
                'payment_gateway' => $this->settings->paymentGateway(),
                'zarinpal_base_url' => $this->settings->zarinpalBaseUrl(),
            ],
        ]);
    }

    /** PATCH /api/v1/site-settings */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            // سندباکس‌های داخلی
            'sms_sandbox' => ['nullable', 'boolean'],
            'payment_sandbox' => ['nullable', 'boolean'],

            // ── پیامک (IPPanel) ──
            'sms_channel' => ['nullable', Rule::in(['log', 'ippanel'])],
            'ippanel_api_key' => ['nullable', 'string', 'max:512'],
            'ippanel_originator' => ['nullable', 'string', 'max:32'],
            'ippanel_base_url' => ['nullable', 'string', 'max:255'],

            // ── درگاه پرداخت (ZarinPal) ──
            'payment_gateway' => ['nullable', Rule::in(['fake', 'zarinpal'])],
            'zarinpal_merchant_id' => ['nullable', 'string', 'max:64'],
            'zarinpal_sandbox' => ['nullable', 'boolean'],
            'zarinpal_base_url' => ['nullable', 'string', 'max:255'],
            'zarinpal_toman_to_rial' => ['nullable', 'boolean'],
            'zarinpal_callback_url' => ['nullable', 'string', 'max:255'],
            'zarinpal_description' => ['nullable', 'string', 'max:255'],
        ]);

        // کلید API پیامک: مقدار خالی/حذف‌شده = حفظ مقدار قبلی (پاک‌شدن تصادفی ممنوع)
        if (array_key_exists('ippanel_api_key', $data) && trim((string) $data['ippanel_api_key']) === '') {
            unset($data['ippanel_api_key']);
        }

        $settings = $this->settings->update($data);

        // Audit بدون کلید خام — کلید فقط به‌صورت ماسک ثبت می‌شود
        $auditData = $data;
        unset($auditData['ippanel_api_key']);

        if ($request->filled('ippanel_api_key')) {
            $auditData['ippanel_api_key'] = '(updated)';
        }

        AuditLog::record('site.settings_updated', $request->user(), $settings, $auditData);

        return $this->ok([
            'sms_sandbox' => $settings->sms_sandbox,
            'payment_sandbox' => $settings->payment_sandbox,
            'sms_channel' => $settings->sms_channel,
            'ippanel_api_key_masked' => $settings->maskedIppanelApiKey(),
            'ippanel_originator' => $settings->ippanel_originator,
            'ippanel_base_url' => $settings->ippanel_base_url,
            'payment_gateway' => $settings->payment_gateway,
            'zarinpal_merchant_id' => $settings->zarinpal_merchant_id,
            'zarinpal_sandbox' => (bool) $settings->zarinpal_sandbox,
            'zarinpal_base_url' => $settings->zarinpal_base_url,
            'zarinpal_toman_to_rial' => $settings->zarinpal_toman_to_rial,
            'zarinpal_callback_url' => $settings->zarinpal_callback_url,
            'zarinpal_description' => $settings->zarinpal_description,
            'effective' => [
                'sms_channel' => $this->settings->smsChannel(),
                'payment_gateway' => $this->settings->paymentGateway(),
                'zarinpal_base_url' => $this->settings->zarinpalBaseUrl(),
            ],
        ]);
    }
}
