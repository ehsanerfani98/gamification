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
| Sprint 4 | بازی‌ها و PWA | ۳ هفته | ✅ تکمیل شد | ۱۰ بازی پلاگین + PWA مشتری + پنل فروشگاه‌دار با Wizard |
| Sprint 5 | Analytics و Retention | ۲ هفته | ✅ تکمیل شد | قیف، Endpoint گزارش، Referral پله‌ای، Check-in/Streak، تجمیع شبانه |
| Sprint 6 | امنیت و بتا | ۲ هفته | ✅ تکمیل شد | Audit کامل، ماتریس ۷ حمله، Scheduler، بکاپ، راهنمای استقرار |
| Sprint 7 | یکپارچه‌سازی سرویس‌های واقعی | ۱ هفته | ✅ تکمیل شد | درایور IPPanel (پیامک OTP) + دروازه ZarinPal (API v4) + callback مرورگر |
| Sprint 8 | سندباکس و شروع بتا | ۱ هفته | ✅ تکمیل شد (کد) + 🚀 بتا | تنظیمات سایت (فقط Admin) با سندباکس پیامک/پرداخت + admin:promote |

**آخرین به‌روزرسانی:** ۲۰۲۶-۰۹-۱۸ — بستن تسک‌های باز: بودجه عملکرد Sprint 4 (Lighthouse CI + بودجه JS + App Shell) و ابزار اجرای بتا (`demo:seed`، `beta:status`، راهنمای Onboarding، فرم بازخورد) — **۱۶۴ تست سبز (۲,۷۵۱ assertion)** + جاب Lighthouse در CI

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

## Sprint 4 — بازی‌ها، PWA و پنل فروشگاه‌دار ✅

### بخش اول: بازی‌ها (بک‌اند) ✅
- [x] ۹ بازی باقیمانده به‌عنوان پلاگین مستقل `GameInterface`: Dice، Scratch Card، Pick a Box، Pick a Card، Lucky Ticket، Quiz، Memory، Reaction، Lucky Claw
- [x] کلاس پشتیبان `WeightedOutcome` (الگوی نتیجه‌اول) + پایه انتزاعی `PickOutcomeGame` برای بازی‌های انتخابی — برد/باخت همیشه weighted RNG سمت سرور و ایندکس انتخابی کلاینت فقط نمایشی (clamp)
- [x] Scratch: کارت کامل سمت سرور (نماد وزنی هر خانه با weighted RNG) + شرط حد نصاب (match_required) — سطح حمله ورودی کلاینت صفر
- [x] Quiz: تصحیح کامل سمت سرور (Server-Side Grading) — کلید پاسخ (`correct_index`) هرگز در پیکربندی عمومی افشا نمی‌شود
- [x] `toPublicArray` به حذف بازگشتی کلیدهای حساس (`weight` / `reward_ref` / `probability_mode` / `correct_index`) در هر عمق تبدیل شد — پوشش خودکار پلاگین‌های جدید بدون تغییر هسته (فصل ۸-۱)
- [x] ثبت هر ۱۰ بازی در `config/games.php` + تست E2E ساخت → انتشار → بازی برای هر ده بازی روی Plan حرفه‌ای
- [x] تست‌های Sprint 4 (۲۱ سناریو): Registry ده بازی، اعتبارسنجی Dice، تصحیح Quiz (برد/مردودی)، سازگاری کارت Scratch (۳۰۰ اجرا)، برجسته‌سازی Pick a Box (۲۰۰ اجرا)، کد بلیط Ticket، چیدمان دقیق board حافظه، جداسازی تصمیم Reaction، grabbed Claw، بی‌اثری ورودی دستکاری‌شده کلاینت — **مجموع ۸۷ تست سبز (۲,۲۹۳ assertion)**

