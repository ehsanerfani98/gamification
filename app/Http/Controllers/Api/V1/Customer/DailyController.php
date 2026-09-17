<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Domain\Retention\Actions\DailyCheckinAction;
use App\Http\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * چک‌این روزانه — فصل ۱۰ سند معماری (POST /api/v1/daily/checkin).
 * چک‌این تکراری همان روز Idempotent است (already) و امتیاز جدیدی نمی‌سازد.
 */
final class DailyController extends Controller
{
    use ApiResponse;

    public function checkin(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();

        $result = app(DailyCheckinAction::class)->handle($customer);

        return $this->ok($result);
    }
}
