# 📋 مراحل و تسک‌های پیاده‌سازی — پلتفرم گیمیفیکیشن SaaS

> **مرجع:** سند معماری `Gamification_SaaS_Architecture_Design_FA.docx` (فصل ۱۰ — نقشه راه)
> **ریپو:** `github.com/ehsanerfani98/gamification`
> **استک:** Laravel 13 · PHP 8.4 · SQLite (WAL) · Sanctum · Vue 3 + Tailwind + Vite (از Sprint 4)
> **قاعده به‌روزرسانی:** پس از پایان هر مرحله، این فایل به‌روز شده و با همان commit پوش می‌شود.

---

## 📊 وضعیت کلی فازها

| فاز | عنوان | بازه | وضعیت | خروجی کلیدی |
|-----|-------|------|--------|--------------|
| Sprint 0 | بستر و اسکلت پروژه | ۱ هفته | ✅ تکمیل شد | اسکلت Laravel 13، ساختار Domain، قراردادها، CI |
| Sprint 1 | Identity و SaaS | ۲ هفته | ✅ تکمیل شد | OTP، Merchant/Store، Plan، Subscription، Feature Gating |
| Sprint 2 | Game Engine | ۳ هفته | 🔄 در حال انجام | Contractها، Session سمت سرور، Lucky Wheel، ضدتقلب |
| Sprint 3 | Reward و Coupon و Points | ۳ هفته | ⬜ در انتظار | Resolver، Inventory، ۹ Issuer، Ledger امتیاز |
| Sprint 4 | بازی‌ها و PWA | ۳ هفته | ⬜ در انتظار | ۹ بازی باقیمانده + PWA + پنل Wizard |
| Sprint 5 | Analytics و Retention | ۲ هفته | ⬜ در انتظار | قیف، Referral، Daily Check-in، Streak |
| Sprint 6 | امنیت و بتا | ۲ هفته | ⬜ در انتظار | ماتریس تست امنیتی، Audit، استقرار |

**آخرین به‌روزرسانی:** ۲۰۲۶-۰۹-۱۷ — پایان Sprint 1 (Identity و SaaS کامل شد)

---

## Sprint 0 — بستر و اسکلت پروژه ✅

- [x] نصب Laravel 13 (PHP 8.4) + Sanctum (`install:api`)
- [x] SQLite با WAL و busy_timeout — قاعده مهاجرت PostgreSQL (فصل ۳-۵)
- [x] ساختار پوشه‌های Domain-Oriented مطابق فصل ۴ (هسته: Game/Reward/Campaign + پشتیبان + عمومی)
- [x] قرارداد `GameInterface` دقیقاً مطابق فصل ۵-۲ (metadata, configSchema, validateConfig, startSession, resolveResult, remainingPlays)
- [x] DTOهای استاندارد: `GameMetadata`، `GameResult` (win / no_reward)، `PlayerAction`
- [x] قرارداد `RewardIssuerInterface` + DTO `IssuanceResult` (۹ نوع جایزه فصل ۶)
- [x] درایورهای تعویض‌پذیر: `SmsChannel` (+LogSmsChannel)، `PaymentGateway` (+FakeGateway)
- [x] لایه Tenancy: `BelongsToStore` (Global Scope اجباری) + `TenantContext`
- [x] `config/gamification.php` (سقف‌های OTP، الگوریتم کد کوپن، HMAC) و `config/games.php` (Game Registry + دسته‌ها)
- [x] CI با GitHub Actions: Pint + `php artisan test`
- [x] `TASKS.md` و `README.md`

**Definition of Done:** ✅ Pipeline سبز + Migration اولیه + ساختار دامنه‌ها قفل‌شده

---

## Sprint 1 — دامنه Identity و SaaS ✅