### بخش دوم: فرانت‌اند ✅
- [x] زیرساخت Vite + Vue 3 + Tailwind 4 با فونت self-host وزیرمتن (۴ وزن woff2) و RTL موبایل‌اول
- [x] کلاینت مشترک API (`resources/shared/api.js`): مدیریت خطای استاندارد `error{code,message,fields}`، توکن مشتری به‌ازای هر کمپین در localStorage (جلوگیری از نشت توکن بین Storeها)، هدر Idempotency-Key
- [x] App Shell PWA: `manifest.webmanifest` (RTL/فا/آیکون‌های ۱۹۲ و ۵۱۲ و maskable) + Service Worker سبک (cache-first برای شل و فونت‌ها؛ API هرگز کش نمی‌شود — نتایج بازی server-authoritative می‌مانند)
- [x] Blade host `/c/{slug}` + شل PWA: بارگذاری کمپین، اعمال Theme کمپین (CSS Variables)، ورود موبایل+OTP با اعتبارسنجی سمت کلاینت
- [x] Frontend Registry با `defineAsyncComponent` — هر ۱۰ بازی chunk جداگانه (~۱ تا ۱.۵KB gzip هرکدام) و main bundle فقط ~۳۲KB gzip (بودجه ۱۵۰KB)
- [x] جریان کامل ۱۰ بازی: Wheel (چرخش conic-gradient با فرود روی segment سرور)، Dice، Scratch (آشکارسازی تدریجی کارت سرور)، PickBox/PickCard (برجسته‌سازی reveal_index)، LuckyTicket، Quiz (فرم چندسؤالی + تصحیح سرور)، Memory (board سرور + چرخش محلی)، Reaction (زمان‌سنجی محلی + قضاوت سرور)، Claw (حرکت به target_x/y سرور)
- [x] Overlay نتیجه (برد: کد کوپن با کپی/امتیاز/هدیه با راهنما — بی‌جایزه: پیام محترمانه) + کیف جایزه‌ها (`/me/rewards`: کوپن‌ها با وضعیت و امتیاز Ledger)
- [x] تست E2E مرورگری (Playwright): ورود OTP → Session → چرخش → برد ۵۰ امتیاز → Overlay → کیف جایزه‌ها (موجودی ۵۰ از Ledger) بدون خطای کنسول
- [x] پنل فروشگاه‌دار (Vue 3 + Tailwind، hash-router): ورود OTP، انتخاب Store با `X-Store-Id`، داشبورد، کتابخانه بازی با نشان Plan و مشاهده Schema، Wizard دو مرحله‌ای ساخت کمپین (مشخصات → فرم پویا از JSON Schema)، صفحه جزئیات کمپین (ویرایش پیکربندی، تعریف ۹ نوع جایزه با موجودی، انتشار و کپی لینک عمومی)، ثبت استفاده کوپن، اشتراک و Plan
- [x] بودجه عملکرد (فصل ۹): LCP < 2.5s و TTI < 3s با **Lighthouse CI** (جاب `performance` در GitHub Actions — روی `/c/demo-wheel` و `/panel`، نسخه پین‌شده `@lhci/cli@0.15.1`)؛ JS لندینگ < 150KB فشرده با `scripts/perf/bundle-budget.mjs` (گراف import استاتیک هر Entry از manifest Vite → PWA: ۳۲.۶KB، پنل: ۴۸.۶KB gzip)
  - بهبود واقعی LCP: App Shell در Blade hostها (رنگ‌آمیزی اول قبل از CSS/JS — الگوی فصل ۹) + سرور gzip شبیه production در جاب اندازه‌گیری (`scripts/perf/server.php`)
  - نتایج اندازه‌گیری: LCP ≤ ۲.۳s و TTI ≤ ۲.۳s و امتیاز Performance ≥ ۰.۹۸ (TBT = 0 و CLS = 0)
  - پیش‌نیاز اندازه‌گیری: دستور `demo:seed` (دموی Idempotent کمپین demo-wheel با جایزه کوپن/امتیاز — تأیید E2E مرورگری: ورود → چرخش → برد → کد کوپن)

**Definition of Done:** ۱۰ بازی روی Game Engine + اجرا در WebView اینستاگرام → **بخش PWA تأیید شد با تست E2E مرورگری (OTP → Session → چرخش → برد امتیاز → کیف جایزه‌ها) و جریان کامل پنل (OTP → Wizard → جایزه → انتشار)**

## Sprint 5 — Analytics و Retention ✅

