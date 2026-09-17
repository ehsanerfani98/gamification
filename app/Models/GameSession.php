<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToStore;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * هر اجرای بازی یک Session — فصل ۵-۴ سند معماری.
 * نتیجه بازی فقط همین‌جا و فقط سمت سرور نوشته می‌شود؛ کلاینت هیچ مسیری
 * برای ارسال «نتیجه» ندارد.
 */
#[Fillable(['store_id', 'campaign_id', 'customer_id', 'play_token', 'status', 'result', 'result_signature', 'idempotency_key', 'started_at', 'token_expires_at', 'completed_at'])]
class GameSession extends Model
{
    use BelongsToStore;

    public const STATUS_STARTED = 'started';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_EXPIRED = 'expired';

    protected function casts(): array
    {
        return [
            'result' => 'array',
            'started_at' => 'datetime',
            'token_expires_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function isExpired(): bool
    {
        return $this->token_expires_at !== null && $this->token_expires_at->isPast();
    }
}
