<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// جداول Analytics و Retention — فصل ۴-۳ و ۱۰ سند معماری (Sprint 5).
// analytics_events رویداد Append-Only است: هیچ ویرایشی وجود ندارد و فقط
// پس از گذشت دوره نگهداری پاک می‌شود (دستور analytics:aggregate).
return new class extends Migration
{
    public function up(): void
    {
        // رخدادهای رفتاری برای قیف View → Enter → Play → Win → Redeem
        Schema::create('analytics_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained();
            $table->foreignId('campaign_id')->nullable()->constrained();
            $table->foreignId('customer_id')->nullable()->constrained();
            $table->unsignedBigInteger('game_session_id')->nullable();
            $table->string('name', 30); // view | enter | play | win | redeem | checkin | referral
            $table->json('properties')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->nullable();

            $table->index(['store_id', 'name', 'occurred_at']);
            $table->index(['campaign_id', 'name', 'occurred_at']);
            $table->index(['customer_id', 'name']);
            $table->index('occurred_at');
        });

        // تجمیع شبانه — سری زمانی روزانه هر کمپین برای نمودارها و گزارش‌های بلندمدت
        Schema::create('campaign_daily_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained();
            $table->foreignId('campaign_id')->constrained();
            $table->date('stat_date');
            $table->unsignedInteger('views')->default(0);
            $table->unsignedInteger('enters')->default(0);
            $table->unsignedInteger('plays')->default(0);
            $table->unsignedInteger('wins')->default(0);
            $table->unsignedInteger('redeems')->default(0);
            $table->unsignedInteger('checkins')->default(0);
            $table->unsignedInteger('unique_customers')->default(0);
            $table->timestamps();

            $table->unique(['campaign_id', 'stat_date']);
            $table->index(['store_id', 'stat_date']);
        });

        // چک‌این روزانه مشتری با زنجیره Streak — فصل ۱۰ (Retention)
        Schema::create('customer_checkins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained();
            $table->foreignId('customer_id')->constrained();
            $table->date('checkin_date');
            $table->unsignedInteger('streak')->default(1);
            $table->unsignedInteger('points_awarded')->default(0);
            $table->timestamp('created_at')->nullable();

            $table->unique(['customer_id', 'checkin_date']);
            $table->index(['store_id', 'checkin_date']);
        });

        // دعوت دوستان — هر invited در هر Store فقط یک‌بار شمرده می‌شود
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained();
            $table->foreignId('referrer_id')->constrained('customers');
            $table->foreignId('invited_id')->constrained('customers');
            $table->string('status', 20)->default('completed');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'invited_id']);
            $table->index(['store_id', 'referrer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referrals');
        Schema::dropIfExists('customer_checkins');
        Schema::dropIfExists('campaign_daily_stats');
        Schema::dropIfExists('analytics_events');
    }
};
