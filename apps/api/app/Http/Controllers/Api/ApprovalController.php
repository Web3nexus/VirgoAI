<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DecideApprovalRequest;
use App\Http\Resources\WorkflowResource;
use App\Models\ApprovalRequest;
use App\Workflows\ApprovalService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class ApprovalController extends Controller
{
    use AuthorizesRequests;

    public function __construct(protected ApprovalService $approvals) {}

    public function approve(DecideApprovalRequest $request, ApprovalRequest $approval): WorkflowResource
    {
        $this->authorize('decide', $approval);

        $this->approvals->approve($approval, $request->user(), $request->string('notes')->toString() ?: null);

        return $this->workflow($approval);
    }

    public function revise(DecideApprovalRequest $request, ApprovalRequest $approval): WorkflowResource
    {
        $this->authorize('decide', $approval);

        $this->approvals->revise(
            $approval,
            $request->user(),
            $request->array('revision'),
            $request->string('notes')->toString() ?: null,
        );

        return $this->workflow($approval);
    }

    public function regenerate(DecideApprovalRequest $request, ApprovalRequest $approval): WorkflowResource
    {
        $this->authorize('decide', $approval);

        $this->approvals->regenerate($approval, $request->user(), $request->string('notes')->toString() ?: null);

        return $this->workflow($approval);
    }

    public function reject(DecideApprovalRequest $request, ApprovalRequest $approval): WorkflowResource
    {
        $this->authorize('decide', $approval);

        $this->approvals->reject($approval, $request->user(), $request->string('notes')->toString() ?: null);

        return $this->workflow($approval);
    }

    protected function workflow(ApprovalRequest $approval): WorkflowResource
    {
        $workflow = $approval->workflow()->firstOrFail();

        return new WorkflowResource($workflow->load(['tasks', 'approvalRequests']));
    }
}
