<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Subscription\Actions\SubscribeAction;
use App\Http\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Store;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class StoreController extends Controller
{
    use ApiResponse;

    /** GET /api/v1/stores — فهرست فروشگاه‌های Merchant (فصل ۸-۲) */
    public function index(Request $request): JsonResponse
    {
        $stores = $request->user()
            ->stores()
            ->with('subscriptions')
            ->orderBy('id')
            ->get()
            ->map(fn (Store $store) => $this->present($store));

        return $this->ok(['stores' => $stores]);
    }

    /** POST /api/v1/stores — ساخت فروشگاه + اشتراک رایگان خودکار */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['sometimes', 'string', 'max:60', 'alpha_dash', 'unique:stores,slug'],
        ]);

        $store = $request->user()->stores()->create([
            'name' => $data['name'],
            'slug' => $data['slug'] ?? $this->uniqueSlug($data['name']),
            'status' => 'active',
        ]);

        // هر Store تازه‌ساخته‌شده روی Plan رایگان شروع می‌کند (فصل ۷-۱)
        $subscription = app(SubscribeAction::class)
            ->activatePlan($store, Plan::free());

        return $this->created([
            'store' => $this->present($store->load('subscriptions')),
            'subscription' => $this->presentSubscription($subscription),
        ]);
    }

    /** GET /api/v1/stores/{id} — فقط از میان فروشگاه‌های خود کاربر */
    public function show(Request $request, int $id): JsonResponse
    {
        $store = $request->user()->stores()->whereKey($id)->first();

        // Cross-Tenant همیشه 404 — بدون افشای وجود منبع (فصل ۲-۵)
        if ($store === null) {
            abort(404);
        }

        return $this->ok(['store' => $this->present($store->load('subscriptions'))]);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'store';

        do {
            $slug = $base.'-'.Str::lower(Str::random(4));
        } while (Store::query()->where('slug', $slug)->exists());

        return $slug;
    }

    private function present(Store $store): array
    {
        return [
            'id' => $store->id,
            'name' => $store->name,
            'slug' => $store->slug,
            'status' => $store->status,
            'subscription' => $store->activeSubscription() ? $this->presentSubscription($store->activeSubscription()) : null,
        ];
    }

    private function presentSubscription(?Subscription $subscription): ?array
    {
        if ($subscription === null) {
            return null;
        }

        return [
            'id' => $subscription->id,
            'plan' => $subscription->plan?->only(['id', 'name', 'slug']),
            'status' => $subscription->status,
            'starts_at' => $subscription->starts_at?->toIso8601String(),
            'ends_at' => $subscription->ends_at?->toIso8601String(),
        ];
    }
}
