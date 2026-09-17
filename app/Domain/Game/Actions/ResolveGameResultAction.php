<?php

namespace App\Domain\Game\Actions;

use App\Domain\Campaign\Services\CampaignRuleEngine;
use App\Domain\Game\DTO\PlayerAction;
use App\Domain\Game\Events\GamePlayed;
use App\Domain\Game\GameRegistry;
use App\Domain\Game\Services\ResultSigner;
use App\Domain\Reward\RewardEngine;
use App\Models\CampaignParticipation;
use App\Models\GameSession;
use App\Support\Exceptions\ApiException;
use Throwable;

/**
 * فازهای ۲ تا ۵ چرخه Session — فصل ۵-۴ سند معماری:
 * اکشن عمومی کاربر → تولید نتیجه امن سمت سرور (weighted RNG) → واگذاری به
 * Reward Engine → ثبت نهایی به‌همراه پاسخ امضاشده.
 *
 * ضدتقلب:
 *  - نتیجه فقط با random_int سمت سرور تولید می‌شود؛ ورودی کلاینت اعتباری ندارد.
 *  - مصرف اتمی توکن (UPDATE ... WHERE status = started) → Replay رد می‌شود.
 */
final class ResolveGameResultAction
{
    public function __construct(
        private readonly RewardEngine $rewardEngine,
    ) {}

    public function handle(GameSession $session, PlayerAction $action): array
    {
        if ($session->isExpired()) {
            throw new ApiException('SESSION_EXPIRED', 'زمان این Session به پایان رسیده است.', 410);
        }

        $campaign = $session->campaign()->with('game', 'configuration')->firstOrFail();
        $plugin = GameRegistry::for($campaign->game->code);

        // فاز ۳: تولید نتیجه فقط سمت سرور
        $result = $plugin->resolveResult($session, $action);

        // فاز ۴: واگذاری به Reward Engine (پلاگین هرگز جایزه نمی‌سازد)
        $reward = $this->rewardEngine->resolveForSession($campaign, $session, $result);

        $payload = [
            'outcome' => $result->outcome,
            'reward_ref' => $result->rewardRef,
            'raw' => $result->raw,
            'display' => $result->display,
            'reward' => $reward,
        ];

        $signature = ResultSigner::sign($payload);

        // فاز ۵: مصرف اتمی توکن + ثبت نتیجه — فقط اولین درخواست موفق می‌شود
        $consumed = GameSession::query()
            ->whereKey($session->getKey())
            ->where('status', GameSession::STATUS_STARTED)
            ->update([
                'status' => GameSession::STATUS_COMPLETED,
                'result' => $payload,
                'result_signature' => $signature,
                'completed_at' => now(),
            ]);

        if ($consumed === 0) {
            throw new ApiException('SESSION_CONSUMED', 'این Session قبلاً مصرف شده است.', 409);
        }

        $this->recordParticipation($session, $result->outcome);

        GamePlayed::dispatch($session->refresh(), $result);

        return [
            'result' => $payload,
            'signature' => $signature,
            'rules_summary' => [
                'remaining' => app(CampaignRuleEngine::class)->remainingToday($campaign, $session->customer),
            ],
        ];
    }

    private function recordParticipation(GameSession $session, string $outcome): void
    {
        try {
            CampaignParticipation::query()->firstOrCreate([
                'campaign_id' => $session->campaign_id,
                'customer_id' => $session->customer_id,
                'played_on' => now()->toDateString(),
            ], [
                'store_id' => $session->store_id,
                'game_session_id' => $session->getKey(),
                'outcome' => $outcome,
                'rewarded_at' => $outcome === 'win' ? now() : null,
            ]);
        } catch (Throwable) {
            // در رقابت هم‌زمان، ایندکس یکتا نقش دفاعی دارد؛ تکرار بی‌اثر است
        }
    }
}
