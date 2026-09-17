<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// تجمیع شبانه Analytics و پاک‌سازی رخدادهای قدیمی — فصل ۱۰ (Sprint 5)
Schedule::command('analytics:aggregate')->dailyAt('03:00');
