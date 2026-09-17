<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToStore;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * تراکنش Ledger امتیاز — فصل ۳-۴ و ۶-۴.
 * موجودی = Σ(delta) تراکنش‌ها؛ Append-Only و بدون updated_at.
 */
#[Fillable(['store_id', 'customer_id', 'point_account_id', 'delta', 'type', 'reference_type', 'reference_id', 'description'])]
class PointTransaction extends Model
{
    use BelongsToStore;

    public $timestamps = false;

    public const TYPE_EARN = 'earn';

    public const TYPE_REDEEM = 'redeem';

    public const TYPE_EXPIRE = 'expire';

    public const TYPE_ADJUST = 'adjust';

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(PointAccount::class, 'point_account_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
