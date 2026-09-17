<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Subscription\Services\FeatureGate;
use App\Http\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Reward;
use App\Models\RewardInventory;
use App\Models\Store;
use App\Support\RewardIssuerTypes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** مدیریت جایزه‌های کمپین — فصل ۸-۲ (مسیرهای Merchant) */
final class RewardController extends Controller
{
    use ApiResponse;

    /** GET /api/v1/campaigns/{id}/rewards */
    public function index(Request $request, int $id): JsonResponse
    {
        /** @var Store $store */
        $store = $request->attributes->get('store');

        $campaign = $store->campaigns()->find($id);

        if ($campaign === null) {
            abort(404);
        }

        $rewards = $campaign->rewards()->with('inventory')->orderBy('id')->get()
            ->map(fn (Reward $reward) => $this->present($reward));

        return $this->ok(['rewards' => $rewards, 'types' => RewardIssuerTypes::all()]);
    }

    /** POST /api/v1/campaigns/{id}/rewards — تعریف جایزه + موجودی اولیه */
    public function store(Request $request, int $id): JsonResponse
    {
        /** @var Store $store */
        $store = $request->attributes->get('store');

        $campaign = $store->campaigns()->with('rules')->find($id);

        if ($campaign === null) {
            abort(404);
        }

        $data = $request->validate([
            'ref' => ['required', 'string', 'max:60', Rule::unique('rewards', 'ref')->where('campaign_id', $campaign->id)],
            'type' => ['required', 'string', Rule::in(RewardIssuerTypes::all())],
            'name' => ['required', 'string', 'max:120'],
            'params' => ['nullable', 'array'],
            'weight' => ['sometimes', 'integer', 'min:1', 'max:1000'],
            'total_qty' => ['nullable', 'integer', 'min:1'],
        ]);

        // سقف جایزه تعریف‌شده بر اساس Plan (فصل ۷)
        $gate = FeatureGate::for($store);
        $maxRewards = $gate->maxRewards();

        if ($maxRewards !== null && $campaign->rewards()->count() >= $maxRewards) {
            return $this->fail('QUOTA_EXCEEDED', 'سقف تعریف جایزه در Plan شما پر شده است.', 403);
        }

        $reward = $campaign->rewards()->create([
            'store_id' => $store->id,
            'ref' => $data['ref'],
            'type' => $data['type'],
            'name' => $data['name'],
            'params' => $data['params'] ?? null,
            'weight' => $data['weight'] ?? 1,
            'total_qty' => $data['total_qty'] ?? null,
            'is_active' => true,
        ]);

        // موجودی اولیه — جدای از تعریف (فصل ۳-۴)
        RewardInventory::query()->create([
            'reward_id' => $reward->id,
            'remaining_qty' => $data['total_qty'] ?? 0,
        ]);

        return $this->created(['reward' => $this->present($reward->load('inventory'))]);
    }

    private function present(Reward $reward): array
    {
        return [
            'id' => $reward->id,
            'ref' => $reward->ref,
            'type' => $reward->type,
            'name' => $reward->name,
            'params' => $reward->params,
            'weight' => $reward->weight,
            'total_qty' => $reward->total_qty,
            'remaining' => $reward->remainingQty(),
            'is_active' => $reward->is_active,
        ];
    }
}
