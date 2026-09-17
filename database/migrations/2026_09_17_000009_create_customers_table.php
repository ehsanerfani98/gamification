<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// مشتریان نهایی — فصل ۳-۴: موبایل یکتا در قلمرو هر Store (چنداجارگی).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained();
            $table->string('phone', 20);
            $table->string('name')->nullable();
            $table->string('referral_code', 20)->nullable()->unique();
            $table->foreignId('referred_by')->nullable()->constrained('customers');
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'phone']);
            $table->index(['store_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
