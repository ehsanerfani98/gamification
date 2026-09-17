<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// تجمیع شبانه Analytics و پاک‌سازی رخدادهای قدیمی — فصل ۱۰ (Sprint 5)
Schedule::command('analytics:aggregate')->dailyAt('03:00');

// بستن کمپین‌های منقضی — فصل ۱۰ (Sprint 6)
Schedule::command('campaigns:close-expired')->everyTenMinutes();

// انقضای Sessionهای راکد با عمر توکن گذشته — فصل ۱۰ (Sprint 6)
Schedule::command('sessions:expire')->everyFiveMinutes();

// بکاپ روزانه یکپارچه از پایگاه‌داده — فصل ۱۰ (Sprint 6)
Schedule::command('database:backup')->dailyAt('04:00');
