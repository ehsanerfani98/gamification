<?php

namespace App\Domain\Campaign\Events;

use App\Models\Campaign;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** بازدید لندینگ کمپین — مصرف‌کننده: Analytics (قیف فصل ۱۰) */
final class CampaignViewed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Campaign $campaign) {}
}
