<?php

namespace App\Workflows\Handlers;

use App\Models\WorkflowTask;
use App\Workflows\StageHandler;

/**
 * Placeholder handler for the built-in test pipeline.
 *
 * It produces deterministic, self-describing output so the workflow and approval engine can be
 * exercised end-to-end without any provider credentials. Real stages replace this with provider
 * adapters (Phase 2+). This handler never represents a production content generation.
 */
class TestStageHandler implements StageHandler
{
    public function handle(WorkflowTask $task): array
    {
        $dependencyOutputs = $task->dependsOn()->get()
            ->mapWithKeys(fn (WorkflowTask $dep) => [$dep->key => $dep->output])
            ->all();

        return [
            'stage' => $task->stage,
            'placeholder' => true,
            'summary' => "Placeholder output for the [{$task->stage}] stage. No content was generated.",
            'generated_at' => now()->toIso8601String(),
            'depends_on' => array_keys($dependencyOutputs),
        ];
    }
}
