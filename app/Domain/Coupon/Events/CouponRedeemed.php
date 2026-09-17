<?php

namespace App\Domain\Coupon\Events;

use App\Models\CouponRedemption;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** مصرف‌کنندگان: Analytics، Campaign (بستن حلقه ROI) — فصل ۴-۳ */
final class CouponRedeemed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly CouponRedemption $redemption) {}
}
