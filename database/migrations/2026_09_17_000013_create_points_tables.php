<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Ledger امتیاز — فصل ۳-۴ و ۶-۴: موجودی هر مشتری فقط از جمع تراکنش‌های
// امضاشده به‌دست می‌آید و هیچ کدی موجودی را مستقیم به‌روزرسانی نمی‌کند؛
// این الگوی Ledger از افت حساب‌ها جلوگیری می‌کند و ردگیری کامل دارد.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('point_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained();
            $table->foreignId('customer_id')->constrained();
            $table->timestamps();

            $table->unique('customer_id');
        });

        Schema::create('point_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained();
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('point_account_id')->constrained();
            $table->integer('delta'); // مثبت = کسب، منفی = خرج/انقضا
            $table->string('type', 20); // earn | redeem | expire | adjust
            $table->string('reference_type', 60)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('description', 200)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['customer_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('point_transactions');
        Schema::dropIfExists('point_accounts');
    }
};
