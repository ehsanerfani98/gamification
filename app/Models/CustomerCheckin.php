<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToStore;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * چک‌این روزانه مشتری — فصل ۱۰ (Retention).
 * Streak: اگر چک‌این دیروز ثبت شده باشد streak + ۱ می‌شود؛ در غیر این
 * صورت به ۱ بازمی‌گردد. یکتایی (customer, checkin_date) جایزه تکراری را
 * از نظر ساختاری غیرممکن می‌کند.
 */
#[Fillable(['store_id', 'customer_id', 'checkin_date', 'streak', 'points_awarded', 'created_at'])]
class CustomerCheckin extends Model
{
    use BelongsToStore;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'checkin_date' => 'date',
            'streak' => 'integer',
            'points_awarded' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
