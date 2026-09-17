<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToStore;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['store_id', 'campaign_id', 'reward_id', 'customer_id', 'code', 'type', 'value', 'expires_at', 'status', 'redeemed_at'])]
class Coupon extends Model
{
    use BelongsToStore;

    public const STATUS_ISSUED = 'issued';

    public const STATUS_REDEEMED = 'redeemed';

    public const STATUS_EXPIRED = 'expired';

    protected function casts(): array
    {
        return [
            'value' => 'array',
            'expires_at' => 'datetime',
            'redeemed_at' => 'datetime',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function reward(): BelongsTo
    {
        return $this->belongsTo(Reward::class);
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    public function isValid(): bool
    {
        return $this->status === self::STATUS_ISSUED
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
