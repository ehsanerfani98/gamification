<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#6d28d9">
    <meta name="description" content="بازی کن، جایزه ببر!">
    <title>بازی و جایزه</title>
    <link rel="icon" type="image/png" href="/favicon.png">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="apple-touch-icon" href="/icons/icon-192x192.png">
    @vite('resources/pwa/main.js')
    {{-- App Shell — اولین رنگ‌آمیزی قبل از رسیدن CSS/JS (فصل ۹ سند: الگوی App Shell).
         داخل #app است تا Vue هنگام mount آن را جایگزین کند. --}}
    <style>
        .shell{position:fixed;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:14px;background:#faf5ff;font-family:system-ui,Tahoma,sans-serif}
        .shell__spinner{width:34px;height:34px;border-radius:50%;border:3px solid #ddd6fe;border-top-color:#7c3aed;animation:shell-spin .9s linear infinite}
        .shell__text{color:#6b7280;font-size:14px;margin:0}
        @keyframes shell-spin{to{transform:rotate(360deg)}}
    </style>
</head>
<body>
    <div id="app">
        <div class="shell" aria-hidden="true">
            <div class="shell__spinner"></div>
            <p class="shell__text">در حال بارگذاری…</p>
        </div>
    </div>
</body>
</html>
