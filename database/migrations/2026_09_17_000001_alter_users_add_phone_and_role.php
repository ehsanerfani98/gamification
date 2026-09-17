<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ساحه هویت (فصل ۳-۲): ورود صرفاً با موبایل + OTP انجام می‌شود؛
// email و password برای Merchant اختیاری‌اند. نقش‌ها: merchant و admin.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->unique()->after('id');
            $table->string('role', 20)->default('merchant')->after('phone');
            $table->string('name')->nullable()->change();
            $table->string('email')->nullable()->change();
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['phone']);
            $table->dropColumn(['phone', 'role']);
        });
    }
};
