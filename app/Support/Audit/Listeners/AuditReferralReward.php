<?php

namespace App\Support\Audit\Listeners;

use App\Domain\Referral\Events\ReferralRewardGranted;
use App\Models\AuditLog;

/** Audit پرداخت جایزه پله‌ای دعوت — فصل ۱۰ سند معماری (Sprint 6) */
final class AuditReferralReward
{
    public function handle(ReferralRewardGranted $event): void
    {
        AuditLog::record('referral.rewarded', null, $event->referrer, [
            'tier' => $event->tier,
            'points' => $event->points,
            'total_referrals' => $event->totalReferrals,
        ], actorType: 'customer');
    }
}
