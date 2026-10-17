<?php

namespace App\Workflows\Handlers;

use App\Ai\Data\GenerationRequest;
use App\Ai\GenerationService;
use App\Enums\Capability;
use App\Models\WorkflowTask;
use App\Workflows\StageHandler;
use RuntimeException;

/**
 * Generates a stage output by submitting the task to the model router / provider layer.
 *
 * This is the bridge between the workflow engine and real AI providers: the stage declares a
 * capability (and optional prompt), the router picks a configured model, and the result is
 * recorded against usage/cost. Requires at least one configured provider to run.
 */
class ModelStageHandler implements StageHandler
{
    public function __construct(protected GenerationService $generation) {}

    public function handle(WorkflowTask $task): array
    {
        $capability = Capability::tryFrom((string) ($task->input['capability'] ?? 'text')) ?? Capability::Text;
        $prompt = (string) ($task->input['prompt'] ?? $task->name);

        $result = $this->generation->generate(new GenerationRequest(
            capability: $capability,
            modelKey: '',
            prompt: $prompt,
            projectId: $task->workflow->project_id,
            workflowTaskId: $task->id,
        ));

        if (! $result->ok) {
            throw new RuntimeException($result->error ?? 'Generation failed.');
        }

        return [
            'provider' => $result->providerKey,
            'model' => $result->modelKey,
            'capability' => $capability->value,
            'output' => $result->output,
            'usage' => $result->usage,
        ];
    }
}
