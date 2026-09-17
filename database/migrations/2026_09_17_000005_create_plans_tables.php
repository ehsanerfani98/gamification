<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Planها رکوردهای داده‌ای هستند — فصل ۷-۱: سقف‌ها و امکانات در ستون features (JSON)
// و دسترسی بازی‌ها از طریق جدول واسط plan_game کنترل می‌شود
// (Feature Gating داده‌محور؛ بدون انتشار نسخه توسط Admin قابل تغییر است).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug', 30)->unique(); // free | basic | pro
            $table->unsignedInteger('price_irt')->default(0); // تومان
            $table->string('billing_period', 20)->default('monthly');
            $table->json('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('plan_game', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained();
            $table->foreignId('game_id')->constrained();
            $table->timestamps();

            $table->unique(['plan_id', 'game_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_game');
        Schema::dropIfExists('plans');
    }
};
