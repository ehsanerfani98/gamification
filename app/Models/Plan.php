<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'price_irt', 'billing_period', 'features', 'is_active', 'sort'])]
class Plan extends Model
{
    protected function casts(): array
    {
        return [
            'features' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function games(): BelongsToMany
    {
        return $this->belongsToMany(Game::class, 'plan_game');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /** Plan رایگان — سطح بازگشتی Feature Gating پس از انقضای اشتراک (فصل ۷-۳) */
    public static function free(): ?self
    {
        return static::query()->where('slug', config('gamification.plans.default_slug'))->first();
    }
}
