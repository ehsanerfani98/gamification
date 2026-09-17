<?php

namespace App\Models;

use App\Domain\Subscription\Events\SubscriptionChanged;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['store_id', 'plan_id', 'status', 'starts_at', 'ends_at', 'canceled_at'])]
class Subscription extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAST_DUE = 'past_due';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_CANCELED = 'canceled';

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'canceled_at' => 'datetime',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && ($this->ends_at === null || $this->ends_at->isFuture());
    }

    /*
    |--------------------------------------------------------------------------
    | ماشین حالت — فصل ۷-۳. هر گذار Idempotent است و Event منتشر می‌کند.
    |--------------------------------------------------------------------------
    */

    public function activate(): void
    {
        $this->transition(self::STATUS_ACTIVE, [
            'starts_at' => $this->starts_at ?? now(),
            'canceled_at' => null,
        ]);
    }

    public function markPastDue(): void
    {
        $this->transition(self::STATUS_PAST_DUE);
    }

    public function expire(): void
    {
        $this->transition(self::STATUS_EXPIRED);
    }

    public function cancel(): void
    {
        $this->transition(self::STATUS_CANCELED, ['canceled_at' => now()]);
    }

    protected function transition(string $to, array $extra = []): void
    {
        $from = $this->status;

        if ($from === $to) {
            return; // Idempotent
        }

        $this->forceFill($extra + ['status' => $to])->save();

        SubscriptionChanged::dispatch($this, $from, $to);
    }
}
