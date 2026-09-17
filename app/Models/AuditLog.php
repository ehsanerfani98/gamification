<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'actor_type', 'action', 'subject_type', 'subject_id', 'ip', 'meta'])]
class AuditLog extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** ثبت Append-Only رخداد حساس — فصل ۲-۵ سند معماری */
    public static function record(
        string $action,
        ?User $actor = null,
        ?Model $subject = null,
        array $meta = [],
        ?string $ip = null,
        ?string $actorType = null,
    ): self {
        return static::create([
            'user_id' => $actor?->getKey(),
            'actor_type' => $actorType ?? $actor?->role ?? 'system',
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'ip' => $ip,
            'meta' => $meta ?: null,
            'created_at' => now(),
        ]);
    }
}
