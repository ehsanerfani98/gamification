<?php

namespace App\Domain\Analytics\Listeners;

use App\Domain\Analytics\Services\AnalyticsTracker;
use App\Domain\Coupon\Events\CouponRedeemed;
use App\Models\AnalyticsEvent;

/**
 * قیف مرحله ۵ — ثبت استفاده واقعی کوپن (حلقه ROI).
 * campaign_id از کوپن به‌دست می‌آید؛ اگر کوپن بدون کمپین صادر
 * شده باشد، رویداد فقط در سطح Store شمرده می‌شود.
 */
final class RecordCouponRedeem
{
    public function __construct(private readonly AnalyticsTracker $tracker) {}

    public function handle(CouponRedeemed $event): void
    {
        $coupon = $event->redemption->coupon;

        $this->tracker->record(
            name: AnalyticsEvent::NAME_REDEEM,
            storeId: (int) $coupon->store_id,
            campaignId: $coupon->campaign_id,
            customerId: $coupon->customer_id,
            properties: ['coupon_code' => $coupon->code],
        );
    }
}
