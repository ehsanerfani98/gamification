<?php

namespace App\Domain\Campaign\Events;

use App\Models\Campaign;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** مصرف‌کنندگان: Analytics، Notification (فصل ۴-۳) */
final class CampaignPublished
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Campaign $campaign,
        public readonly string $from,
    ) {}
}
