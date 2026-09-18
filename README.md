# 🎮 پلتفرم گیمیفیکیشن SaaS — Game-as-a-Service

پیاده‌سازی MVP پلتفرم SaaS گیمیفیکیشن برای آنلاین‌شاپ‌ها و کسب‌وکارهای اینستاگرامی:
فروشگاه‌دار کمپین بازی‌محور می‌سازد، مشتری از اینستاگرام وارد می‌شود، بازی می‌کند و
کد تخفیف یا امتیاز می‌گیرد — و کل قیف تعامل قابل اندازه‌گیری است.

پیاده‌سازی دقیقاً بر اساس **سند معماری مصوب** (`Gamification_SaaS_Architecture_Design_FA.docx`) انجام می‌شود.
وضعیت مراحل و تسک‌ها: **[TASKS.md](TASKS.md)**

## سه ستون معماری (فصل‌های ۵ و ۶ و ۴ سند)

| موتور | مسئولیت | قاعده کلیدی |
|-------|----------|--------------|
| **Game Engine** | اجرای بازی‌ها به‌صورت پلاگین مستقل | افزودن بازی جدید = یک ماژول جدید + ثبت در Registry؛ بدون تغییر هسته |
| **Reward Engine** | تصمیم و صدور جایزه | تنها ورودی «نتیجه بازی» است؛ احتمال، موجودی و سقف‌ها فقط اینجا |
| **Campaign Engine** | چرخه حیات کمپین، قوانین مشارکت و سقف‌ها | انتشار، انقضا و Rule Engine داده‌محور |

**اصول امنیتی غیرقابل مذاکره:**
- نتیجه بازی فقط سمت سرور با `random_int` تولید می‌شود؛ کلاینت هیچ ورودی تصمیم‌گیری ندارد.
- هر Session توکن یک‌بارمصرف دارد؛ Replay با خطای `SESSION_CONSUMED` رد می‌شود.
- پاسخ نتیجه با HMAC امضا می‌شود.
- چنداجارگی با Global Scope اجباری (`BelongsToStore`) — دسترسی Cross-Tenant همیشه 404 است.
- عملیات‌های مالی با Transaction اتمی و Idempotency-Key.

## استک فنی

- **بک‌اند:** Laravel 13 · PHP 8.4 · Sanctum (توکن جدا برای Merchant و Customer)
- **دیتابیس:** SQLite (WAL) — طراحی آماده مهاجرت به PostgreSQL (فصل ۳-۵ سند)
- **صف و کش:** Database Queue + File/Database Cache (ارتقای بدون تغییر کد به Redis)
- **فرانت‌اند (Sprint 4+):** PWA موبایل‌اول RTL — Vue 3 / Alpine.js + Tailwind + Vite
- **API نسخه‌دار:** `/api/v1`

## راه‌اندازی محلی

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed     # سه Plan پیش‌فرض + بازی‌های MVP
php artisan serve              # http://localhost:8000
```

> در حالت توسعه، درایور پیامک `log` است و کد OTP در `storage/logs/laravel.log` ثبت می‌شود.

## تست‌ها

```bash
php artisan test               # کل Suite
php artisan test --filter=OtpAuth      # فقط احراز هویت
php artisan test --filter=WheelProbabilityTest  # توزیع احتمال (۱۰٬۰۰۰ اجرا)
```

هر اسپرینت تست‌های اجباری خود را دارد: Tenant Isolation (404)، Replay (SESSION_CONSUMED)،
توزیع احتمال (±۲ واحد درصد)، تراز Ledger و اتمی بودن موجودی. ماتریس کامل ۷ سناریوی
حمله امنیتی در `tests/Feature/Security/SecurityMatrixTest.php` است.

## راهنمای استقرار (Sprint 6)

```bash
# ۱) کد و وابستگی‌ها
composer install --no-dev --optimize-autoloader
npm ci && npm run build

# ۲) پیکربندی
cp .env.example .env && php artisan key:generate --force
# APP_ENV=production، APP_DEBUG=false — درایورهای SMS/Payment با env تعویض می‌شوند
php artisan migrate --force

# ۳) بهینه‌سازی
php artisan config:cache && php artisan route:cache && php artisan event:cache

# ۴) Worker صف (Supervisor) — QUEUE_CONNECTION=database
php artisan queue:work --tries=3 --backoff=5

