<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// مدل داده جایزه — فصل ۳-۴: موجودی جاری جدا از تعریف جایزه نگه داشته می‌شود
// تا کسر موجودی با قفل اتمی و بدون قفل‌کردن رکورد تعریف انجام شود.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained();
            $table->foreignId('campaign_id')->constrained();
            // مرجع جایزه — پلاگین بازی با همین ref به جایزه اشاره می‌کند (فصل ۶-۲)
            $table->string('ref', 60);
            $table->string('type', 30); // percentage|fixed|free_shipping|free_product|gift|points|store_coupon|custom|none
            $table->string('name');
            $table->json('params')->nullable(); // درصد، مبلغ، SKU، تعداد امتیاز، دستورالعمل و…
            $table->unsignedInteger('weight')->default(1); // وزن انتخاب وزنی در Reward Engine
            $table->unsignedInteger('total_qty')->nullable(); // سقف کل؛ null = بی‌نهایت
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['campaign_id', 'ref']);
            $table->index(['campaign_id', 'is_active']);
        });

        Schema::create('reward_inventory', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reward_id')->constrained();
            $table->unsignedInteger('remaining_qty');
            $table->timestamps();

            $table->unique('reward_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reward_inventory');
        Schema::dropIfExists('rewards');
    }
};
