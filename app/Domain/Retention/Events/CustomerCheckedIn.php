<?php

namespace App\Domain\Retention\Events;

use App\Models\CustomerCheckin;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** چک‌این روزانه مشتری — مصرف‌کنندگان: Analytics، Notification — فصل ۱۰ */
final class CustomerCheckedIn
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly CustomerCheckin $checkin,
        public readonly int $pointsAwarded,
        public readonly bool $hasStreakBonus,
    ) {}
}