- [x] `analytics_events` (Append-Only) + قیف View → Enter → Play → Win → Redeem
  - جدول Append-Only با مسدودسازی updating در مدل؛ پاک‌سازی فقط از طریق دستور تجمیع
  - ارتباط Cross-Domain فقط با Domain Event: `CampaignViewed`، `CustomerEnteredCampaign` (رویدادهای جدید) + `GamePlayed`، `CouponRedeemed` (موجود)
  - Tracker مستقل با منطق «شکست Analytics هرگز جریان اصلی را نمی‌شکند» (try/catch + Log)
  - «برد» فقط برای نتیجه win نهایی شمرده می‌شود — برد بدون بودجه (Fallback) در win ثبت نمی‌شود
- [x] `GET /api/v1/campaigns/{id}/analytics` — قیف کمپین، نرخ تبدیل هر گام، مشتری یکتا و سری زمانی ۳۰ روزه
  - نرخ تبدیل صفر در حالت بدون داده (بدون تقسیم بر صفر)؛ ایزوله‌سازی Tenant (Cross-Tenant → 404)
- [x] Referral: کد دعوت قلمرو Store، جایزه پله‌ای ۱/۳/۵ دعوت
  - `POST /api/v1/referrals/apply` + کد دعوت اختیاری در `POST /c/{slug}/enter`
  - دعوت نامعتبر هرگز ورود را نمی‌شکند (اصطکاک صفر ورود — فصل ۹-۱)؛ مسیر صریح خطا با REFERRAL_INVALID 422
  - دعوت خودی رد، ثبت تکراری Idempotent (already)، هر invited فقط یک‌بار (unique)
  - جوایز فقط از طریق Ledger با مرجع رکورد دعوت + Eventهای `ReferralRegistered` و `ReferralRewardGranted`
- [x] Daily Check-in و Streak (`POST /api/v1/daily/checkin`)
  - امتیاز پایه ۱۰ + پاداش آستانه زنجیره ۷/۱۴/۳۰ روز (۵۰/۱۰۰/۲۰۰) — همه از Ledger
  - چک‌این تکراری همان روز Idempotent؛ شکست زنجیره → ریست به ۱؛ پیکربندی در `config/gamification.php`
- [x] تجمیع شبانه و پاک‌سازی جدول رخدادها
  - دستور `analytics:aggregate` — بازمحاسبه Idempotent `campaign_daily_stats` (کمپین × روز) + حذف رخدادهای قدیمی‌تر از ۹۰ روز
  - زمان‌بندی ۰۳:۰۰ در Scheduler؛ مقایسه امن تاریخ با whereDate (سازگار SQLite/PostgreSQL)
- [x] تست‌ها (۲۴ سناریوی جدید): جریان کامل قیف از API واقعی، برد بدون بودجه در win شمرده نمی‌شود، ایزوله‌سازی Tenant گزارش، صفر بدون تقسیم بر صفر، پله‌های ۱/۳/۵، تکرار Idempotent، دعوت خودی/نامعتبر/Cross-Store، ورود بدون شکست با کد نامعتبر، Streak پیوسته/پاداش/ریست، تجمیع دو روزه، Idempotent بودن دستور، حذف دوره نگهداری، رخداد بدون کمپین — **مجموع ۱۱۸ تست سبز (۲,۵۱۷ assertion)**

## Sprint 6 — امنیت و بتا 🔶 (سهم کد تکمیل شد؛ دو تصمیم باز پیش از بتا)

- [x] `audit_logs` کامل + ثبت همه رخدادهای حساس
  - ورود (`auth.login` — موجود) + تلاش ناموفق OTP + شروع Session (`game.session_started`)
  - نتیجه بازی (`game.played`) و صدور جایزه (`reward.issued` — Listener روی `RewardIssued`)
  - تغییر اشتراک (`subscription.changed` — Listener روی `SubscriptionChanged`)
  - انتشار کمپین (`campaign.published`)، استفاده کوپن (`coupon.redeemed`)، جایزه دعوت (`referral.rewarded`)
  - پشتیبانی از actor_type (merchant/customer/system) در `AuditLog::record` — Append-Only
