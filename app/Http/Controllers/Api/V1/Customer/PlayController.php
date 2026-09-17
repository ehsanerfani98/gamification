<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Domain\Campaign\Services\CampaignRuleEngine;
use App\Domain\Game\Actions\ResolveGameResultAction;
use App\Domain\Game\Actions\StartGameSessionAction;
use App\Domain\Game\DTO\PlayerAction;
use App\Http\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Customer;
use App\Models\GameSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * جریان بازی مشتری — فصل ۵-۴ و ۸-۲:
 * POST /play/sessions (Start) → POST /play/sessions/{token}/action (نتیجه).
 * توکن مشتری فقط به Sessionهای خودش دسترسی دارد.
 */
final class PlayController extends Controller
{
    use ApiResponse;

    /** POST /api/v1/play/sessions — شروع Session با Idempotency-Key (فصل ۸-۳) */
    public function start(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();

        $data = $request->validate([
            'campaign_slug' => ['required', 'string', 'exists:campaigns,slug'],
        ]);

        $campaign = Campaign::query()
            ->where('slug', $data['campaign_slug'])
            ->firstOrFail();

        // دفاع Cross-Tenant: مشتری فقط در کمپین Store خودش بازی می‌کند
        if ((int) $campaign->store_id !== (int) $customer->store_id) {
            abort(404);
        }

        $session = app(StartGameSessionAction::class)->handle(
            $campaign,
            $customer,
            $request->header('Idempotency-Key'),
        );

        return $this->ok([
            'session' => [
                'play_token' => $session->play_token,
                'status' => $session->status,
                'token_expires_at' => $session->token_expires_at?->toIso8601String(),
            ],
            'rules_summary' => [
                'remaining' => app(CampaignRuleEngine::class)->remainingToday($campaign, $customer),
            ],
        ]);
    }

    /** POST /api/v1/play/sessions/{token}/action — اکشن و دریافت نتیجه امضاشده */
    public function action(string $token, Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();

        $data = $request->validate([
            'type' => ['required', 'string', Rule::in(['spin', 'pick', 'scratch', 'answer', 'tap'])],
            'payload' => ['sometimes', 'array'],
        ]);

        $session = GameSession::query()
            ->where('play_token', $token)
            ->where('customer_id', $customer->getKey())
            ->first();

        // توکن نامعتبر یا Session متعلق به مشتری دیگر → 404 بدون افشا
        if ($session === null) {
            abort(404);
        }

        // ورودی کلاینت صرفاً اکشن نمایشی است؛ در نتیجه نقشی ندارد (فصل ۲-۵)
        $outcome = app(ResolveGameResultAction::class)->handle(
            $session,
            new PlayerAction($data['type'], $data['payload'] ?? []),
        );

        return $this->ok($outcome);
    }
}
