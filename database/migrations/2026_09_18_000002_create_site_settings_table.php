<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * تنظیمات سایت (تک‌ردیفی) — Sprint 8: سندباکس پیامک و پرداخت برای بتای ۵ فروشگاه.
 *
 * سندباکس پیامک: بدون ارسال واقعی SMS؛ کد OTP در پاسخ API و لاگ (تست بدون اعتبار پیامک).
 * سندباکس پرداخت: شبیه‌ساز کامل جریان دروازه (Request → صفحه پرداخت → callback → verify)
 * بدون تماس خارجی — کلید واقعی ZarinPal بعداً با env اضافه می‌شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_settings', function (Blueprint $table): void {
            $table->id();
            $table->boolean('sms_sandbox')->default(false);
            $table->boolean('payment_sandbox')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};
