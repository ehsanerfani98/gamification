<?php

namespace App\Domain\Coupon\Events;

use App\Models\Coupon;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** مصرف‌کنندگان: Analytics، Campaign (اندازه‌گیری ROI) — فصل ۴-۳ */
final class CouponCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Coupon $coupon) {}
}
