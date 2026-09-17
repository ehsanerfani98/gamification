<?php

namespace App\Domain\Referral\Events;

use App\Models\Customer;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * پرداخت جایزه پله‌ای دعوت — مصرف‌کنندگان: Analytics، Notification — فصل ۱۰.
 * با عبور از هر آستانه (۱/۳/۵ دعوت) یک‌بار برای دعوت‌کننده پرداخت می‌شود.
 */
final class ReferralRewardGranted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Customer $referrer,
        public readonly int $points,
        public readonly int $tier,
        public readonly int $totalReferrals,
    ) {}
}
