<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['game_category_id', 'code', 'name', 'description', 'is_active', 'supported_reward_types', 'sort'])]
class Game extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'supported_reward_types' => 'array',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(GameCategory::class, 'game_category_id');
    }

    /** Planهای دارای دسترسی به این بازی — Feature Gating داده‌محور (فصل ۷-۱) */
    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(Plan::class, 'plan_game');
    }
}
