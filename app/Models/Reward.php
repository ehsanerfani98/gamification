<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToStore;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['store_id', 'campaign_id', 'ref', 'type', 'name', 'params', 'weight', 'total_qty', 'is_active'])]
class Reward extends Model
{
    use BelongsToStore;

    public const TYPE_PERCENTAGE = 'percentage';

    public const TYPE_FIXED = 'fixed';

    public const TYPE_FREE_SHIPPING = 'free_shipping';

    public const TYPE_FREE_PRODUCT = 'free_product';

    public const TYPE_GIFT = 'gift';

    public const TYPE_POINTS = 'points';

    public const TYPE_STORE_COUPON = 'store_coupon';

    public const TYPE_CUSTOM = 'custom';

    public const TYPE_NONE = 'none';

    protected function casts(): array
    {
        return [
            'params' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function inventory(): HasOne
    {
        return $this->hasOne(RewardInventory::class);
    }

    /** موجودی باقیمانده؛ null یعنی بی‌نهایت */
    public function remainingQty(): ?int
    {
        if ($this->total_qty === null) {
            return null;
        }

        return (int) ($this->inventory?->remaining_qty ?? 0);
    }
}
