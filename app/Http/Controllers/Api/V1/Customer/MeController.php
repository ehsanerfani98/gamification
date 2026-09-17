<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Domain\Points\Actions\EarnPointsAction;
use App\Http\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** کدها و امتیازهای مشتری — فصل ۸-۲ (GET /me/rewards) */
final class MeController extends Controller
{
    use ApiResponse;

    public function rewards(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();

        $coupons = Coupon::query()
            ->where('customer_id', $customer->getKey())
            ->with('store:id,name')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Coupon $coupon) => [
                'code' => $coupon->code,
                'type' => $coupon->type,
                'value' => $coupon->value,
                'status' => $coupon->status,
                'expires_at' => $coupon->expires_at?->toIso8601String(),
                'store' => $coupon->store?->name,
            ]);

        $points = app(EarnPointsAction::class)->balance($customer);

        return $this->ok([
            'coupons' => $coupons,
            'points' => $points,
        ]);
    }
}
