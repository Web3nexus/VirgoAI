<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProjectController extends Controller
{
    use AuthorizesRequests;

    /**
     * List the authenticated user's projects.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Project::class);

        $projects = Project::query()
            ->where('user_id', $request->user()->id)
            ->when($request->string('status')->toString(), fn ($q, $status) => $q->where('status', $status))
            ->when($request->string('type')->toString(), fn ($q, $type) => $q->where('type', $type))
            ->when($request->string('search')->toString(), fn ($q, $search) => $q->where('title', 'ilike', "%{$search}%"))
            ->latest()
            ->paginate(perPage: (int) $request->integer('per_page', 15))
            ->withQueryString();

        return ProjectResource::collection($projects);
    }

    /**
     * Create a new project for the authenticated user.
     */
    public function store(StoreProjectRequest $request): JsonResponse
    {
        $this->authorize('create', Project::class);

        $project = $request->user()->projects()->create([
            'title' => $request->string('title')->toString(),
            'description' => $request->string('description')->toString() ?: null,
            'type' => $request->string('type')->toString(),
            'format' => $request->string('format')->toString() ?: Project::DEFAULT_FORMAT,
            'status' => 'draft',
            'settings' => $request->input('settings', []),
        ]);

        return (new ProjectResource($project))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show a single project.
     */
    public function show(Project $project): ProjectResource
    {
        $this->authorize('view', $project);

        return new ProjectResource($project);
    }

    /**
     * Update a project.
     */
    public function update(UpdateProjectRequest $request, Project $project): ProjectResource
    {
        $this->authorize('update', $project);

        $project->fill($request->validated())->save();

        return new ProjectResource($project);
    }

    /**
     * Soft-delete a project.
     */
    public function destroy(Project $project): JsonResponse
    {
        $this->authorize('delete', $project);

        $project->delete();

        return response()->json(status: 204);
    }
}
