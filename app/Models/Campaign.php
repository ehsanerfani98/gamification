<?php

namespace App\Models;

use App\Domain\Campaign\Events\CampaignPublished;
use App\Support\Tenancy\BelongsToStore;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['store_id', 'game_id', 'title', 'slug', 'status', 'starts_at', 'ends_at', 'theme'])]
class Campaign extends Model
{
    use BelongsToStore;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_ARCHIVED = 'archived';

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'theme' => 'array',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function configuration(): HasOne
    {
        return $this->hasOne(GameConfiguration::class);
    }

    public function rules(): HasMany
    {
        return $this->hasMany(CampaignRule::class);
    }

    public function participations(): HasMany
    {
        return $this->hasMany(CampaignParticipation::class);
    }

    public function scopePlayable($query)
    {
        return $query->where('status', self::STATUS_PUBLISHED)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }

    public function isPlayable(): bool
    {
        return $this->status === self::STATUS_PUBLISHED
            && ($this->starts_at === null || $this->starts_at->isPast())
            && ($this->ends_at === null || $this->ends_at->isFuture());
    }

    /*
    |--------------------------------------------------------------------------
    | ماشین حالت کمپین — فصل ۱-۳ و ۴-۳. هر گذار Idempotent است.
    |--------------------------------------------------------------------------
    */

    public function publish(): void
    {
        if ($this->status === self::STATUS_PUBLISHED) {
            return;
        }

        $from = $this->status;
        $this->forceFill([
            'status' => self::STATUS_PUBLISHED,
            'starts_at' => $this->starts_at ?? now(),
        ])->save();

        CampaignPublished::dispatch($this, $from);
    }

    public function expire(): void
    {
        if ($this->status === self::STATUS_EXPIRED) {
            return;
        }

        $this->forceFill(['status' => self::STATUS_EXPIRED])->save();
    }

    public function archive(): void
    {
        $this->forceFill(['status' => self::STATUS_ARCHIVED])->save();
    }
}
