<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToStore;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * آمار روزانه کمپین — خروجی تجمیع شبانه (analytics:aggregate).
 * این جدول برای نمودارها و گزارش‌های بلندمدت استفاده می‌شود؛
 * رکوردهای آن Idempotent بازمحاسبه می‌شوند (unique: campaign, stat_date).
 */
#[Fillable(['store_id', 'campaign_id', 'stat_date', 'views', 'enters', 'plays', 'wins', 'redeems', 'checkins', 'unique_customers'])]
class CampaignDailyStat extends Model
{
    use BelongsToStore;

    protected function casts(): array
    {
        return [
            'stat_date' => 'date',
            'views' => 'integer',
            'enters' => 'integer',
            'plays' => 'integer',
            'wins' => 'integer',
            'redeems' => 'integer',
            'checkins' => 'integer',
            'unique_customers' => 'integer',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
