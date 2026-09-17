<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;

final class PlanController extends Controller
{
    use ApiResponse;

    /** GET /api/v1/plans — فهرست Planها با امکانات و بازی‌های مجاز (فصل ۸-۲) */
    public function index(): JsonResponse
    {
        $plans = Plan::query()
            ->where('is_active', true)
            ->with('games:id,code,name')
            ->orderBy('sort')
            ->get()
            ->map(fn (Plan $plan) => [
                'id' => $plan->id,
                'name' => $plan->name,
                'slug' => $plan->slug,
                'price_irt' => $plan->price_irt,
                'billing_period' => $plan->billing_period,
                'features' => $plan->features ?? [],
                'games' => $plan->games->map(fn ($g) => ['code' => $g->code, 'name' => $g->name]),
            ]);

        return $this->ok(['plans' => $plans]);
    }
}
