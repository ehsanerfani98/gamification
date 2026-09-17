<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Audit Log غیرقابل ویرایش — فصل ۲-۵: روی رخدادهای حساس (ورود، Start Session،
// صدور جایزه، تغییر اشتراک) ثبت می‌شود تا زنجیره رویداد قابل بازسازی باشد.
// رکوردها Append-Only هستند (بدون updated_at و بدون حذف).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained();
            $table->string('actor_type', 20)->nullable(); // merchant | admin | customer | system
            $table->string('action', 50);
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('ip', 45)->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['subject_type', 'subject_id']);
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