- [x] اجرای کامل ماتریس تست امنیتی فصل ۱۰ — ۷ سناریو در `SecurityMatrixTest`:
  ۱) Replay (SESSION_CONSUMED) ۲) دستکاری Payload کلاینت (نتیجه فقط سمت سرور) ۳) امضای HMAC (باطل‌سازی دستکاری) ۴) Brute Force OTP (Rate Limit + قفل تلاش + Audit) ۵) Cross-Tenant (404 بدون افشا روی ۵ Endpoint) ۶) بالا رفتن سطح دسترسی (توکن مشتری/فروشگاه‌دار/ناشناس) ۷) حدس کوپن (نبود نشت اطلاعات + تک‌مصرف اتمی)
- [x] Scheduler: بستن کمپین منقضی (`campaigns:close-expired` هر ۱۰ دقیقه)، انقضای Session (`sessions:expire` هر ۵ دقیقه) — هر دو Idempotent با ریست TenantContext (قرعه‌کشی زمان‌دار پس از تعریف نوع بازی قرعه — خارج از MVP)
- [x] آماده‌سازی استقرار: دستور `database:backup` (VACUUM INTO، نگه‌داری ۱۴ نسخه، زمان‌بندی ۰۴:۰۰)، QUEUE_CONNECTION=database، راهنمای کامل استقرار در README (cache، worker صف، cron)
- [x] ~~درایور پیامک واقعی + دروازه پرداخت واقعی~~ — ✅ در Sprint 7 انجام شد (IPPanel + ZarinPal با انتخاب کاربر)
- [x] ~~بتای ۵ فروشگاه~~ — 🚀 در Sprint 8 شروع شد (سندباکس + تنظیمات سایت)

---

## Sprint 7 — یکپارچه‌سازی سرویس‌های واقعی ✅ (IPPanel + ZarinPal — انتخاب کاربر)

### پیامک OTP با IPPanel (فراز اس‌ام‌اس)
- [x] درایور `IppanelSmsChannel` روی قرارداد موجود `SmsChannel` — بدون تغییر در Actionها
  - REST `POST /api/v1/sms/send/webservice/single` با هدر `apikey` (base_url قابل پیکربندی)
  - Exception در خطا → صف `SendSmsJob` با tries=3 و backoff پلکانی (۵/۳۰/۱۲۰ ثانیه) Retry می‌کند
- [x] بایند پویا با env: `SMS_CHANNEL=log|ippanel` — رفتار پیش‌فرض (log) حفظ شد
- [x] پیکربندی: `IPANEL_API_KEY`، `IPANEL_ORIGINATOR`، `IPANEL_BASE_URL`
### پرداخت اشتراک با ZarinPal (API v4)
- [x] درایور `ZarinpalGateway` روی قرارداد موجود `PaymentGateway` — بدون تغییر در Actionها
  - `request()`: `POST /pg/v4/payment/request.json` → Authority + URL پرداخت `StartPay`
  - `verify()`: `POST /pg/v4/payment/verify.json` — تأیید فقط سمت سرور؛ کد ۱۰۱ (قبلاً تأیید) Idempotent موفق
  - دفاع‌ها: تطبیق Authority با reference رکورد (hash_equals)، رد NOK بدون تماس API، تبدیل تومان→ریال (قابل غیرفعال‌سازی)
- [x] `GET /api/v1/payments/zarinpal/callback` — بازگشت مرورگر از دروازه؛ ریدایرکت به پنل با `payment=paid|failed`
- [x] بایند پویا با env: `PAYMENT_GATEWAY=fake|zarinpal` + پیکربندی `ZARINPAL_*`
- [x] Idempotency کامل callback (پرداخت تکراری → فقط یک اشتراک و یک فاکتور)
### رفع باگ و پایداری
- [x] رفع باگ رگرسیون: Listener `AuditReferralReward` بدون `use` ثبت شده بود و هرگز فعال نمی‌شد (+ تست رگرسیون `ReferralAuditTest`)
- [x] تست‌ها (۲۰ سناریوی جدید): بایند پویا، صحت Endpoint/Payload، خطای IPPanel برای Retry صف، Request/Verify زرین‌پال، دفاع Authority نامطبق، NOK بدون تماس، جریان E2E پرداخت، Idempotency callback، Authority ناشناس، خطای وریفای — **مجموع ۱۴۷ تست سبز (۲,۶۷۲ assertion)**

