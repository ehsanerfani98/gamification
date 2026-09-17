<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'name', 'slug', 'status'])]
class Store extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    /** اشتراک فعال جاری — برای Feature Gating (فصل ۷-۱) */
    public function activeSubscription(): ?Subscription
    {
        return $this->subscriptions()
            ->active()
            ->latest('id')
            ->first();
    }
}
