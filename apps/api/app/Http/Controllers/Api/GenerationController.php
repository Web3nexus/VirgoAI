<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\GenerationResource;
use App\Media\MediaRemover;
use App\Models\Generation;
use App\Tools\ToolCatalog;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class GenerationController extends Controller
{
    /**
     * One saved run. The studio polls this while a queued render is still running.
     */
    public function show(Request $request, string $generation): GenerationResource
    {
        $run = Generation::query()
            ->where('user_id', $request->user()->id)
            ->whereKey($generation)
            ->firstOrFail();

        return new GenerationResource($run);
    }

    /** Delete a run's image or video file. The run and its cost stay on record. */
    public function destroy(Request $request, string $generation): JsonResponse
    {
        $run = Generation::query()->where('user_id', $request->user()->id)->whereKey($generation)->firstOrFail();

        try {
            app(MediaRemover::class)->removeGeneration($run);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json(null, 204);
    }

    /**
     * The signed-in user's saved tool runs, newest first. Optionally filtered by tool.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate([
            'tool' => ['nullable', 'string', Rule::in(array_keys(ToolCatalog::TOOLS))],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $generations = Generation::query()
            ->where('user_id', $request->user()->id)
            ->when($data['tool'] ?? null, fn ($query, $tool) => $query->where('tool', $tool))
            ->latest('created_at')
            ->limit($data['limit'] ?? 30)
            ->get();

        return GenerationResource::collection($generations);
    }
}
