<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// اشتراک هر Store — فصل ۷-۳: ماشین حالت Active → Past Due → Expired → Canceled
// (Trial در صورت پیکربندی). هر تغییر وضعیت Event پیام‌های SubscriptionChanged منتشر می‌کند.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained();
            $table->foreignId('plan_id')->constrained();
            $table->string('status', 20)->default('active'); // active | past_due | expired | canceled
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
