<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0f172a">
    <title>پنل فروشگاه‌دار | گیمیفیکیشن</title>
    <link rel="icon" type="image/png" href="/favicon.png">
    @vite('resources/panel/main.js')
    {{-- App Shell — اولین رنگ‌آمیزی قبل از رسیدن CSS/JS (فصل ۹ سند: الگوی App Shell).
         داخل #app است تا Vue هنگام mount آن را جایگزین کند. --}}
    <style>
        .shell{position:fixed;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:14px;background:#f8fafc;font-family:system-ui,Tahoma,sans-serif}
        .shell__spinner{width:34px;height:34px;border-radius:50%;border:3px solid #c7d2fe;border-top-color:#4f46e5;animation:shell-spin .9s linear infinite}
        .shell__text{color:#6b7280;font-size:14px;margin:0}
        @keyframes shell-spin{to{transform:rotate(360deg)}}
    </style>
</head>
<body>
    <div id="app">
        <div class="shell" aria-hidden="true">
            <div class="shell__spinner"></div>
            <p class="shell__text">در حال بارگذاری پنل…</p>
        </div>
    </div>
</body>
</html>
