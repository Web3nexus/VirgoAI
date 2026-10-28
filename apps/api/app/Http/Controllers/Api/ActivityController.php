<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\WorkflowResource;
use App\Models\Workflow;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ActivityController extends Controller
{
    use AuthorizesRequests;

    /**
     * Recent workflow activity across the signed-in user's projects, newest first.
     * Each workflow carries its tasks (with timestamps, attempts, output) and approval checkpoints.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $limit = min(max((int) $request->query('limit', 30), 1), 100);

        $workflows = Workflow::query()
            ->whereHas('project', fn ($query) => $query->where('user_id', $request->user()->id))
            ->with(['project:id,title', 'tasks', 'dependencies', 'approvalRequests'])
            ->latest('updated_at')
            ->limit($limit)
            ->get();

        return WorkflowResource::collection($workflows);
    }
}
