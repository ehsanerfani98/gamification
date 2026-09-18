<?php

/**
 * سرور اندازه‌گیری عملکرد — جاب Lighthouse در CI (Sprint 4).
 *
 * `php artisan serve` فشرده‌سازی ندارد؛ nginx تولیدی gzip می‌دهد.
 * برای اندازه‌گیری production-like، این روتر برای `php -S` پاسخ‌های
 * قابل‌فشرده‌سازی را با gzip برمی‌گرداند و بقیه را به index.php می‌سپارد:
 *
 *   php -S 127.0.0.1:8080 -t public scripts/perf/server.php
 */
$path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$file = __DIR__.'/../../public'.($path === '/' ? '/index.php' : $path);

// فایل استاتیک واقعی داخل public → سرو با gzip در صورت پشتیبانی کلاینت
if ($path !== '/' && is_file($file)) {
    $mimes = [
        'js' => 'text/javascript; charset=utf-8',
        'css' => 'text/css; charset=utf-8',
        'woff2' => 'font/woff2',
        'png' => 'image/png',
        'ico' => 'image/x-icon',
        'svg' => 'image/svg+xml',
        'json' => 'application/json',
        'webmanifest' => 'application/manifest+json',
        'html' => 'text/html; charset=utf-8',
    ];

    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    header('Content-Type: '.$mimes[$ext] ?? 'application/octet-stream');

    $content = file_get_contents($file);

    $compressible = in_array($ext, ['js', 'css', 'json', 'svg', 'html', 'webmanifest'], true)
        && str_contains($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '', 'gzip');

    if ($compressible) {
        header('Content-Encoding: gzip');
        header('Vary: Accept-Encoding');
        echo gzencode($content, 9);
    } else {
        header('Content-Length: '.strlen($content));
        echo $content;
    }

    return true;
}

// بقیه → اپلیکیشن Laravel
require __DIR__.'/../../public/index.php';
