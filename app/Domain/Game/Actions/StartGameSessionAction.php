<?php

namespace App\Domain\Game\Actions;

use App\Domain\Campaign\Services\CampaignRuleEngine;
use App\Domain\Game\Events\GameSessionStarted;
use App\Models\Campaign;
use App\Models\Customer;
use App\Models\GameSession;
use App\Support\Exceptions\ApiException;

/**
 * فاز ۱ چرخه Session — فصل ۵-۴: شروع Session با بررسی قوانین و توکن یک‌بارمصرف.
 *
 * Idempotency (فصل ۸-۳): کلاینت هدر Idempotency-Key می‌فرستد؛ تپ مضاعف یا
 * قطعی شبکه هرگز دو Session نمی‌سازد — پاسخ همان Session قبلی برمی‌گردد.
 */
final class StartGameSessionAction
{
    public function __construct(
        private readonly CampaignRuleEngine $rules,
    ) {}

    public function handle(Campaign $campaign, Customer $customer, ?string $idempotencyKey = null): GameSession
    {
        if (! $campaign->isPlayable()) {
            throw new ApiException('CAMPAIGN_NOT_ACTIVE', 'این کمپین در حال حاضر فعال نیست.', 409);
        }

        // Idempotency-Key: بازگشت همان Session معتبر قبلی
        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $existing = GameSession::query()
                ->where('customer_id', $customer->getKey())
                ->where('idempotency_key', $idempotencyKey)
                ->where('status', GameSession::STATUS_STARTED)
                ->first();

            if ($existing !== null) {
                return $existing;
            }
        }

        // قوانین مشارکت (Daily Limit، سقف کل، مشتری جدید)
        $this->rules->assertCanPlay($campaign, $customer);

        $session = GameSession::query()->create([
            'store_id' => $campaign->store_id,
            'campaign_id' => $campaign->getKey(),
            'customer_id' => $customer->getKey(),
            'play_token' => bin2hex(random_bytes(24)),
            'status' => GameSession::STATUS_STARTED,
            'idempotency_key' => $idempotencyKey,
            'started_at' => now(),
            'token_expires_at' => now()->addMinutes((int) config('gamification.session.token_ttl_minutes', 10)),
        ]);

        GameSessionStarted::dispatch($session);

        return $session;
    }
}
