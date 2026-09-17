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
| Sprint 2 | Game Engine | ۳ هفته | ✅ تکمیل شد | Contractها، Session سمت سرور، Lucky Wheel، ضدتقلب |
| Sprint 3 | Reward و Coupon و Points | ۳ هفته | ✅ تکمیل شد | Resolver، Inventory، ۹ Issuer، Ledger امتیاز |
| Sprint 4 | بازی‌ها و PWA | ۳ هفته | 🔄 در حال انجام | ✅ ۱۰ بازی پلاگین (بک‌اند) — ⬜ PWA و پنل Wizard |
| Sprint 5 | Analytics و Retention | ۲ هفته | ⬜ در انتظار | قیف، Referral، Daily Check-in، Streak |
| Sprint 6 | امنیت و بتا | ۲ هفته | ⬜ در انتظار | ماتریس تست امنیتی، Audit، استقرار |

**آخرین به‌روزرسانی:** ۲۰۲۶-۰۹-۱۷ — Sprint 4 (فرانت‌اند، قدم ۲): PWA مشتری کامل شد — هر ۱۰ بازی با Registry و lazy-load، Overlay نتیجه، کیف جایزه‌ها؛ تست E2E مرورگری سبز

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

## Sprint 2 — هسته Game Engine و Campaign ✅

### دامنه Customer (پشتیبان)
- [x] Migration `customers` (موبایل یکتا در قلمرو هر Store، `referral_code`)
- [x] ورود مشتری با OTP: `POST /api/v1/c/{slug}/enter` → توکن محدود دامنه‌دار (guard مخصوص customer)
- [x] `GET /api/v1/c/{slug}` — جزئیات عمومی کمپین برای PWA (config امنِ عمومی بدون weight و reward_ref)
### Campaign Engine (هسته)
- [x] Migration: `campaigns` (slug، چرخه حیات، theme JSON)، `game_configurations`، `campaign_rules`، `game_sessions`، `campaign_participations`
- [x] ماشین حالت کمپین: Draft → Published → Expired → Archived + Event `CampaignPublished`
- [x] `CampaignRuleEngine`: قوانین مشارکت (روزی یک‌بار، سقف کل، فقط مشتری جدید) + ایندکس یکتا (customer, campaign, window)
- [x] CRUD کمپین: `GET/POST /campaigns`، `GET/PATCH /campaigns/{id}`، `POST /campaigns/{id}/publish`
- [x] سهمیه «کمپین فعال هم‌زمان» و Feature Gating بازی در Publish (QUOTA_EXCEEDED / PLAN_GAME_NOT_ALLOWED)
### Game Engine (هسته)
- [x] `GameRegistry` — نگاشت کد بازی ← پلاگین از `config/games.php` + اعتبارسنجی قرارداد
- [x] DTOها + امضای HMAC نتیجه (`ResultSigner`)
- [x] چرخه ۵ فازی Session سمت سرور (فصل ۵-۴): Start با توکن یک‌بارمصرف → اکشن → weighted RNG با `random_int` → واگذاری به Reward → ثبت + پاسخ امضاشده
- [x] مصرف اتمی توکن (`WHERE status = started` → Replay با `SESSION_CONSUMED` رد می‌شود)
- [x] `POST /play/sessions` (+ هدر Idempotency-Key) و `POST /play/sessions/{token}/action`
- [x] `GET /api/v1/games` و `GET /api/v1/games/{code}/config-schema` (فیلتر بر اساس Plan)
- [x] پلاگین **Lucky Wheel**: `configSchema` (segments وزنی)، `validateConfig`، weighted RNG، داده نمایش انیمیشن
- [x] نقطه واگذاری `RewardEngine::resolveForSession` (پایپ‌لاین کامل در Sprint 3)
### تست‌ها (Definition of Done)
- [x] تست توزیع احتمال (۱۰٬۰۰۰ اجرا، تلورانس ±۲ واحد درصد)
- [x] تست Replay — دو اکشن با همان توکن → رد
- [x] تست Daily Limit / سقف کل مشارکت
- [x] تست Cross-Tenant روی کمپین → 404
- [x] تست اعتبارسنجی Schema پیکربندی Wheel
- [x] تست نادیده‌گرفتن ورودی کلاینت در نتیجه (reward_id / force_win)
- [x] تست جریان کامل مشتری: کمپین عمومی → OTP → شروع → اکشن → نتیجه امضاشده

