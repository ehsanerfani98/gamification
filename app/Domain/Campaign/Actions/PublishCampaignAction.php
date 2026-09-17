<?php

namespace App\Domain\Campaign\Actions;

use App\Domain\Subscription\Services\FeatureGate;
use App\Models\Campaign;
use App\Support\Exceptions\ApiException;

/**
 * انتشار کمپین — فصل ۱-۳ و ۷-۲: سهمیه‌ها در لایه Use Case اعمال می‌شوند.
 * عبور سهمیه هرگز خاموش نادیده گرفته نمی‌شود؛ با خطای روشن مسدود می‌شود.
 */
final class PublishCampaignAction
{
    public function handle(Campaign $campaign): Campaign
    {
        if ($campaign->status !== Campaign::STATUS_DRAFT && $campaign->status !== Campaign::STATUS_SCHEDULED) {
            throw new ApiException('INVALID_CAMPAIGN_STATE', 'فقط کمپین پیش‌نویس قابل انتشار است.', 409);
        }

        $store = $campaign->store;

        // Feature Gating: بازی باید در Plan فعلی مجاز باشد (فصل ۷-۱)
        $gate = FeatureGate::for($store);

        if (! $gate->canAccessGame($campaign->game)) {
            throw new ApiException('PLAN_GAME_NOT_ALLOWED', 'این بازی در Plan فعلی شما فعال نیست.', 403);
        }

        // سهمیه کمپین فعال هم‌زمان
        $maxCampaigns = $gate->maxActiveCampaigns();

        if ($maxCampaigns !== null) {
            $activeCount = $store->campaigns()
                ->whereIn('status', [Campaign::STATUS_PUBLISHED, Campaign::STATUS_SCHEDULED])
                ->count();

            if ($activeCount >= $maxCampaigns) {
                throw new ApiException('QUOTA_EXCEEDED', 'سقف کمپین فعال Plan شما پر شده است.', 403);
            }
        }

        $campaign->publish();

        return $campaign->refresh();
    }
}
