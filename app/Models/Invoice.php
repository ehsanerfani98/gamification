<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['store_id', 'payment_id', 'number', 'amount_irt', 'issued_at'])]
class Invoice extends Model
{
    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** شماره فاکتور خوانا و یکتا: INV-YYYYMM-SEQ */
    public static function nextNumber(): string
    {
        $prefix = 'INV-'.now()->format('Ym').'-';
        $seq = (int) static::query()->where('number', 'like', $prefix.'%')->count() + 1;

        return $prefix.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }
}
