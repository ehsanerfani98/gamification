<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToStore;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * رخداد رفتاری Append-Only — فصل ۴-۳ و ۱۰ سند معماری.
 *
 * تنها منبع ساخت این رکوردها Listenerهای Analytics هستند (AnalyticsTracker)؛
 * هیچ مسیری مجاز به ویرایش رکورد نیست (updating مسدود است) و پاک‌سازی فقط
 * پس از گذشت دوره نگهداری با دستور analytics:aggregate انجام می‌شود.
 */
#[Fillable(['store_id', 'campaign_id', 'customer_id', 'game_session_id', 'name', 'properties', 'occurred_at', 'created_at'])]
class AnalyticsEvent extends Model
{
    use BelongsToStore;

    public const NAME_VIEW = 'view';

    public const NAME_ENTER = 'enter';

    public const NAME_PLAY = 'play';

    public const NAME_WIN = 'win';

    public const NAME_REDEEM = 'redeem';

    public const NAME_CHECKIN = 'checkin';

    public const NAME_REFERRAL = 'referral';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'occurred_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Append-Only: ویرایش رکورد ممنوع — فصل ۳-۴
        static::updating(function (): bool {
            return false;
        });
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** حذف فقط برای پاک‌سازی دوره نگهداری مجاز است — در Action اختصاصی استفاده می‌شود */
    public function scopePrunable(Builder $query, $before): Builder
    {
        return $query->where('occurred_at', '<', $before);
    }
}
