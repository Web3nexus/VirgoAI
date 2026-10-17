<?php

namespace App\Http\Controllers\Api;

use App\Clips\Captions;
use App\Clips\Ffmpeg;
use App\Clips\Split;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessSplit;
use App\Models\Clip;
use App\Models\ClipRun;
use App\Models\ClipSource;
use App\Models\Generation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Manual editing and captions: split a long video, download captions, trim a clip, trim a generated video.
 * Every method checks the record belongs to the signed-in user before it reads or changes anything.
 */
class EditController extends Controller
{
    private const MIN_TRIM = 1.0;

    private const MAX_TRIM = 900.0;

    /** Cut a long upload into consecutive parts of about the chosen length. Local work: no charge. */
    public function split(Request $request, string $source): JsonResponse
    {
        $clipSource = ClipSource::query()->where('user_id', $request->user()->id)->whereKey($source)->firstOrFail();

        if ($clipSource->kind !== ClipSource::KIND_UPLOAD || $clipSource->status !== ClipSource::STATUS_READY) {
            throw ValidationException::withMessages(['source' => 'Only a ready upload can be split. A YouTube video plays in full.']);
        }

        $data = $request->validate([
            'part_seconds' => ['required', 'integer', 'between:5,900'],
        ]);

        $duration = (float) $clipSource->duration_seconds;
        $parts = Split::plan($duration, $data['part_seconds'], is_array($clipSource->transcript) ? $clipSource->transcript : []);

        if (count($parts) > Split::MAX_PARTS) {
            throw ValidationException::withMessages(['part_seconds' => 'That would make more than '.Split::MAX_PARTS.' parts. Choose longer parts.']);
        }

        $run = ClipRun::create([
            'user_id' => $request->user()->id,
            'clip_source_id' => $clipSource->id,
            'request' => "Automatic split into {$data['part_seconds']} second parts",
            'model_key' => 'none',
            'count' => count($parts),
            'min_seconds' => $data['part_seconds'],
            'max_seconds' => $data['part_seconds'],
            'status' => ClipRun::STATUS_QUEUED,
        ]);

        ProcessSplit::dispatch($run->id);

        return response()->json(['run_id' => $run->id, 'parts' => count($parts), 'status' => $run->status], 201);
    }

    /** Captions for a whole source, as WebVTT or SRT. */
    public function sourceCaptions(Request $request, string $source): Response
    {
        $clipSource = ClipSource::query()->where('user_id', $request->user()->id)->whereKey($source)->firstOrFail();

        if (! is_array($clipSource->transcript) || $clipSource->transcript === []) {
            throw ValidationException::withMessages(['captions' => 'There is no transcript to caption yet.']);
        }

        return $this->captionFile($clipSource->transcript, 0.0, (float) max(collect($clipSource->transcript)->max('end'), $clipSource->duration_seconds ?? 0), (string) $request->query('format', 'vtt'), $clipSource->original_name ?? 'captions');
    }

    /** Captions for one clip, shifted so they start at zero. */
    public function clipCaptions(Request $request, string $clip): Response
    {
        $record = $this->ownedClip($request, $clip);
        $source = $record->run->source;

        return $this->captionFile($source->transcript ?? [], (float) $record->start_seconds, (float) $record->end_seconds, (string) $request->query('format', 'vtt'), 'clip-'.$record->position);
    }

    /** Re-cut a clip with new start and end times. The original file is replaced. */
    public function trimClip(Request $request, string $clip): JsonResponse
    {
        $record = $this->ownedClip($request, $clip);
        $source = $record->run->source;

        if ($source->kind !== ClipSource::KIND_UPLOAD || ! $source->storage_path) {
            throw ValidationException::withMessages(['clip' => 'Only clips cut from an uploaded video can be trimmed.']);
        }

        [$start, $end] = $this->range($request, (float) $source->duration_seconds);

        $path = "clips/runs/{$record->clip_run_id}/{$record->position}-".Str::lower(Str::random(6)).'.mp4';
        Storage::disk('public')->makeDirectory("clips/runs/{$record->clip_run_id}");

        try {
            Ffmpeg::fromConfig()->cut(Storage::disk('local')->path($source->storage_path), $start, $end, Storage::disk('public')->path($path));
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['clip' => $e->getMessage()]);
        }

