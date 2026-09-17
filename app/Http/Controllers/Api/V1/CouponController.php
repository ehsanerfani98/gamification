<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Coupon\Actions\RedeemCouponAction;
use App\Http\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** ثبت Redemption کوپن در فروشگاه — بستن حلقه ROI (فصل ۳-۴ و ۶-۴) */
final class CouponController extends Controller
{
    use ApiResponse;

    /** POST /api/v1/coupons/redeem {code, amount_irt?, order_ref?} */
    public function redeem(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:32'],
            'amount_irt' => ['nullable', 'integer', 'min:0'],
            'order_ref' => ['nullable', 'string', 'max:60'],
        ]);

        $result = app(RedeemCouponAction::class)->handle(
            $data['code'],
            $data['amount_irt'] ?? null,
            $data['order_ref'] ?? null,
        );

        return $this->ok([
            'coupon' => [
                'code' => $result['coupon']->code,
                'type' => $result['coupon']->type,
                'value' => $result['coupon']->value,
                'status' => $result['coupon']->status,
                'redeemed_at' => $result['coupon']->redeemed_at?->toIso8601String(),
            ],
        ]);
    }
}