---

## Sprint 8 — سندباکس و شروع بتای ۵ فروشگاه ✅ (کد) + 🚀 (بتا)

### تنظیمات سایت (بخش جدید پنل — فقط Admin)
- [x] Migration `site_settings` (تک‌ردیفی) + مدل + `SiteSettingsService` (Singleton)
- [x] `GET/PATCH /api/v1/site-settings` — فقط توکن با ability «admin» (مهمان → 401، Merchant → 403)
- [x] `GET /api/v1/auth/me` — نقش کاربر برای منوی Admin پنل پس از refresh صفحه
- [x] صدور توکن Admin با ability اضافه «admin» در OTP + گارد روت `/site-settings` در پنل
- [x] دستور `admin:promote {phone}` — ارتقای کاربر پنل به Admin (+ Audit)
- [x] UI پنل: ویو «تنظیمات سایت» با دو کلید سندباکس + هشدار گذار به کلیدهای واقعی (منو فقط برای Admin)
### سندباکس پیامک (OTP بدون IPPanel — درخواست کاربر)
- [x] با روشن‌بودن سندباکس: هیچ Job پیامکی به صف نمی‌رود؛ کد در پاسخ API (`debug_code` + `sandbox:true`) و لاگ
- [x] برای هر دو purpose (پنل و ورود مشتری)؛ Rate Limit و هش امن کد بدون تغییر
### سندباکس پرداخت (شبیه‌ساز داخلی — بدون ZarinPal واقعی — درخواست کاربر)
- [x] درایور `SandboxGateway` — مقدم بر env وقتی سندباکس روشن است:
  - `request()` → Authority داخلی `SBX-…` + صفحه پرداخت آزمایشی `/payments/sandbox/{id}`
  - کاربر «پرداخت موفق/ناموفق» را انتخاب می‌کند → همان `GET /payments/zarinpal/callback?Authority=…&Status=OK|NOK`
  - `verify()` → بدون تماس خارجی؛ همان دفاع تطبیق Authority — کل مسیر فعال‌سازی اشتراک/فاکتور/Audit مثل دروازه واقعی
- [x] فلگ `payments.meta.sandbox` برای تفکیک پرداخت‌های آزمایشی در Audit/گزارش‌ها
### شروع بتای ۵ فروشگاه 🚀
- [x] زیرساخت بتا آماده: سندباکس + تنظیمات سایت + راهنمای README (بخش «حالت سندباکس و بتای ۵ فروشگاه»)
- [x] ابزار و مستندات اجرای بتا: دستور `beta:status` (وضعیت زنده سندباکس‌ها/Adminها/فروشگاه‌ها/کمپین‌ها/پرداخت‌ها + گام بعدی پیشنهادی) + `docs/BETA_ONBOARDING.md` (راهنمای گام‌به‌گام Onboarding ۵ فروشگاه تا گذار به کلیدهای واقعی) + `docs/BETA_FEEDBACK_TEMPLATE.md` (فرم بازخورد پایلوت) + دموی `demo:seed`
- [ ] اجرای عملیاتی روی سرور (بعد از استقرار — کلیدها/سرور با شما): `admin:promote` → روشن‌کردن دو سندباکس از پنل → Onboarding ۵ فروشگاه پایلوت طبق BETA_ONBOARDING.md
- [ ] جمع‌آوری بازخورد پایلوت‌ها با فرم آماده و رفع اشکالات
- [ ] پایان بتا: خاموشی سندباکس + کلیدهای واقعی IPPanel/ZarinPal در env (بدون تغییر کد)
- [x] تست‌ها (۱۷ سناریوی جدید): دسترسی Admin/Merchant/مهمان، گذار رفت‌وبرگشتی سندباکس‌ها، اعتبارسنجی، auth/me، admin:promote، سندباکس OTP (پنل و مشتری)، بایند سندباکس مقدم بر env، صفحه پرداخت سندباکس، callback موفق/ناموفق/Authority جعلی، حفظ رفتار Fake — **مجموع ۱۶۴ تست سبز (۲,۷۵۱ assertion)**

