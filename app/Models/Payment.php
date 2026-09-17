<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['store_id', 'plan_id', 'amount_irt', 'gateway', 'reference', 'status', 'paid_at', 'meta'])]
class Payment extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_FAILED = 'failed';

    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
            'meta' => 'array',
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

    public function markPaid(): void
    {
        $this->forceFill([
            'status' => self::STATUS_PAID,
            'paid_at' => now(),
        ])->save();
    }

    public function markFailed(): void
    {
        $this->forceFill(['status' => self::STATUS_FAILED])->save();
    }
}
