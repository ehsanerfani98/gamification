<?php

namespace App\Domain\Points\Events;

use App\Models\Customer;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** مصرف‌کنندگان: Analytics، Notification — فصل ۴-۳ */
final class PointsEarned
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Customer $customer,
        public readonly int $delta,
        public readonly int $balance,
    ) {}
}
