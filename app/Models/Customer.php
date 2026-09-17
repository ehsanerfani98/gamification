<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToStore;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * مشتری نهایی — کاربری که از اینستاگرام وارد کمپین می‌شود؛ این کاربر
 * نیازی به نصب اپلیکیشن ندارد و فقط با توکن محدودِ دامنه‌دار کار می‌کند.
 * موبایل در قلمرو هر Store یکتاست (چنداجارگی — فصل ۳-۴).
 */
#[Fillable(['store_id', 'phone', 'name', 'referral_code', 'referred_by', 'last_seen_at'])]
class Customer extends Authenticatable
{
    use BelongsToStore, HasApiTokens;

    public $timestamps = true;

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** Store مالک این مشتری — برای جریان‌های احراز هویت */
    public function participations()
    {
        return $this->hasMany(CampaignParticipation::class);
    }

    public function markSeen(): void
    {
        $this->forceFill(['last_seen_at' => now()])->save();
    }
}