### احراز هویت (Authentication)
- [x] Migration `otp_codes`: هش امن کد، `purpose`، سقف تلاش، انقضا، `consumed_at`
- [x] `POST /api/v1/auth/otp/request` — نرخ ۳/ساعت به‌ازای هر شماره + ۲۰/ساعت هر IP (فصل ۸-۳)
- [x] `POST /api/v1/auth/otp/verify` — تأیید کد و صدور Sanctum Token (کاربر جدید خودکار ساخته می‌شود)
- [x] ثبت Audit Log برای رخدادهای ورود
### Store و Tenancy
- [x] Migration `stores` (slug یکتا، status) + Model با `BelongsToStore`
- [x] `TenantContext` Middleware + خطای 404 برای دسترسی Cross-Tenant
- [x] `GET/POST /api/v1/stores` + Policy مالکیت
### SaaS و Feature Gating (فصل ۷)
- [x] Migration: `plans` (features JSON)، `plan_game`، `subscriptions`، `payments`، `invoices`
- [x] Migration: `games` + `game_categories` (ریجستری خالی برای Sprint 2)
- [x] Seeder سه Plan: رایگان / پایه / حرفه‌ای (سقف‌ها و بازی‌های جدول فصل ۷)
- [x] سرویس `FeatureGate` — تنها مرجع پاسخ به «این Store الان چه مجازی دارد؟»
- [x] `GET /api/v1/plans`، `GET/POST /api/v1/subscriptions`، `POST /api/v1/payments/callback`
- [x] چرخه حیات اشتراک: Active → Past Due → Expired → Canceled + Event `SubscriptionChanged`
### تست‌ها (Definition of Done)
- [x] سناریوی E2E: ثبت‌نام OTP → ساخت Store → خرید اشتراک → شارژ کیف پول
- [x] تست Tenant Isolation (توکن فروشگاه A روی منبع فروشگاه B → 404)
- [x] تست Rate Limit و انقضا و سقف تلاش OTP

---

## Sprint 2 — هسته Game Engine و Campaign 🔄

### دامنه Customer (پشتیبان)
- [ ] Migration `customers` (موبایل یکتا در قلمرو هر Store، `referral_code`)
- [ ] ورود مشتری با OTP: `POST /api/v1/c/{slug}/enter` → توکن محدود دامنه‌دار
- [ ] `GET /api/v1/c/{slug}` — جزئیات عمومی کمپین برای PWA (config امنِ عمومی)
### Campaign Engine (هسته)
- [ ] Migration: `campaigns` (slug، چرخه حیات، theme JSON)، `game_configurations`، `campaign_rules`، `campaign_participations`
- [ ] ماشین حالت کمپین: Draft → Published → Expired → Archived + Event `CampaignPublished`
- [ ] `CampaignRuleEngine`: قوانین مشارکت (روزی یک‌بار، سقف کل، فقط مشتری جدید) + ایندکس یکتا (customer, campaign, window)
- [ ] CRUD کمپین: `GET/POST /campaigns`، `GET/PATCH /campaigns/{id}`، `POST /campaigns/{id}/publish`
### Game Engine (هسته)
- [ ] `GameRegistry` — نگاشت کد بازی ← پلاگین از `config/games.php` + اعتبارسنجی قرارداد
- [ ] DTOها + امضای HMAC نتیجه (`ResultSigner`)
- [ ] چرخه ۵ فازی Session سمت سرور (فصل ۵-۴): Start با توکن یک‌بارمصرف → اکشن → weighted RNG با `random_int` → واگذاری به Reward → ثبت + پاسخ امضاشده
- [ ] مصرف اتمی توکن (`WHERE status = started` → Replay با `SESSION_CONSUMED` رد می‌شود)
- [ ] `POST /play/sessions` (+ هدر Idempotency-Key) و `POST /play/sessions/{token}/action`
- [ ] `GET /api/v1/games` و `GET /api/v1/games/{code}/config-schema` (فیلتر بر اساس Plan)
- [ ] پلاگین **Lucky Wheel**: `configSchema` (segments وزنی)، `validateConfig`، weighted RNG، داده نمایش انیمیشن
### تست‌ها (Definition of Done)
- [ ] تست توزیع احتمال (۱۰٬۰۰۰ اجرا، تلورانس ±۲ واحد درصد)
- [ ] تست Replay — دو اکشن با همان توکن → رد
- [ ] تست Daily Limit / سقف کل مشارکت
- [ ] تست Cross-Tenant روی کمپین → 404
- [ ] تست اعتبارسنجی Schema پیکربندی Wheel

---

## Sprint 3 — Reward Engine و Coupon و Points ⬜

