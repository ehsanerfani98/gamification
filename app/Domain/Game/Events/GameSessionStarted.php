<?php

namespace App\Domain\Game\Events;

use App\Models\GameSession;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** مصرف‌کنندگان: Analytics، Campaign (شمارش مشارکت) — فصل ۴-۳ */
final class GameSessionStarted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly GameSession $session) {}
}
