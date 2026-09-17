<?php

namespace App\Domain\Reward\Events;

use App\Domain\Reward\DTO\IssuanceResult;
use App\Models\GameSession;
use App\Models\Reward;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** مصرف‌کنندگان: Analytics، Notification، Audit — فصل ۴-۳ و ۶-۴ */
final class RewardIssued
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Reward $reward,
        public readonly GameSession $session,
        public readonly IssuanceResult $issuance,
    ) {}
}
