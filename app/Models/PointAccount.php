<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToStore;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['store_id', 'customer_id'])]
class PointAccount extends Model
{
    use BelongsToStore;

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