---

## Sprint 3 — Reward Engine و Coupon و Points ✅

### مدل داده (فصل ۳-۴)
- [x] Migration: `rewards` (۹ نوع، weight، سقف) + `reward_inventory` (موجودی جدا از تعریف)
- [x] Migration: `coupons` + `coupon_redemptions` (کد یکتا متصل به مشتری/Store)
- [x] Migration: `point_accounts` + `point_transactions` (الگوی Ledger دوطرفه، Append-Only)
### Reward Engine (فصل ۶)
- [x] خط لوله تصمیم: کاندیدها → فیلترها (فعال، سقف برد روزانه، موجودی) → انتخاب وزنی → صدور
- [x] قفل اتمی موجودی: `UPDATE ... WHERE remaining_qty > 0` داخل Transaction → در شکست، انتخاب وزنی روی باقیمانده تکرار می‌شود
- [x] ۹ Issuer با Registry: تخفیف درصدی، مبلغ ثابت، ارسال رایگان، محصول رایگان (CouponIssuer)، هدیه (GiftIssuer)، امتیاز (PointsIssuer)، کوپن فروشگاه + سفارشی (CustomIssuer)، بدون جایزه (NoneIssuer)
- [x] تولید کد کوپن: الفبای بدون ابهام (بدون 0/O/1/I)، طول ۸، Prefix برند
- [x] Ledger امتیاز: موجودی فقط از جمع تراکنش‌ها — هیچ کدی موجودی را مستقیم به‌روزرسانی نمی‌کند
- [x] Eventهای `RewardIssued` / `CouponCreated` / `CouponRedeemed` / `PointsEarned`
- [x] Fallback شفاف: بردی که بودجه نداشت → «بدون جایزه» در نتیجه Session
- [x] `GET/POST /api/v1/campaigns/{id}/rewards` + سقف تعداد جایزه بر اساس Plan
- [x] `POST /api/v1/coupons/redeem` — ثبت استفاده واقعی (حلقه ROI) با مصرف اتمی
- [x] `GET /api/v1/me/rewards` — کدها و امتیازهای مشتری
### تست‌ها (Definition of Done)
- [x] تست اتمی بودن کسر موجودی (۵ صدور با موجودی ۲ → دقیقاً ۲ صدور، هرگز منفی)
- [x] تست یکتایی و الگوی کد کوپن + انقضا + Redemption یک‌بارمصرف
- [x] تست تراز Ledger (موجودی = Σ تراکنش‌ها، +۵۰ +۳۰ −۲۰ = ۶۰)
- [x] تست Fallback «بدون جایزه» هنگام خاتم بودجه کمپین
- [x] تست واگذاری به جایزه جایگزین با انتخاب وزنی پس از اتمام موجودی

---

## Sprint 4 — بازی‌های باقیمانده و PWA 🔄

### بخش اول: بازی‌ها (بک‌اند) ✅
- [x] ۹ بازی باقیمانده به‌عنوان پلاگین مستقل `GameInterface`: Dice، Scratch Card، Pick a Box، Pick a Card، Lucky Ticket، Quiz، Memory، Reaction، Lucky Claw
- [x] کلاس پشتیبان `WeightedOutcome` (الگوی نتیجه‌اول) + پایه انتزاعی `PickOutcomeGame` برای بازی‌های انتخابی — برد/باخت همیشه weighted RNG سمت سرور و ایندکس انتخابی کلاینت فقط نمایشی (clamp)
- [x] Scratch: کارت کامل سمت سرور (نماد وزنی هر خانه با weighted RNG) + شرط حد نصاب (match_required) — سطح حمله ورودی کلاینت صفر
- [x] Quiz: تصحیح کامل سمت سرور (Server-Side Grading) — کلید پاسخ (`correct_index`) هرگز در پیکربندی عمومی افشا نمی‌شود
- [x] `toPublicArray` به حذف بازگشتی کلیدهای حساس (`weight` / `reward_ref` / `probability_mode` / `correct_index`) در هر عمق تبدیل شد — پوشش خودکار پلاگین‌های جدید بدون تغییر هسته (فصل ۸-۱)
- [x] ثبت هر ۱۰ بازی در `config/games.php` + تست E2E ساخت → انتشار → بازی برای هر ده بازی روی Plan حرفه‌ای
- [x] تست‌های Sprint 4 (۲۱ سناریو): Registry ده بازی، اعتبارسنجی Dice، تصحیح Quiz (برد/مردودی)، سازگاری کارت Scratch (۳۰۰ اجرا)، برجسته‌سازی Pick a Box (۲۰۰ اجرا)، کد بلیط Ticket، چیدمان دقیق board حافظه، جداسازی تصمیم Reaction، grabbed Claw، بی‌اثری ورودی دستکاری‌شده کلاینت — **مجموع ۸۷ تست سبز (۲,۲۹۳ assertion)**

