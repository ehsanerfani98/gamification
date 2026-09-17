<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['reward_id', 'remaining_qty'])]
class RewardInventory extends Model
{
    protected $table = 'reward_inventory';

    protected function casts(): array
    {
        return [
            'remaining_qty' => 'integer',
        ];
    }

    public function reward(): BelongsTo
    {
        return $this->belongsTo(Reward::class);
    }
}
