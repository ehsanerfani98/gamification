<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Domain\Referral\Actions\CompleteReferralAction;
use App\Http\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * دعوت دوست — فصل ۱۰ سند معماری (POST /api/v1/referrals/apply).
 * مشتریِ لاگین‌شده در کمپین، کد دعوت دوستش را ثبت می‌کند؛ ثبت تکراری
 * Idempotent است (already) و جایزه جدیدی نمی‌سازد.
 */
final class ReferralController extends Controller
{
    use ApiResponse;

    public function apply(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();

        $data = $request->validate([
            'code' => ['required', 'string', 'max:20'],
        ]);

        $result = app(CompleteReferralAction::class)->handle($customer, $data['code']);

        return $this->ok([
            'status' => $result['status'],
            'total_referrals' => $result['total'],
            'awarded' => $result['awarded'],
        ]);
    }
}
