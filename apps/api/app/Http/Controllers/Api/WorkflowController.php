<?php

namespace App\Http\Controllers\Api;

use App\Enums\AutomationMode;
use App\Http\Controllers\Controller;
use App\Http\Requests\StartWorkflowRequest;
use App\Http\Resources\WorkflowResource;
use App\Models\Project;
use App\Models\Workflow;
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
            ->with(['tasks', 'approvalRequests'])
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

    protected function load(Workflow $workflow): Workflow
    {
        return $workflow->load(['tasks', 'approvalRequests']);
    }
}
