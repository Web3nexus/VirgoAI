<?php

namespace App\Http\Controllers\Api;

use App\Billing\InsufficientCredit;
use App\Billing\PriceUnavailable;
use App\Films\FilmService;
use App\Http\Controllers\Controller;
use App\Models\Film;
use App\Models\FilmAsset;
use App\Models\FilmTake;
use App\Models\Project;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The Director Lab: start a film, follow its board and chapters, and get the finished file.
 */
class FilmController extends Controller
{
    public function __construct(protected FilmService $films) {}

    public function index(Request $request): JsonResponse
    {
        $films = Film::query()
            ->where('user_id', $request->user()->id)
            ->latest('created_at')
            ->limit(30)
            ->get();

        return response()->json(['data' => $films->map(fn (Film $film) => $this->present($film, false))->values()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'brief' => ['required', 'string', 'min:20', 'max:4000'],
            'title' => ['nullable', 'string', 'max:200'],
            'length_seconds' => ['required', 'integer', 'min:'.(int) config('virgoai.films.min_length_seconds', 15), 'max:'.(int) config('virgoai.films.max_length_seconds', 3600)],
            'format' => ['required', 'string', Rule::in(array_keys(Project::FORMATS))],
            'quality' => ['required', 'string', Rule::in(['720p', '1080p'])],
            'styles' => ['nullable', 'array'],
            'styles.environment' => ['nullable', 'string'],
            'styles.character' => ['nullable', 'string'],
            'styles.look' => ['nullable', 'string'],
        ]);

        try {
            $film = $this->films->start(
                $request->user(),
                $data['brief'],
                (int) $data['length_seconds'],
                $data['format'],
                $data['quality'],
                $data['styles'] ?? null,
                $data['title'] ?? null,
            );
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['brief' => $e->getMessage()]);
        } catch (PriceUnavailable $e) {
            throw ValidationException::withMessages(['brief' => $e->getMessage()]);
        } catch (InsufficientCredit $e) {
            return response()->json(['message' => $e->getMessage()], 402);
        }

        return response()->json($this->present($film->refresh(), true), 201);
    }

    public function show(Request $request, string $film): JsonResponse
    {
        $record = Film::query()->where('user_id', $request->user()->id)->whereKey($film)->firstOrFail();

        return response()->json($this->present($record, true));
    }

    public function cancel(Request $request, string $film): JsonResponse
    {
        $record = Film::query()->where('user_id', $request->user()->id)->whereKey($film)->firstOrFail();

        try {
            $this->films->cancel($record);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json($this->present($record->refresh(), true));
    }

    /** @return array<string, mixed> */
    private function present(Film $film, bool $detailed): array
    {
        $format = Project::FORMATS[$film->format] ?? Project::FORMATS[Project::DEFAULT_FORMAT];
        $takes = $film->takes()->get(['status']);
        $done = $takes->where('status', 'done')->count();

        $data = [
            'id' => $film->id,
            'title' => $film->title,
            'brief' => $film->brief,
            'status' => $film->status,
            'error' => $film->error,
            'length_seconds' => $film->length_seconds,
            'format' => $film->format,
            'format_label' => $format['label'],
            'quality' => $film->quality,
            'styles' => $film->styles,
            'takes_total' => $takes->count(),
            'takes_done' => $done,
            'progress' => $takes->count() === 0 ? 0 : round($done / $takes->count(), 3),
            'output_url' => $film->output_path ? Storage::disk('public')->url($film->output_path) : null,
            'created_at' => $film->created_at?->toIso8601String(),
        ];

        if ($detailed) {
            $plan = $film->plan ?? [];

            $data['style'] = $plan['bible']['visual_style'] ?? null;
            $data['logline'] = $plan['bible']['logline'] ?? null;
            $data['story'] = $plan['bible']['story'] ?? null;
            $data['board'] = $film->assets->map(fn (FilmAsset $asset) => [
                'kind' => $asset->kind,
                'key' => $asset->key,
                'name' => $asset->name,
                'status' => $asset->status,
                'image_url' => $asset->image_url,
            ])->values();
            $data['chapters'] = collect($plan['chapters'] ?? [])->map(fn (array $chapter) => [
                'index' => $chapter['index'],
                'seconds' => $chapter['seconds'],
                'takes' => $chapter['takes'],
                'summary' => $chapter['summary'],
                'output_url' => $chapter['output_path'] ? Storage::disk('public')->url($chapter['output_path']) : null,
            ])->values();
            $data['takes'] = $film->takes->map(fn (FilmTake $take) => [
                'chapter' => $take->chapter,
                'position' => $take->position,
                'seconds' => $take->seconds,
                'status' => $take->status,
                'attempts' => $take->attempts,
                'error' => $take->error,
                'clip_url' => $take->clip_path ? Storage::disk('public')->url($take->clip_path) : null,
            ])->values();
            $data['pricing'] = [
                'reserved_tokens' => $this->reservedTokens($film),
                'spent_tokens' => round($this->films->submittedQuote($film)->priceMicros / 1000, 1),
            ];
        }

        return $data;
    }

    private function reservedTokens(Film $film): float
    {
        return round($this->films->reservedMicros($film) / 1000, 1);
    }
}
