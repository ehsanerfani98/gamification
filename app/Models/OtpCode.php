<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['phone', 'purpose', 'code_hash', 'attempts', 'expires_at', 'consumed_at'])]
class OtpCode extends Model
{
    public const PURPOSE_AUTH = 'auth';

    public const PURPOSE_CUSTOMER_LOGIN = 'customer_login';

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    /** آخرین کد معتبر (مصرف‌نشده، منقضی‌نشده) برای شماره و purpose */
    public function scopeValid($query, string $phone, string $purpose = self::PURPOSE_AUTH)
    {
        return $query->where('phone', $phone)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->latest('id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function hasAttemptsLeft(int $max): bool
    {
        return $this->attempts < $max;
    }

    /** مصرف اتمی کد — فقط اگر هنوز مصرف نشده باشد (دفاع در برابر مصرف هم‌زمان) */
    public function consume(): bool
    {
        return (bool) static::query()
            ->whereKey($this->getKey())
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);
    }
}
