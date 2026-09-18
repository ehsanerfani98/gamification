# 📸 اسکرین‌شات‌های پلتفرم گیمیفیکیشن SaaS

گرفته‌شده از دموی محلی (`demo:seed`) با کمپین منتشرشده `demo-wheel`، چند مشتری شبیه‌سازی‌شده و یک پرداخت سندباکس کامل.

## پنل فروشگاه‌دار (`/panel` — دسکتاپ)

| فایل | بخش |
|------|-----|
| `01-panel-login-phone.png` | ورود — گام شماره موبایل |
| `02-panel-login-otp.png` | ورود — گام کد تأیید |
| `03-panel-dashboard.png` | داشبورد (کمپین فعال، تعداد مشتریان، بانک کوپن، شروع سریع) |
| `04-panel-campaigns.png` | فهرست کمپین‌ها با وضعیت انتشار |
| `05-panel-games.png` | کتابخانه بازی — هر ۱۰ بازی MVP با نشان Plan |
| `06-panel-coupons.png` | ثبت استفاده کوپن (حلقه ROI) |
| `07-panel-subscription.png` | اشتراک و Plan (حالت رایگان) |
| `08-panel-stores.png` | فروشگاه‌ها |
| `09-panel-site-settings.png` | تنظیمات سایت (فقط Admin) — کلیدهای سندباکس پیامک و پرداخت |
| `10-panel-campaign-detail.png` | جزئیات کمپین — لینک عمومی، پیکربندی بازی، جایزه‌ها |
| `11-panel-campaign-detail-full.png` | جزئیات کمپین — نمای کامل صفحه |
| `12-panel-wizard-step1.png` | Wizard ساخت کمپین — مرحله ۱ (مشخصات) |
| `13-panel-wizard-step2.png` | Wizard ساخت کمپین — مرحله ۲ (فرم پویا از JSON Schema بازی) |
| `20-panel-subscription-active.png` | پس از پرداخت سندباکس موفق — Plan پایه فعال شد |

## PWA مشتری (`/c/demo-wheel` — موبایل ۳۹۰×۸۴۴)

| فایل | بخش |
|------|-----|
| `14-pwa-campaign-enter.png` | صفحه ورود کمپین (شماره موبایل) |
| `15-pwa-otp.png` | کد تأیید ورود مشتری |
| `16-pwa-wheel-game.png` | چرخ شانس آماده چرخش |
| `17-pwa-result-overlay.png` | Overlay نتیجه — برد ۵۰ امتیاز |
| `18-pwa-rewards-wallet.png` | کیف جایزه‌ها — موجودی امتیاز |

## سندباکس پرداخت (Sprint 8)

| فایل | بخش |
|------|-----|
| `19-sandbox-payment.png` | صفحه پرداخت آزمایشی داخلی (به‌جای ZarinPal در بتا) |

## بازتولید این اسکرین‌شات‌ها

```bash
php artisan migrate:fresh --seed && php artisan demo:seed
npm run build && php artisan serve
# سپس مرور: /panel و /c/demo-wheel و (با روشن‌کردن سندباکس پرداخت از تنظیمات سایت) صفحه پرداخت آزمایشی
```

راهنمای کامل کار با برنامه: **[help.md](../help.md)**
