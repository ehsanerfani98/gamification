<?php

namespace App\Domain\Referral\Events;

use App\Models\Referral;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** ثبت دعوت موفق — مصرف‌کنندگان: Analytics، Notification، Audit — فصل ۴-۳ */
final class ReferralRegistered
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Referral $referral) {}
}
