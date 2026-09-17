<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['campaign_id', 'type', 'value'])]
class CampaignRule extends Model
{
    public const TYPE_ONCE_DAILY = 'once_daily';

    public const TYPE_NEW_CUSTOMER_ONLY = 'new_customer_only';

    public const TYPE_MAX_TOTAL_PLAYS = 'max_total_plays';

    public const TYPE_MAX_DAILY_WINS = 'max_daily_wins';

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
