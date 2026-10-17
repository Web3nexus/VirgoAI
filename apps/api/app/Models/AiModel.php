<?php

namespace App\Models;

use App\Enums\Capability;
use Database\Factories\AiModelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['ai_provider_id', 'key', 'name', 'family', 'context_window', 'max_output', 'pricing', 'meta', 'enabled', 'priority'])]
class AiModel extends Model
{
    /** @use HasFactory<AiModelFactory> */
    use HasFactory, HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pricing' => 'array',
            'meta' => 'array',
            'enabled' => 'boolean',
            'context_window' => 'integer',
            'max_output' => 'integer',
            'priority' => 'integer',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(AiProvider::class, 'ai_provider_id');
    }

    public function capabilities(): HasMany
    {
        return $this->hasMany(ProviderCapability::class);
    }

    public function supports(Capability $capability): bool
    {
        return $this->capabilities()->where('capability', $capability->value)->exists();
    }

    public function isUsable(): bool
    {
        return $this->enabled && $this->provider->isUsable();
    }
}
