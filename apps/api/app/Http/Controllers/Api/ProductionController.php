<?php

namespace App\Http\Controllers\Api;

use App\Billing\InsufficientCredit;
use App\Billing\Money;
use App\Billing\PriceUnavailable;
use App\Billing\UsageRecorder;
use App\Http\Controllers\Controller;
use App\Media\MediaRemover;
use App\Models\Generation;
use App\Models\Production;
use App\Models\ProductionScene;
use App\Models\Project;
use App\Productions\ProductionService;
use App\Skills\SkillLibrary;
use App\Uploads\OwnedReference;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Full videos: start one, watch it progress, and get the finished file with its tokens.
 */
class ProductionController extends Controller
{
    public function __construct(
        protected ProductionService $service,
        protected UsageRecorder $usage,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $productions = Production::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('media_deleted_at')
            ->latest('created_at')
            ->limit(30)
            ->get();

        return response()->json(['data' => $productions->map(fn (Production $p) => $this->present($p, false))->values()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'prompt' => ['required', 'string', 'min:10', 'max:4000'],
            'format' => ['nullable', 'string', Rule::in(array_keys(Project::FORMATS))],
            'quality' => ['required', 'string', Rule::in(['720p', '1080p'])],
            'styles' => ['nullable', 'array'],
            'styles.environment' => ['nullable', 'string'],
            'styles.character' => ['nullable', 'string'],
            'styles.look' => ['nullable', 'string'],
            'skill_key' => ['nullable', 'string', Rule::in(SkillLibrary::keys())],
            'reference_url' => ['nullable', 'string', 'max:2048', new OwnedReference],
        ]);

        try {
            // A selected skill decides the shape its video is made in. Without one, the request's format is used.
            $format = isset($data['skill_key'])
                ? SkillLibrary::get($data['skill_key'])['defaults']['format'] ?? Project::DEFAULT_FORMAT
                : ($data['format'] ?? Project::DEFAULT_FORMAT);
            $production = $this->service->start($request->user(), $data['prompt'], $format, $data['quality'], $data['styles'] ?? null, ProductionService::TEXT_MODEL, $data['skill_key'] ?? null, $data['reference_url'] ?? null);
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['prompt' => $e->getMessage()]);
        } catch (PriceUnavailable $e) {
            throw ValidationException::withMessages(['prompt' => $e->getMessage()]);
        } catch (InsufficientCredit $e) {
            return response()->json(['message' => $e->getMessage()], 402);
        }

        return response()->json($this->present($production->refresh(), true), 201);
    }

    public function show(Request $request, string $production): JsonResponse
    {
        $record = Production::query()->where('user_id', $request->user()->id)->whereKey($production)->with('scenes')->firstOrFail();

        return response()->json($this->present($record, true));
    }

    /** Delete a full video's files. The record stays so its cost is still on the books. */
    public function destroy(Request $request, string $production): JsonResponse
    {
        $record = Production::query()->where('user_id', $request->user()->id)->whereKey($production)->firstOrFail();

        try {
            app(MediaRemover::class)->removeProduction($record);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json(null, 204);
    }

    /** @return array<string, mixed> */
    private function present(Production $production, bool $detailed): array
    {
        $format = Project::FORMATS[$production->format] ?? Project::FORMATS[Project::DEFAULT_FORMAT];
        $generation = Generation::query()->find($production->generation_id);

        $data = [
            'id' => $production->id,
            'prompt' => $production->prompt,
            'status' => $production->status,
            'error' => $production->error,
            'format' => $production->format,
            'format_label' => $format['label'],
            'width' => $format['width'],
            'height' => $format['height'],
            'quality' => $production->quality,
            'output_url' => $production->output_path ? Storage::disk('public')->url($production->output_path) : null,
            'reserved_tokens' => $generation ? Money::toTokens((int) $generation->price_micros) : null,
            'created_at' => $production->created_at?->toIso8601String(),
        ];

        if ($detailed) {
            $data['script'] = $production->script ? ['title' => $production->script['title'] ?? null, 'logline' => $production->script['logline'] ?? null] : null;
            $data['scenes'] = $production->scenes->map(fn (ProductionScene $scene) => [
                'position' => $scene->position,
                'title' => $scene->title,
                'status' => $scene->status,
                'keyframe_url' => $scene->keyframe_path ? Storage::disk('public')->url($scene->keyframe_path) : null,
                'clip_url' => $scene->clip_path ? Storage::disk('public')->url($scene->clip_path) : null,
                'error' => $scene->error,
            ])->values();
            $data['usage'] = $this->usage->userSummary(UsageRecorder::TYPE_PRODUCTION, $production->id);
        }

        return $data;
    }
}