# ۵) زمان‌بندی (cron هر دقیقه) — تجمیع Analytics، بستن کمپین منقضی،
#    انقضای Session و بکاپ روزانه همه خودکار اجرا می‌شوند
* * * * * php /path/to/artisan schedule:run
```

**پایگاه‌داده:** SQLite با WAL و busy_timeout (از env: `DB_JOURNAL_MODE=WAL`).
بکاپ یکپارچه با `php artisan database:backup` (VACUUM INTO، نگه‌داری ۱۴ نسخه،
زمان‌بندی ۰۴:۰۰) — بدون توقف سرویس. مهاجرت به PostgreSQL بدون تغییر کد
Domain انجام می‌شود (فصل ۳-۵ سند).

**وضعیت درایورهای تولیدی:** پیامک و دروازه پرداخت قرارداد تعویض‌پذیر دارند
(`app/Infrastructure/*`). درایورهای واقعی از Sprint 7 آماده‌اند:
**IPPanel** (`SMS_CHANNEL=ippanel` + `IPANEL_API_KEY`/`IPANEL_ORIGINATOR`) و
**ZarinPal v4** (`PAYMENT_GATEWAY=zarinpal` + `ZARINPAL_MERCHANT_ID`) —
کلیدها را فقط در env سرور تنظیم کنید.

## حالت سندباکس و بتای ۵ فروشگاه (Sprint 8)

برای بتا بدون کلید واقعی، مدیر سایت دو سندباکس را از پنل روشن می‌کند:

1. کاربر مدیر را ارتقا دهید (یک‌بار با OTP وارد پنل شده باشد):
   `php artisan admin:promote 09xxxxxxxxx` → خارج/ورود مجدد پنل
2. در پنل → **تنظیمات سایت** (منوی فقط-Admin):
   - **📱 سندباکس پیامک**: OTP واقعاً ارسال نمی‌شود؛ کد در پاسخ API برمی‌گردد و جریان ورود تست می‌شود
   - **💳 سندباکس دروازه پرداخت**: به‌جای ZarinPal صفحه پرداخت آزمایشی داخلی (`/payments/sandbox/{id}`) باز می‌شود؛
     کل مسیر callback → verify → فعال‌سازی اشتراک → فاکتور → Audit دقیقاً مثل دروازه واقعی اجرا می‌شود
3. پایان بتا: هر دو کلید خاموش و کلیدهای واقعی در env قرار می‌گیرد — بدون هیچ تغییر کد.

وضعیت سندباکس هر پرداخت در `payments.meta.sandbox` و تغییر تنظیمات در
Audit (`site.settings_updated`) ثبت می‌شود.

## ساختار کد (Domain-Oriented — فصل ۴ سند)

```
app/
├── Domain/                     ← دامنه‌ها (منطق کسب‌وکار)
│   ├── Game/                   ← هسته: Contracts، DTO، Registry
│   │   └── Plugins/            ← Wheel/ ، Dice/ ، Quiz/ ، ... (هر بازی یک پکیج)
│   ├── Reward/                 ← هسته: Pipeline، Issuers، Inventory
│   ├── Campaign/               ← هسته: Lifecycle، Rules، Limits
│   ├── Subscription/           ← Plan، اشتراک، FeatureGate
│   ├── Customer/  Points/  Coupon/  Analytics/  Referral/
│   ├── Authentication/  Merchant/  Notification/
├── Application/                ← Use Caseها و Orchestratorها (نازک)
├── Infrastructure/             ← درایورهای بیرونی: Sms/ ، Payment/
├── Http/Controllers/Api/V1     ← Controllerهای /api/v1 + Middleware
├── Models/                     ← Eloquent Models (لایه persistence)
└── Support/                    ← Tenancy، ابزارها و Helperهای مشترک
```

**قاعده وابستگی:** دامنه‌ها فقط از طریق **Contract** و **Domain Event** با هم صحبت می‌کنند.
فراخوانی مستقیم کلاس داخلی دامنه دیگر یا پرس‌وجوی Cross-Domain ممنوع است.

## جریان پیاده‌سازی

نقشه راه هفت‌اسپرینتی (فصل ۱۰): Sprint 0 بستر → 1 Identity/SaaS → 2 Game Engine →
3 Reward/Campaign → 4 بازی‌ها و PWA → 5 Analytics → 6 امنیت و بتا.
جزئیات و پیشرفت لحظه‌ای در **[TASKS.md](TASKS.md)** ثبت می‌شود.
