<?php

namespace App\Http\Controllers\Api;

use App\Enums\AutomationMode;
use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StartWorkflowRequest;
use App\Http\Resources\WorkflowResource;
use App\Models\Project;
use App\Models\Workflow;
use App\Models\WorkflowTask;
use App\Workflows\WorkflowEngine;
use App\Workflows\WorkflowTemplates;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class WorkflowController extends Controller
{
    use AuthorizesRequests;

    public function __construct(protected WorkflowEngine $engine) {}

    /**
     * Show the latest workflow for a project (with tasks and approval checkpoints).
     */
    public function show(Request $request, Project $project): WorkflowResource
    {
        $this->authorize('view', $project);

        $workflow = $project->workflows()
            ->with(['tasks', 'dependencies', 'approvalRequests'])
            ->latest()
            ->first();

        abort_if($workflow === null, 404, 'No workflow has been started for this project.');

        return new WorkflowResource($workflow);
    }

    /**
     * Start a new workflow run for a project.
     */
    public function start(StartWorkflowRequest $request, Project $project): WorkflowResource
    {
        $this->authorize('update', $project);

        $workflow = $this->engine->start(
            $project,
            $request->string('template_key')->toString() ?: WorkflowTemplates::DEFAULT,
            $request->enum('mode', AutomationMode::class) ?? AutomationMode::Guided,
        );

        return new WorkflowResource($this->load($workflow));
    }

    public function pause(Workflow $workflow): WorkflowResource
    {
        $this->authorize('update', $workflow);

        return new WorkflowResource($this->load($this->engine->pause($workflow)));
    }

    public function resume(Workflow $workflow): WorkflowResource
    {
        $this->authorize('update', $workflow);

        return new WorkflowResource($this->load($this->engine->resume($workflow)));
    }

    public function cancel(Workflow $workflow): WorkflowResource
    {
        $this->authorize('update', $workflow);

        return new WorkflowResource($this->load($this->engine->cancel($workflow)));
    }

    /**
     * Set the instructions for one stage of a run. Only a stage that has not started can be changed,
     * and only a stage that runs a model takes instructions. An empty prompt restores the default.
     */
    public function updateTask(Request $request, Workflow $workflow, WorkflowTask $task): WorkflowResource
    {
        $this->authorize('update', $workflow);

        abort_unless($task->workflow_id === $workflow->id, 404);
        abort_unless($task->handler === 'model', 422, 'This stage does not take instructions.');

        $data = $request->validate([
            'prompt' => ['nullable', 'string', 'max:4000'],
        ]);

        $input = $task->input ?? [];
        $prompt = trim((string) ($data['prompt'] ?? ''));

        // An empty prompt puts back the template's own instructions for this stage.
        $defaultPrompt = collect(WorkflowTemplates::get($workflow->template_key)->stages)
            ->firstWhere('key', $task->key)?->prompt;

        if ($prompt !== '') {
            $input['prompt'] = $prompt;
        } elseif ($defaultPrompt !== null) {
            $input['prompt'] = $defaultPrompt;
        } else {
            unset($input['prompt']);
        }

        // The status is part of the WHERE clause so the change cannot land after the stage has been queued.
        $updated = WorkflowTask::query()
            ->whereKey($task->id)
            ->where('status', TaskStatus::Pending->value)
            ->update(['input' => $input === [] ? null : $input]);

        abort_unless($updated === 1, 409, 'This stage has already started, so its instructions are locked.');

        return new WorkflowResource($this->load($workflow));
    }

    protected function load(Workflow $workflow): Workflow
    {
        return $workflow->load(['tasks', 'dependencies', 'approvalRequests']);
    }
}
