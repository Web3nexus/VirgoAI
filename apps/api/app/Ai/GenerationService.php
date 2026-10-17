<?php

namespace App\Ai;

use App\Ai\Data\GenerationRequest;
use App\Ai\Data\GenerationResult;
use App\Ai\Exceptions\NoSuitableModel;
use App\Models\AiUsageRecord;
use Throwable;

/**
 * The single entry point for submitting a generation: routes the capability to a configured
 * model, invokes the provider adapter, and records usage/cost. Callers never touch adapters
 * directly, so provider fallback, budgets, and analytics can evolve in one place.
 */
class GenerationService
{
    public function __construct(protected ModelRouter $router) {}

    public function generate(GenerationRequest $request): GenerationResult
    {
        try {
            $routed = $request->requestedModelKey
                ? $this->router->routeTo($request->capability, $request->requestedModelKey)
                : $this->router->route($request->capability);
        } catch (NoSuitableModel $e) {
            return GenerationResult::failure($e->getMessage());
        }

        $adapter = $this->router->adapterFor($routed);
        $resolved = $request->withModelKey($routed->modelKey());

        $record = AiUsageRecord::create([
            'project_id' => $request->projectId,
            'workflow_task_id' => $request->workflowTaskId,
            'ai_provider_id' => $routed->provider->id,
            'ai_model_id' => $routed->model->id,
            'capability' => $request->capability->value,
            'status' => 'pending',
            'idempotency_key' => $request->idempotencyKey,
        ]);

        try {
            $result = $adapter->generate($resolved);
        } catch (Throwable $e) {
            $record->update(['status' => 'failed', 'error' => $e->getMessage()]);

            return GenerationResult::failure($e->getMessage(), $routed->providerKey(), $routed->modelKey());
        }

        // A queued job is finished later by GenerationCompleter, which closes this usage record.
        if ($result->isQueued()) {
            return $result->withJob([...$result->job, 'usage_record_id' => $record->id]);
        }

        $record->update([
            'status' => $result->ok ? 'succeeded' : 'failed',
            'cost_usd' => $result->costUsd,
            'usage' => $result->usage,
            'error' => $result->error,
        ]);

        return $result;
    }
}
