<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ساحه کمپین و اجرای بازی — فصل ۳-۳ سند معماری.
// game_sessions مهم‌ترین جدول است: نتیجه بازی فقط همین‌جا و فقط سمت سرور نوشته می‌شود.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained();
            $table->foreignId('game_id')->constrained();
            $table->string('title');
            $table->string('slug', 80)->unique();
            $table->string('status', 20)->default('draft'); // draft | scheduled | published | expired | archived
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->json('theme')->nullable(); // رنگ و لوگوی برند
            $table->timestamps();

            $table->index(['store_id', 'status']);
        });

        // پیکربندی اختصاصی بازیِ هر کمپین با نسخه Schema (فصل ۵-۳)
        Schema::create('game_configurations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained();
            $table->string('schema_version', 10)->default('1');
            $table->json('config');
            $table->timestamps();

            $table->unique('campaign_id');
        });

        // قوانین مشارکت به‌صورت رکورد نوع‌دار (فصل ۳-۳)
        Schema::create('campaign_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained();
            $table->string('type', 40); // once_daily | new_customer_only | max_total_plays | max_daily_wins
            $table->json('value')->nullable();
            $table->timestamps();

            $table->index(['campaign_id', 'type']);
        });

        Schema::create('game_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained();
            $table->foreignId('campaign_id')->constrained();
            $table->foreignId('customer_id')->constrained();
            $table->string('play_token', 64)->unique();
            $table->string('status', 20)->default('started'); // started | completed | expired
            $table->json('result')->nullable(); // نتیجه فقط سمت سرور نوشته می‌شود (فصل ۵-۴)
            $table->string('result_signature', 128)->nullable(); // HMAC قابل اثبات
            $table->string('idempotency_key', 64)->nullable(); // فصل ۸-۳
            $table->timestamp('started_at')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['campaign_id', 'customer_id', 'status']);
            $table->unique(['customer_id', 'idempotency_key']);
        });

        Schema::create('campaign_participations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained();
            $table->foreignId('campaign_id')->constrained();
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('game_session_id')->nullable()->constrained();
            $table->string('outcome', 20)->nullable(); // win | no_reward
            $table->date('played_on'); // پنجره روزانه
            $table->timestamp('rewarded_at')->nullable();
            $table->timestamps();

            // مشارکت چندباره در همان روز را در سطح دیتابیس می‌بندد (فصل ۲-۵)
            $table->unique(['campaign_id', 'customer_id', 'played_on']);
            $table->index(['campaign_id', 'outcome']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_participations');
        Schema::dropIfExists('game_sessions');
        Schema::dropIfExists('campaign_rules');
        Schema::dropIfExists('game_configurations');
        Schema::dropIfExists('campaigns');
    }
};
