<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Game Registry — فصل ۳-۳ و ۵-۵: بازی‌ها و دسته‌ها داده‌ای هستند، نه شاخه کد.
// جدول‌های سراسری خارج از قلمرو Tenant؛ فقط Admin آن‌ها را می‌نویسد.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_category_id')->constrained();
            $table->string('code', 30)->unique();        // wheel, dice, quiz, ...
            $table->string('name');
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            // انواع جایزه پشتیبانی‌شده برای Game Library پنل
            $table->json('supported_reward_types')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('games');
        Schema::dropIfExists('game_categories');
    }
};