        if ($record->storage_path) {
            Storage::disk('public')->delete($record->storage_path);
        }

        $record->forceFill(['start_seconds' => $start, 'end_seconds' => $end, 'storage_path' => $path])->save();

        return response()->json(['id' => $record->id, 'start_seconds' => $start, 'end_seconds' => $end, 'url' => Storage::disk('public')->url($path)]);
    }

    /** Trim a generated video. Its file is replaced by the trimmed one. Local work: no charge. */
    public function trimGeneration(Request $request, string $generation): JsonResponse
    {
        $run = Generation::query()->where('user_id', $request->user()->id)->whereKey($generation)->firstOrFail();

        if ($run->output_type !== 'video' || $run->status !== Generation::STATUS_SUCCEEDED || ! $run->output_path) {
            throw ValidationException::withMessages(['generation' => 'Only a finished video can be trimmed.']);
        }

        $duration = $this->mediaDuration(Storage::disk('public')->path($run->output_path));
        [$start, $end] = $this->range($request, $duration);

        $path = "generations/{$run->id}-trim-".Str::lower(Str::random(6)).'.mp4';

        try {
            Ffmpeg::fromConfig()->cut(Storage::disk('public')->path($run->output_path), $start, $end, Storage::disk('public')->path($path));
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['generation' => $e->getMessage()]);
        }

        $run->forceFill(['output_path' => $path])->save();

        return response()->json(['output_url' => Storage::disk('public')->url($path), 'start_seconds' => $start, 'end_seconds' => $end]);
    }

    /** @return array{0: float, 1: float} */
    private function range(Request $request, float $duration): array
    {
        $data = $request->validate([
            'start' => ['required', 'numeric', 'min:0'],
            'end' => ['required', 'numeric', 'gt:start'],
        ]);

        $start = (float) $data['start'];
        $end = min((float) $data['end'], $duration > 0 ? $duration : (float) $data['end']);

        if ($end - $start < self::MIN_TRIM) {
            throw ValidationException::withMessages(['end' => 'A trim must be at least '.self::MIN_TRIM.' second long.']);
        }
        if ($end - $start > self::MAX_TRIM) {
            throw ValidationException::withMessages(['end' => 'A trim can be at most '.self::MAX_TRIM.' seconds long.']);
        }

        return [round($start, 3), round($end, 3)];
    }

    /** @param  array<int, array{start: float, end: float, text: string}>  $transcript */
    private function captionFile(array $transcript, float $from, float $to, string $format, string $name): Response
    {
        $cues = Captions::window($transcript, $from, $to);

        if ($cues === []) {
            throw ValidationException::withMessages(['captions' => 'No speech falls inside this range.']);
        }

        $isSrt = $format === 'srt';
        $body = $isSrt ? Captions::srt($cues) : Captions::vtt($cues);
        $file = Str::slug($name).'.'.($isSrt ? 'srt' : 'vtt');

        return response($body, 200, [
            'Content-Type' => $isSrt ? 'application/x-subrip; charset=utf-8' : 'text/vtt; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$file.'"',
        ]);
    }

    private function ownedClip(Request $request, string $clip): Clip
    {
        return Clip::query()
            ->with('run.source')
            ->whereKey($clip)
            ->whereHas('run', fn ($query) => $query->where('user_id', $request->user()->id))
            ->firstOrFail();
    }

    private function mediaDuration(string $path): float
    {
        return Ffmpeg::fromConfig()->probe($path)['duration'];
    }
}
