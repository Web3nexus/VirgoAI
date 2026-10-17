<?php

namespace App\Http\Resources;

use App\Enums\TaskStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkflowResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $progress = null;

        // Tasks read the run's mode to decide whether they need approval. Share this run
        // with them so the mode is not looked up once per task.
        if ($this->relationLoaded('tasks')) {
            $this->tasks->each(fn ($task) => $task->setRelation('workflow', $this->resource));
        }

        if ($this->relationLoaded('tasks')) {
            $total = $this->tasks->count();
            $completed = $this->tasks->where('status', TaskStatus::Completed)->count();

            $progress = [
                'total' => $total,
                'completed' => $completed,
                'percent' => $total > 0 ? (int) round($completed / $total * 100) : 0,
            ];
        }

        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'template_key' => $this->template_key,
            'mode' => $this->mode->value,
            'mode_label' => $this->mode->label(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'current_stage' => $this->current_stage,
            'started_at' => $this->started_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
            'paused_at' => $this->paused_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'progress' => $progress,
            'project' => $this->whenLoaded('project', fn () => [
                'id' => $this->project->id,
                'title' => $this->project->title,
            ]),
            'tasks' => WorkflowTaskResource::collection($this->whenLoaded('tasks')),
            'approvals' => ApprovalRequestResource::collection($this->whenLoaded('approvalRequests')),
        ];
    }
}