### بخش دوم: فرانت‌اند 🔄
- [x] زیرساخت Vite + Vue 3 + Tailwind 4 با فونت self-host وزیرمتن (۴ وزن woff2) و RTL موبایل‌اول
- [x] کلاینت مشترک API (`resources/shared/api.js`): مدیریت خطای استاندارد `error{code,message,fields}`، توکن مشتری به‌ازای هر کمپین در localStorage (جلوگیری از نشت توکن بین Storeها)، هدر Idempotency-Key
- [x] App Shell PWA: `manifest.webmanifest` (RTL/فا/آیکون‌های ۱۹۲ و ۵۱۲ و maskable) + Service Worker سبک (cache-first برای شل و فونت‌ها؛ API هرگز کش نمی‌شود — نتایج بازی server-authoritative می‌مانند)
- [x] Blade host `/c/{slug}` + شل PWA: بارگذاری کمپین، اعمال Theme کمپین (CSS Variables)، ورود موبایل+OTP با اعتبارسنجی سمت کلاینت
- [x] Frontend Registry با `defineAsyncComponent` — هر ۱۰ بازی chunk جداگانه (~۱ تا ۱.۵KB gzip هرکدام) و main bundle فقط ~۳۲KB gzip (بودجه ۱۵۰KB)
- [x] جریان کامل ۱۰ بازی: Wheel (چرخش conic-gradient با فرود روی segment سرور)، Dice، Scratch (آشکارسازی تدریجی کارت سرور)، PickBox/PickCard (برجسته‌سازی reveal_index)، LuckyTicket، Quiz (فرم چندسؤالی + تصحیح سرور)، Memory (board سرور + چرخش محلی)، Reaction (زمان‌سنجی محلی + قضاوت سرور)، Claw (حرکت به target_x/y سرور)
- [x] Overlay نتیجه (برد: کد کوپن با کپی/امتیاز/هدیه با راهنما — بی‌جایزه: پیام محترمانه) + کیف جایزه‌ها (`/me/rewards`: کوپن‌ها با وضعیت و امتیاز Ledger)
- [x] تست E2E مرورگری (Playwright): ورود OTP → Session → چرخش → برد ۵۰ امتیاز → Overlay → کیف جایزه‌ها (موجودی ۵۰ از Ledger) بدون خطای کنسول
- [ ] پنل فروشگاه‌دار (Vue 3 + Tailwind): Wizard ساخت کمپین، Game Library با فرم پویا از JSON Schema، مدیریت جایزه
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
| 0 | `fd7ca08` | اسکلت Laravel 13 + ساختار Domain + قراردادها + TASKS.md | 2026-09-17 |
| 1 | `4dbec8e` | Identity و SaaS: OTP، Store، Plan، Subscription، FeatureGate (۳۱ تست) | 2026-09-17 |
| 2 | `a991122` | Campaign + Game Engine: Session سمت سرور، Wheel، ضدتقلب (۵۰ تست) | 2026-09-17 |
| 3 | `ae752a3` | Reward Engine + Coupon + Points Ledger (۶۶ تست) | 2026-09-17 |
| ci | `323ebcf` | فعال‌سازی GitHub Actions (Pint + تست روی PHP 8.4) پس از مجوز workflow scope | 2026-09-17 |
| 4 | `388fe98` | هر ۱۰ بازی MVP به‌صورت پلاگین + toPublicArray بازگشتی (۸۷ تست) | 2026-09-17 |
| ci | `fc4074c` | رفع CI: ساخت .env و APP_KEY پیش از تست (حذف warning dotenv و MissingAppKey) | 2026-09-17 |
