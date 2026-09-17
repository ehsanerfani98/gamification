<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'sort'])]
class GameCategory extends Model
{
    public function games(): HasMany
    {
        return $this->hasMany(Game::class);
    }
}
