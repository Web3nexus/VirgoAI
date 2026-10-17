<?php

namespace App\Http\Controllers\Api;

use App\Billing\InsufficientCredit;
use App\Billing\Money;
use App\Billing\PriceUnavailable;
use App\Clips\ClipService;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessClipRun;
use App\Jobs\ProcessClipSource;
use App\Models\ClipRun;
use App\Models\ClipSource;
use App\Models\Generation;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Long videos: upload one to clip it, or paste a YouTube link to watch it in full.
 */
class ClipController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $sources = ClipSource::query()
            ->where('user_id', $request->user()->id)
            ->latest('created_at')
            ->limit(30)
            ->get();

        return response()->json(['data' => $sources->map(fn (ClipSource $source) => $this->presentSource($source, false))->values()]);
    }

    /**
     * Upload a file, or add a YouTube link. The file is probed and transcribed in the background.
     */
    public function store(Request $request, ClipService $clips): JsonResponse
    {
        if ($request->filled('youtube_url')) {
            $data = $request->validate(['youtube_url' => ['required', 'string', 'max:500']]);

            try {
                $source = $clips->createYoutube($request->user(), $data['youtube_url']);
            } catch (DomainException $e) {
                throw ValidationException::withMessages(['youtube_url' => $e->getMessage()]);
            }

            return response()->json($this->presentSource($source, true), 201);
        }

        $maxKb = (int) config('virgoai.clips.max_upload_kb', 2097152);
        $request->validate([
            'file' => ['required', 'file', 'max:'.$maxKb, 'mimetypes:video/mp4,video/quicktime,video/webm,video/x-matroska'],
        ], ['file.mimetypes' => 'Upload an MP4, MOV, WebM or MKV video.']);

        $source = $clips->createUpload($request->user(), $request->file('file'));
        ProcessClipSource::dispatch($source->id);

        return response()->json($this->presentSource($source, false), 201);
    }

    public function show(Request $request, string $source): JsonResponse
    {
        return response()->json($this->presentSource($this->owned($request, $source), true));
    }

    /** Ask for clips from a transcribed upload. A YouTube source plays in full and cannot be clipped. */
    public function startRun(Request $request, string $source, ClipService $clips): JsonResponse
    {
        $clipSource = $this->owned($request, $source);

        if ($clipSource->status !== ClipSource::STATUS_READY) {
            throw ValidationException::withMessages(['source' => 'This video is not ready yet. Wait for its transcript.']);
        }

        // A YouTube video can have moments found in its transcript, but it is not cut: each moment plays
        // from YouTube's own player between two times.
        if ($clipSource->kind === ClipSource::KIND_YOUTUBE && ! is_array($clipSource->transcript)) {
            throw ValidationException::withMessages(['source' => 'Add the transcript first. Upload the captions (.srt or .vtt) for this video.']);
        }

        $max = (int) config('virgoai.clips.max_clips', 10);
        $data = $request->validate([
            'request' => ['required', 'string', 'min:3', 'max:1000'],
            'count' => ['required', 'integer', 'between:1,'.$max],
            'min_seconds' => ['required', 'integer', 'between:5,600'],
            'max_seconds' => ['required', 'integer', 'between:5,900', 'gte:min_seconds'],
            'model_key' => ['nullable', 'string', Rule::in(['claude-haiku-5-5', 'claude-sonnet-5-5', 'claude-opus-5-5'])],
        ]);

        $modelKey = $data['model_key'] ?? ClipService::TEXT_MODEL_DEFAULT;

        try {
            $run = $clips->requestRun($request->user(), $clipSource, $data['request'], $modelKey, $data['count'], $data['min_seconds'], $data['max_seconds']);
        } catch (InsufficientCredit $e) {
            return response()->json(['message' => $e->getMessage()], 402);
        } catch (PriceUnavailable $e) {
            throw ValidationException::withMessages(['model_key' => $e->getMessage()]);
        }

        ProcessClipRun::dispatch($run->id);

        return response()->json($this->presentRun($run->refresh()->load('clips')), 201);
    }

    /**
     * Add captions to a source: an SRT or WebVTT file, or the same text pasted. Used for YouTube videos, whose
     * audio we do not download, and for any video whose transcript you want to supply yourself.
     */
    public function importTranscript(Request $request, string $source, ClipService $clips): JsonResponse
    {
        $clipSource = $this->owned($request, $source);

        $data = $request->validate([
            'text' => ['nullable', 'string', 'max:2000000'],
            'file' => ['nullable', 'file', 'max:5120', 'mimes:srt,vtt,txt'],
        ]);

        $content = $request->file('file')
            ? file_get_contents($request->file('file')->getRealPath())
            : (string) ($data['text'] ?? '');

        if (trim($content) === '') {
            throw ValidationException::withMessages(['text' => 'Upload a caption file or paste the captions.']);
        }

        try {
            $clips->importTranscript($clipSource, $content);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['text' => $e->getMessage()]);
        }

        return response()->json($this->presentSource($clipSource->refresh()->load('runs.clips'), true));
    }

    public function showRun(Request $request, string $run): JsonResponse
    {
        $clipRun = ClipRun::query()->where('user_id', $request->user()->id)->whereKey($run)->with('clips')->firstOrFail();

        return response()->json($this->presentRun($clipRun));
    }

    private function owned(Request $request, string $id): ClipSource
    {
        return ClipSource::query()->where('user_id', $request->user()->id)->whereKey($id)->with('runs.clips')->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function presentSource(ClipSource $source, bool $withRuns): array
    {
        $data = [
            'id' => $source->id,
            'kind' => $source->kind,
            'status' => $source->status,
            'name' => $source->original_name,
            'error' => $source->error,
            'duration_seconds' => $source->duration_seconds,
            'width' => $source->width,
            'height' => $source->height,
            // The full YouTube video, shown in YouTube's own player. Nothing is downloaded.
            'youtube_embed_url' => $source->youtube_id ? 'https://www.youtube.com/embed/'.$source->youtube_id : null,
            'youtube_watch_url' => $source->youtube_id ? 'https://www.youtube.com/watch?v='.$source->youtube_id : null,
            'transcript_segments' => is_array($source->transcript) ? count($source->transcript) : 0,
            'can_clip' => $source->kind === ClipSource::KIND_UPLOAD && $source->status === ClipSource::STATUS_READY,
            'can_find_moments' => $source->status === ClipSource::STATUS_READY && is_array($source->transcript),
            'created_at' => $source->created_at?->toIso8601String(),
        ];

        if ($withRuns) {
            $data['runs'] = $source->runs->take(10)->map(fn (ClipRun $run) => $this->presentRun($run))->values();
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function presentRun(ClipRun $run): array
    {
        $generation = $run->generation_id ? Generation::query()->find($run->generation_id) : null;

        return [
            'id' => $run->id,
            'source_id' => $run->clip_source_id,
            'request' => $run->request,
            'status' => $run->status,
            'error' => $run->error,
            'count' => $run->count,
            'min_seconds' => $run->min_seconds,
            'max_seconds' => $run->max_seconds,
            'model_key' => $run->model_key,
            'price_usd' => $generation ? Money::toUsd((int) $generation->price_micros) : null,
            'clips' => $run->clips->map(fn ($clip) => [
                'id' => $clip->id,
                'position' => $clip->position,
                'title' => $clip->title,
                'reason' => $clip->reason,
                'start_seconds' => $clip->start_seconds,
                'end_seconds' => $clip->end_seconds,
                'duration_seconds' => round($clip->end_seconds - $clip->start_seconds, 2),
                'url' => $clip->storage_path ? Storage::disk('public')->url($clip->storage_path) : null,
                // A moment in a YouTube video: the embed starts and stops at these times.
                'youtube_embed_url' => $run->source?->youtube_id
                    ? 'https://www.youtube.com/embed/'.$run->source->youtube_id.'?start='.(int) floor($clip->start_seconds).'&end='.(int) ceil($clip->end_seconds)
                    : null,
            ])->values(),
            'created_at' => $run->created_at?->toIso8601String(),
        ];
    }
}
