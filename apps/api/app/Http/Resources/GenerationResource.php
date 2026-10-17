<?php

namespace App\Http\Resources;

use App\Billing\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class GenerationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tool' => $this->tool,
            'capability' => $this->capability,
            'provider_key' => $this->provider_key,
            'model_key' => $this->model_key,
            'project_id' => $this->project_id,
            'prompt' => $this->prompt,
            'options' => $this->options ?? [],
            'status' => $this->status,
            'output_type' => $this->output_type,
            'output_text' => $this->output_text,
            'output_url' => $this->output_path ? Storage::disk('public')->url($this->output_path) : null,
            // The provider's own link to the finished file. It can feed the next run, such as a keyframe for video.
            'provider_url' => $this->provider_job['output_url'] ?? null,
            'error' => $this->error,
            'cost_usd' => $this->cost_usd,
            'price_usd' => Money::toUsd((int) $this->price_micros),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
