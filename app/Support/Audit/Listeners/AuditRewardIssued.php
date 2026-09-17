<?php

namespace App\Support\Audit\Listeners;

use App\Domain\Reward\Events\RewardIssued;
use App\Models\AuditLog;

/** Audit صدور جایزه — فصل ۱۰ سند معماری (Sprint 6) */
final class AuditRewardIssued
{
    public function handle(RewardIssued $event): void
    {
        AuditLog::record('reward.issued', null, $event->reward, [
            'session_id' => (int) $event->session->getKey(),
            'customer_id' => (int) $event->session->customer_id,
            'campaign_id' => (int) $event->session->campaign_id,
            'type' => $event->reward->type,
            'kind' => $event->issuance->kind,
        ]);
    }
}
