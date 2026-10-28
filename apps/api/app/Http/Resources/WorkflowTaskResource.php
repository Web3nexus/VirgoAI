<?php

namespace App\Http\Resources;

use App\Enums\AutomationMode;
use App\Enums\TaskStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkflowTaskResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'stage' => $this->stage,
            'name' => $this->name,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'position' => $this->position,
            'approval_policy' => $this->approval_policy->value,
            // Effective for this run's mode: full handoff shows Automatic for every stage.
            'requires_approval' => $this->approval_policy->requiresApproval(
                $this->workflow?->mode ?? AutomationMode::Guided,
                (bool) ($this->input['user_configured'] ?? false),
            ),
            'attempts' => $this->attempts,
            'prompt' => $this->input['prompt'] ?? null,
            // The server decides whether instructions can be changed, so the page does not guess.
            'prompt_editable' => $this->handler === 'model' && $this->status === TaskStatus::Pending,
            'output' => $this->output,
            'error' => $this->error,
            'started_at' => $this->started_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
        ];
    }
}
