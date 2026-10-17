<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AiModelResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'name' => $this->name,
            'family' => $this->family,
            'context_window' => $this->context_window,
            'max_output' => $this->max_output,
            'pricing' => $this->pricing ?? [],
            'enabled' => $this->enabled,
            'priority' => $this->priority,
            'provider' => [
                'id' => $this->provider->id,
                'key' => $this->provider->key,
                'name' => $this->provider->name,
                'status' => $this->provider->status->value,
                'configured' => $this->provider->runtimeConfigured(),
            ],
            'capabilities' => $this->whenLoaded(
                'capabilities',
                fn () => $this->capabilities->map(fn ($capability) => $capability->capability->value)->values(),
            ),
        ];
    }
}