---

## 📌 تصمیم‌های باز (پیش از استقرار نهایی شوند)

| تصمیم | وضعیت | توضیح |
|--------|--------|-------|
| سرویس پیامک OTP | ✅ نهایی شد — **IPPanel** | درایور `IppanelSmsChannel` آماده؛ فعال‌سازی با `SMS_CHANNEL=ippanel` + کلید API |
| دروازه پرداخت | ✅ نهایی شد — **ZarinPal** | درایور `ZarinpalGateway` آماده؛ فعال‌سازی با `PAYMENT_GATEWAY=zarinpal` + Merchant ID |
| سقف‌های عددی Planها | ✅ نهایی شد — **پیش‌فرض سند معماری** | Seeder مطابق جدول فصل ۷ اعمال شد (رایگان/پایه/حرفه‌ای)؛ سقف‌ها داده‌ای (features JSON) هستند و تغییر آتی بدون تغییر کد |

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
| 5 | `75d383f` | فرانت ۱/۳: زیرساخت Vue 3 + Vite، کلاینت API مشترک، App Shell و SW | 2026-09-17 |
| 6 | `e45b34e` | فرانت ۲/۳: PWA کامل مشتری — ۱۰ بازی lazy-load + Overlay + کیف جایزه | 2026-09-17 |
| 7 | `ef6a907` | فرانت ۳/۳: پنل فروشگاه‌دار با Wizard پویا + رفع import Reward + ۴ تست رگرسیون (۹۴ تست) | 2026-09-17 |
| 10 | `ee932ec` | Sprint 5: Analytics (قیف Append-Only + گزارش کمپین)، Referral پله‌ای، Check-in/Streak، تجمیع شبانه (۱۱۸ تست) | 2026-09-18 |
| 11 | `a627252` | Sprint 6: Audit کامل، ماتریس ۷ حمله امنیتی، Scheduler، بکاپ و راهنمای استقرار (۱۲۷ تست) | 2026-09-18 |
| 12 | `56f2298` | Sprint 7: درایور IPPanel (پیامک OTP) + دروازه ZarinPal v4 با callback مرورگر + رفع باگ Audit دعوت (۱۴۷ تست) | 2026-09-18 |
| 13 | `2badea3` | Sprint 8: تنظیمات سایت فقط-Admin با سندباکس پیامک/پرداخت + admin:promote + شروع بتای ۵ فروشگاه (۱۶۴ تست) | 2026-09-18 |
| fix | `21c9e84` | رفع flaky CI: کد دعوت یکتا در تست‌ها (شمارنده پروسه) + حلقه Retry در تولید production (۱۶۴ تست، ۳ اجرای متوالی سبز) | 2026-09-18 |
| 14 | `2f5c076` | بودجه عملکرد Sprint 4 (Lighthouse CI + بودجه JS + App Shell + demo:seed) و ابزار بتا (beta:status + مستندات Onboarding و بازخورد) | 2026-09-18 |
| docs | `b173bdd` | راهنمای کاربر `help.md`: راه‌اندازی محلی، پیکربندی env، جریان پنل فروشگاه‌دار و PWA مشتری، سندباکس و بتا، استقرار production با کلیدهای واقعی، مرجع دستورها/API و رفع اشکال | 2026-09-18 |
| fix | — | رفع باگ لینک callback در صفحه پرداخت سندباکس: `url($path, $array)` پارامترها را path segment می‌ساخت → «پرداخت موفق/ناموفق» 404 می‌شد؛ اصلاح به query string (+ تست رگرسیون — **۱۶۵ تست سبز (۲,۷۵۷ assertion)**) | 2026-09-18 |
| docs | — | پوشه `screenshots/` با ۲۰ اسکرین‌شات از همه بخش‌ها (پنل، Wizard، PWA مشتری، سندباکس پرداخت) + README ایندکس | 2026-09-18 |
