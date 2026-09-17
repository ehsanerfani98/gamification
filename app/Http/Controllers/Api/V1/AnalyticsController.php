<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Analytics\Actions\BuildCampaignFunnelReport;
use App\Http\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use Illuminate\Http\JsonResponse;

/**
 * گزارش Analytics کمپین — فصل ۱۰ سند معماری (GET /api/v1/campaigns/{id}/analytics).
 * فروشگاه در resolve.store تعیین می‌شود؛ کمپینِ Store دیگر 404 است
 * (Global Scope Tenant — بدون افشای وجود منبع).
 */
final class AnalyticsController extends Controller
{
    use ApiResponse;

    public function campaign(string $id): JsonResponse
    {
        $campaign = Campaign::query()->findOrFail($id);

        $report = app(BuildCampaignFunnelReport::class)->handle($campaign);

        return $this->ok($report);
    }
}
