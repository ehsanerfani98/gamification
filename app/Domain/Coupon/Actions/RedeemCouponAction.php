<?php

namespace App\Domain\Coupon\Actions;

use App\Domain\Coupon\Events\CouponRedeemed;
use App\Models\AuditLog;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\User;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Facades\DB;

/**
 * ثبت Redemption کوپن در فروشگاه — فصل ۶-۴ و ۳-۴:
 * لحظه استفاده واقعی ثبت می‌شود تا ROI کمپین قابل اندازه‌گیری باشد.
 * وضعیت کوپن به‌صورت اتمی به redeemed تغییر می‌کند (مصرف دوباره رد می‌شود).
 */
final class RedeemCouponAction
{
    public function handle(string $code, ?int $amountIrt = null, ?string $orderRef = null): array
    {
        return DB::transaction(function () use ($code, $amountIrt, $orderRef): array {
            /** @var Coupon|null $coupon */
            $coupon = Coupon::query()->where('code', mb_strtoupper(trim($code)))->first();

            // بدون افشای تفاوت «ناموجود» و «مصرف‌شده» برای حدس‌زننده کد
            if ($coupon === null || ! $coupon->isValid()) {
                throw new ApiException('COUPON_INVALID', 'این کد تخفیف معتبر نیست یا قبلاً استفاده شده است.', 422);
            }

            $updated = Coupon::query()
                ->whereKey($coupon->getKey())
                ->where('status', Coupon::STATUS_ISSUED)
                ->update(['status' => Coupon::STATUS_REDEEMED, 'redeemed_at' => now()]);

            if ($updated === 0) {
                throw new ApiException('COUPON_INVALID', 'این کد تخفیف معتبر نیست یا قبلاً استفاده شده است.', 422);
            }

            $redemption = CouponRedemption::query()->create([
                'coupon_id' => $coupon->getKey(),
                'amount_irt' => $amountIrt,
                'order_ref' => $orderRef,
                'redeemed_at' => now(),
            ]);

            CouponRedeemed::dispatch($redemption);

            // رخداد حساس — فصل ۲-۵ و ۱۰ (Sprint 6): ثبت Audit استفاده کوپن
            $actor = auth()->user();

            AuditLog::record('coupon.redeemed', $actor instanceof User ? $actor : null, $coupon, [
                'redemption_id' => (int) $redemption->getKey(),
                'amount_irt' => $amountIrt,
                'order_ref' => $orderRef,
            ]);

            return ['coupon' => $coupon->refresh(), 'redemption' => $redemption];
        });
    }
}
