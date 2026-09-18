<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * متغیرهای درگاه پرداخت و پیامک در تنظیمات سایت — قابل ذخیره/بروزرسانی از پنل (Admin).
 *
 * همه ستون‌ها nullable هستند: مقدار null یعنی «پیش‌فرض env/config» استفاده شود؛
 * بنابراین رفتار فعلی مبتنی بر env حفظ می‌شود و Admin می‌تواند بدون دست‌زدن به
 * سرور، کلیدها و درایورها را از پنل عوض کند.
 *
 * zarinpal_sandbox: سندباکس رسمی خود زرین‌پال (sandbox.zarinpal.com — همان API v4)
 * که با روشن‌شدن، base URL به محیط آزمایشگاهی زرین‌پال سوئیچ می‌شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table): void {
            // ── پیامک (IPPanel) ──────────────────────────────
            $table->string('sms_channel')->nullable();          // log | ippanel
            $table->text('ippanel_api_key')->nullable();        // کلید API (ماسک در پاسخ)
            $table->string('ippanel_originator')->nullable();   // خط ارسال
            $table->string('ippanel_base_url')->nullable();     // پیش‌فرض https://api2.ippanel.com

            // ── درگاه پرداخت (ZarinPal) ──────────────────────
            $table->string('payment_gateway')->nullable();      // fake | zarinpal
            $table->string('zarinpal_merchant_id')->nullable();
            $table->boolean('zarinpal_sandbox')->default(false); // سندباکس رسمی زرین‌پال
            $table->string('zarinpal_base_url')->nullable();     // خالی → خودکار production/sandbox
            $table->boolean('zarinpal_toman_to_rial')->nullable(); // null → پیش‌فرض true (×۱۰)
            $table->string('zarinpal_callback_url')->nullable();
            $table->string('zarinpal_description')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'sms_channel',
                'ippanel_api_key',
                'ippanel_originator',
                'ippanel_base_url',
                'payment_gateway',
                'zarinpal_merchant_id',
                'zarinpal_sandbox',
                'zarinpal_base_url',
                'zarinpal_toman_to_rial',
                'zarinpal_callback_url',
                'zarinpal_description',
            ]);
        });
    }
};
