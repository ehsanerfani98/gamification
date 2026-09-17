<?php

namespace App\Domain\Game\Events;

use App\Domain\Game\DTO\GameResult;
use App\Models\GameSession;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** مصرف‌کنندگان: Analytics، Referral (چالش‌ها) — فصل ۴-۳ */
final class GamePlayed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly GameSession $session,
        public readonly GameResult $result,
    ) {}
}
