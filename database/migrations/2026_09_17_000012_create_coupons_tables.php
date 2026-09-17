<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// کوپن و Redemption — فصل ۳-۴: کد یکتای هر برنده متصل به مشتری و Store؛
// coupon_redemptions لحظه استفاده واقعی را ثبت می‌کند (حلقه اندازه‌گیری ROI).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained();
            $table->foreignId('campaign_id')->nullable()->constrained();
            $table->foreignId('reward_id')->nullable()->constrained();
            $table->foreignId('customer_id')->constrained();
            $table->string('code', 32)->unique();
            $table->string('type', 30); // percentage|fixed|free_shipping|free_product
            $table->json('value')->nullable(); // درصد، سقف مبلغ، مبلغ ثابت، SKU
            $table->timestamp('expires_at')->nullable();
            $table->string('status', 20)->default('issued'); // issued | redeemed | expired
            $table->timestamp('redeemed_at')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'customer_id', 'status']);
            $table->index(['status', 'expires_at']);
        });

        Schema::create('coupon_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained();
            $table->unsignedInteger('amount_irt')->nullable(); // مبلغ تراکنش واقعی
            $table->string('order_ref', 60)->nullable(); // شماره سفارش در فروشگاه
            $table->timestamp('redeemed_at');
            $table->timestamps();

            $table->index(['coupon_id', 'redeemed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_redemptions');
        Schema::dropIfExists('coupons');
    }
};
