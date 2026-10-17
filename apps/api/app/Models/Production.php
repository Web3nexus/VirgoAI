<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'prompt', 'format', 'quality', 'styles', 'skill_key', 'reference_url', 'model_key', 'status', 'error', 'output_path', 'generation_id', 'script', 'script_price', 'script_cost'])]
class Production extends Model
{
    use HasUlids;

    public const TERMINAL = ['succeeded', 'failed'];

    protected function casts(): array
    {
        return ['styles' => 'array', 'script' => 'array', 'script_price' => 'integer', 'script_cost' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scenes(): HasMany
    {
        return $this->hasMany(ProductionScene::class)->orderBy('position');
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL, true);
    }
}
