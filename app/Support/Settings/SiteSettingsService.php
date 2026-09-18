<?php

namespace App\Support\Settings;

use App\Models\SiteSettings;

/**
 * سرویس تنظیمات سایت — نقطه یکتای خواندن/نوشتن (Singleton در AppServiceProvider).
 *
 * سندباکس‌ها برای بتای ۵ فروشگاه طراحی شده‌اند: مدیر سایت بدون کلید واقعی
 * IPPanel/ZarinPal می‌تواند کل جریان ورود و خرید اشتراک را تست کند.
 * درخوانی از DB هر بار یک Query سبک است — کش در فاز مقیاس‌پذیری اضافه می‌شود.
 */
final class SiteSettingsService
{
    public function get(): SiteSettings
    {
        return SiteSettings::current();
    }

    public function smsSandboxEnabled(): bool
    {
        return $this->get()->sms_sandbox;
    }

    public function paymentSandboxEnabled(): bool
    {
        return $this->get()->payment_sandbox;
    }

    /**
     * به‌روزرسانی تنظیمات — فقط از SiteSettingsController (Admin) فراخوانی می‌شود.
     *
     * @param  array{sms_sandbox?: bool, payment_sandbox?: bool}  $data
     */
    public function update(array $data): SiteSettings
    {
        $settings = $this->get();
        $settings->fill($data)->save();

        return $settings;
    }
}
