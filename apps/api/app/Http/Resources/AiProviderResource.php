<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AiProviderResource extends JsonResource
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
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'configured' => $this->runtimeConfigured(),
            'priority' => $this->priority,
            'models_count' => $this->whenCounted('models'),
        ];
    }
}