### مدل داده (فصل ۳-۴)
- [ ] Migration: `rewards` (۹ نوع، weight، سقف)، `reward_inventory` (موجودی جدا از تعریف)
- [ ] Migration: `coupons` + `coupon_redemptions` (کد یکتا متصل به مشتری/Store)
- [ ] Migration: `point_accounts` + `point_transactions` (الگوی Ledger دوطرفه)
### Reward Engine (فصل ۶)
- [ ] خط لوله تصمیم: کاندیداها → ۴ فیلتر (فعال، سقف کاربر، سقف نرخ برد، موجودی) → انتخاب وزنی → صدور
- [ ] قفل اتمی موجودی: `UPDATE ... WHERE remaining_qty > 0` داخل Transaction → در شکست، انتخاب وزنی روی باقیمانده تکرار می‌شود
- [ ] ۹ Issuer: تخفیف درصدی، مبلغ ثابت، ارسال رایگان، محصول رایگان، هدیه، امتیاز، کوپن فروشگاه، سفارشی، بدون جایزه
- [ ] تولید کد کوپن: الفبای بدون ابهام (بدون 0/O/1/I)، طول ۸، Prefix برند
- [ ] Ledger امتیاز: موجودی فقط از جمع تراکنش‌های امضاشده — هیچ کدی موجودی را مستقیم به‌روزرسانی نمی‌کند
- [ ] Eventهای `RewardIssued` / `CouponCreated` (Analytics و Notification بدون وابستگی مستقیم)
- [ ] `GET /api/v1/me/rewards` — کدها و امتیازهای مشتری
- [ ] اتصال نقطه واگذاری Session → Reward Engine در Game Engine (پلاگین‌ها هرگز مستقیم جایزه نمی‌سازند)
### تست‌ها (Definition of Done)
- [ ] تست اتمی بودن کسر موجودی (دو صدور هم‌زمان → هرگز بیش از موجودی)
- [ ] تست یکتایی کد کوپن + انقضا + Redemption
- [ ] تست تراز Ledger (موجودی = Σ تراکنش‌ها)
- [ ] تست Fallback «بدون جایزه» هنگام خاتم بودجه کمپین
- [ ] تست سقف برد روزانه کاربر (فیلترهای موتور)

---

## Sprint 4 — بازی‌های باقیمانده و PWA ⬜

- [ ] ۹ بازی باقیمانده به‌عنوان پلاگین مستقل: Dice، Scratch Card، Pick a Box، Pick a Card، Lucky Ticket، Quiz، Memory، Reaction، Lucky Claw
- [ ] Frontend Registry و Lazy-load کامپوننت هر بازی
- [ ] PWA کمپین (مشتری): Alpine/Vue سبک، RTL موبایل‌اول، App Shell + Service Worker، صفحه آفلاین
- [ ] پنل فروشگاه‌دار (Vue 3 + Tailwind): Wizard شش‌مرحله‌ای ساخت کمپین، Game Library، مدیریت جایزه
- [ ] بودجه عملکرد: LCP < 2.5s، JS لندینگ < 150KB فشرده، TTI < 3s (Lighthouse CI)

**Definition of Done:** ۱۰ بازی روی Game Engine + اجرا در WebView اینستاگرام

## Sprint 5 — Analytics و Retention ⬜

- [ ] `analytics_events` (Append-Only) + قیف View → Enter → Play → Win → Redeem
- [ ] `GET /api/v1/campaigns/{id}/analytics` — شاخص‌ها و قیف کمپین
- [ ] Referral: کد دعوت، جایزه پله‌ای ۱/۳/۵ دعوت
- [ ] Daily Check-in و Streak (`POST /api/v1/daily/checkin`)
- [ ] تجمیع شبانه و پاک‌سازی جدول رخدادها

## Sprint 6 — امنیت و بتا ⬜

- [ ] `audit_logs` کامل + ثبت همه رخدادهای حساس (ورود، Start Session، صدور جایزه، تغییر اشتراک)
- [ ] اجرای کامل ماتریس تست امنیتی فصل ۱۰ (۷ سناریوی حمله)
- [ ] Scheduler: بستن کمپین منقضی، انقضای Sessionها، قرعه‌کشی زمان‌دار
- [ ] درایور پیامک واقعی + دروازه پرداخت واقعی (تصمیم‌های باز زیر)
- [ ] آماده‌سازی استقرار (WAL حداکثری، صف Database، بکاپ) + بتای ۵ فروشگاه

---

## 📌 تصمیم‌های باز (پیش از استقرار نهایی شوند)

| تصمیم | وضعیت | توضیح |
|--------|--------|-------|
| سرویس پیامک OTP | ⬜ باز | Contract آماده در `app/Infrastructure/Sms` — فعلاً LogSmsChannel (کد در لاگ) |
| دروازه پرداخت | ⬜ باز | Contract آماده در `app/Infrastructure/Payment` — فعلاً FakeGateway |
| سقف‌های عددی Planها | ⬜ باز | پیش‌فرض Seeder مطابق جدول فصل ۷ سند معماری |

## 📝 گزارش تغییرات Git

| Sprint | Commit | شرح | تاریخ |
|--------|--------|------|-------|
| 0 | — | اسکلت Laravel 13 + ساختار Domain + قراردادها + CI + TASKS.md | 2026-09-17 |
