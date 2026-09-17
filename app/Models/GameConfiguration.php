<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['campaign_id', 'schema_version', 'config'])]
class GameConfiguration extends Model
{
    protected function casts(): array
    {
        return [
            'config' => 'array',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /** پیکربندی عمومی و امن برای کلاینت — وزن‌ها و مرجع جوایز افشا نمی‌شود (فصل ۸-۱) */
    public function toPublicArray(): array
    {
        $config = $this->config ?? [];

        if (isset($config['segments']) && is_array($config['segments'])) {
            $config['segments'] = array_map(
                fn (array $segment) => [
                    'label' => $segment['label'] ?? '',
                    'color' => $segment['color'] ?? null,
                ],
                $config['segments'],
            );
        }

        unset($config['probability_mode']);

        return $config;
    }
}
