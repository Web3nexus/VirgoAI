<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'generation_id', 'title', 'brief', 'length_seconds', 'format', 'quality', 'styles', 'status', 'error', 'plan', 'output_path', 'planning_price', 'planning_cost', 'media_deleted_at'])]
class Film extends Model
{
    use HasUlids;

    public const TERMINAL = ['succeeded', 'failed'];

    protected function casts(): array
    {
        return [
            'styles' => 'array',
            'plan' => 'array',
            'length_seconds' => 'integer',
            'planning_price' => 'integer',
            'planning_cost' => 'integer',
            'media_deleted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(FilmAsset::class)->orderBy('kind')->orderBy('key');
    }

    public function takes(): HasMany
    {
        return $this->hasMany(FilmTake::class)->orderBy('position');
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL, true);
    }
}
