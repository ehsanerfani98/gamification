<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Subscription\Actions\SubscribeAction;
use App\Domain\Subscription\Services\FeatureGate;
use App\Http\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SubscriptionController extends Controller
{
    use ApiResponse;

    /** GET /api/v1/subscriptions — وضعیت اشتراک فروشگاه جاری (فصل ۸-۲) */
    public function show(Request $request): JsonResponse
    {
        /** @var Store $store */
        $store = $request->attributes->get('store');

        $subscription = $store->subscriptions()
            ->with('plan.games:id,code,name')
            ->latest('id')
            ->first();

        $gate = FeatureGate::for($store);

        return $this->ok([
            'subscription' => $subscription ? [
                'id' => $subscription->id,
                'status' => $subscription->status,
                'plan' => $subscription->plan?->only(['id', 'name', 'slug', 'price_irt']),
                'games' => $subscription->plan?->games->map(fn ($g) => $g->code),
                'starts_at' => $subscription->starts_at?->toIso8601String(),
                'ends_at' => $subscription->ends_at?->toIso8601String(),
            ] : null,
            'features' => $gate->features(),
        ]);
    }

    /** POST /api/v1/subscriptions — شروع خرید اشتراک {plan_id} */
    public function subscribe(Request $request): JsonResponse
    {
        /** @var Store $store */
        $store = $request->attributes->get('store');

        $data = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
        ]);

        $plan = Plan::query()->where('is_active', true)->findOrFail($data['plan_id']);

        $result = app(SubscribeAction::class)->handle($store, $plan);

        return $this->ok([
            'subscription' => $result['subscription'] ? [
                'id' => $result['subscription']->id,
                'status' => $result['subscription']->status,
                'plan' => $plan->only(['id', 'name', 'slug']),
                'ends_at' => $result['subscription']->ends_at?->toIso8601String(),
            ] : null,
            'payment' => $result['payment'] ? [
                'id' => $result['payment']->id,
                'status' => $result['payment']->status,
                'amount_irt' => $result['payment']->amount_irt,
                'reference' => $result['payment']->reference,
                'redirect_url' => $result['redirect_url'],
            ] : null,
        ]);
    }
}
