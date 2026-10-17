<?php

namespace App\Models;

use App\Ai\AdapterRegistry;
use App\Enums\ProviderStatus;
use App\Settings\ProviderCredentials;
use Database\Factories\AiProviderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['key', 'name', 'status', 'credentials_key', 'priority', 'meta'])]
class AiProvider extends Model
{
    /** @use HasFactory<AiProviderFactory> */
    use HasFactory, HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProviderStatus::class,
            'meta' => 'array',
            'priority' => 'integer',
        ];
    }

    public function models(): HasMany
    {
        return $this->hasMany(AiModel::class);
    }

    /**
     * Whether a credential is declared for this provider. Never exposes the secret.
     */
    public function isConfigured(): bool
    {
        if ($this->credentials_key === null) {
            return false;
        }

        return ProviderCredentials::has($this->credentials_key);
    }

    /**
     * Whether the provider is actually usable right now (adapter registered and configured).
     */
    public function runtimeConfigured(): bool
    {
        $registry = app(AdapterRegistry::class);

        if ($registry->has($this->key)) {
            return $registry->resolve($this->key)->isConfigured();
        }

        return $this->isConfigured();
    }

    public function isUsable(): bool
    {
        return $this->status->isUsable();
    }
}
