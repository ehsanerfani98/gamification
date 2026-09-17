<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToStore;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * دعوت دوست — فصل ۱۰ (Referral).
 * هر مشتری دعوت‌شده در هر Store فقط یک‌بار شمرده می‌شود (unique:
 * store + invited)؛ جوایز پله‌ای ۱/۳/۵ دعوت در ReferralTierAwarder
 * هنگام عبور از هر آستانه یک‌بار پرداخت می‌شوند.
 */
#[Fillable(['store_id', 'referrer_id', 'invited_id', 'status', 'completed_at'])]
class Referral extends Model
{
    use BelongsToStore;

    public const STATUS_COMPLETED = 'completed';

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
        ];
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'referrer_id');
    }

    public function invited(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'invited_id');
    }
}
