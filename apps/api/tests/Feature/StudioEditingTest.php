<?php

use App\Clips\Captions;
use App\Clips\Ffmpeg;
use App\Clips\Split;
use App\Jobs\ProcessSplit;
use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\Clip;
use App\Models\ClipRun;
use App\Models\ClipSource;
use App\Models\Generation;
use App\Models\ProviderCapability;
use App\Models\User;
use App\Styles\StylePresets;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

function studioSampleVideo(float $seconds): ?string
{
    $path = sys_get_temp_dir().'/virgo-studio-'.uniqid().'.mp4';
    $command = sprintf(
        '%s -y -f lavfi -i testsrc=duration=%s:size=320x240:rate=10 -f lavfi -i sine=frequency=440:duration=%s -shortest -c:v libx264 -pix_fmt yuv420p -c:a aac %s 2>/dev/null',
        escapeshellcmd((string) config('virgoai.clips.ffmpeg', 'ffmpeg')), $seconds, $seconds, escapeshellarg($path),
    );
    exec($command, $out, $code);

    return $code === 0 && is_file($path) ? $path : null;
}

function studioHasFfmpeg(): bool
{
    exec('command -v '.escapeshellarg((string) config('virgoai.clips.ffmpeg', 'ffmpeg')).' 2>/dev/null', $out, $code);

    return $code === 0;
}

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
    $this->user = User::factory()->create(['balance_micros' => 20_000_000]);
    $this->owner = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
});

// Style menus

it('turns the chosen menus into plain words for the provider, and ignores unknown ones', function () {
    $directive = StylePresets::directive(['environment' => 'village', 'character' => 'anime', 'look' => 'nope']);

    expect($directive)->toContain('quiet village')
        ->and($directive)->toContain('anime characters')
        ->and($directive)->not->toContain('nope');

    expect(StylePresets::applyToPrompt('A fox learns to read', []))->toBe('A fox learns to read');
});

it('sends the chosen style with the prompt, and stores the choice with the run', function () {
    $provider = AiProvider::create(['key' => 'echo', 'name' => 'Echo', 'status' => 'active', 'priority' => 999]);
    $model = AiModel::create(['ai_provider_id' => $provider->id, 'key' => 'echo-1', 'name' => 'Echo', 'enabled' => true, 'priority' => 999]);
    ProviderCapability::create(['ai_model_id' => $model->id, 'capability' => 'text']);

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/tools/story/run', [
            'prompt' => 'A fox learns to read',
            'skill' => 'plain',
            'model_key' => 'echo-1',
            'styles' => ['environment' => 'village', 'character' => 'anime'],
        ])
        ->assertCreated();

    expect($response->json('data.output_text'))->toContain('quiet village')
        ->and($response->json('data.output_text'))->toContain('A fox learns to read');
    expect(Generation::find($response->json('data.id'))->options['styles'])->toEqualCanonicalizing(['environment' => 'village', 'character' => 'anime']);
});

it('refuses a style that is not on the menu', function () {
    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/tools/story/run', ['prompt' => 'A fox', 'styles' => ['environment' => 'moon-base-zero']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('styles.environment');
});

it('lists the menus, grouped, for the picker', function () {
    $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/styles')
        ->assertOk()
        ->assertJsonPath('groups.environment.0.key', 'village');
});

// Captions

it('shifts captions to the clip and writes valid WebVTT and SRT', function () {
    $segments = [
        ['start' => 0.0, 'end' => 4.0, 'text' => 'Before the clip'],
        ['start' => 9.0, 'end' => 13.5, 'text' => 'The key point <bold>'],
        ['start' => 30.0, 'end' => 34.0, 'text' => 'After'],
    ];

    $cues = Captions::window($segments, 8.0, 20.0);

    expect($cues)->toHaveCount(1)
        ->and($cues[0]['start'])->toBe(1.0)
        ->and($cues[0]['end'])->toBe(5.5);

    expect(Captions::vtt($cues))->toStartWith("WEBVTT\n\n00:00:01.000 --> 00:00:05.500")
        ->and(Captions::vtt($cues))->toContain('&lt;bold&gt;')
        ->and(Captions::srt($cues))->toContain('00:00:01,000 --> 00:00:05,500');
});

it('serves a clip\'s captions to its owner only', function () {
    $source = ClipSource::create(['user_id' => $this->user->id, 'kind' => 'upload', 'status' => 'ready', 'duration_seconds' => 60,
        'transcript' => [['start' => 10, 'end' => 14, 'text' => 'Hello there.']]]);
    $run = ClipRun::create(['user_id' => $this->user->id, 'clip_source_id' => $source->id, 'request' => 'x', 'model_key' => 'claude-haiku-5-5', 'count' => 1, 'min_seconds' => 5, 'max_seconds' => 60, 'status' => 'succeeded']);
    $clip = Clip::create(['clip_run_id' => $run->id, 'position' => 1, 'start_seconds' => 8, 'end_seconds' => 20, 'title' => 'T', 'storage_path' => 'x.mp4']);

    $this->actingAs($this->user, 'sanctum')
        ->get("/api/v1/clips/{$clip->id}/captions?format=vtt")
        ->assertOk()
        ->assertSee('00:00:02.000 --> 00:00:06.000', false);

    $stranger = User::factory()->create();
    $this->actingAs($stranger, 'sanctum')->get("/api/v1/clips/{$clip->id}/captions")->assertNotFound();
});

// Split

it('plans parts that cover the whole video with no gaps, snapped to sentence ends', function () {
    $transcript = [['start' => 0, 'end' => 29.4, 'text' => 'a'], ['start' => 29.4, 'end' => 61.0, 'text' => 'b']];
    $parts = Split::plan(125.0, 30, $transcript);

    expect($parts[0]['start'])->toBe(0.0)
        ->and(end($parts)['end'])->toBe(125.0);

    for ($i = 1; $i < count($parts); $i++) {
        expect($parts[$i]['start'])->toBe($parts[$i - 1]['end']);
    }
    expect($parts[0]['end'])->toBe(29.4);
});

it('splits a long upload into parts with no AI charge, and refuses a split that makes too many parts', function () {
    $source = ClipSource::create(['user_id' => $this->user->id, 'kind' => 'upload', 'status' => 'ready', 'duration_seconds' => 3600, 'storage_path' => 'x.mp4']);

    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/clip-sources/{$source->id}/split", ['part_seconds' => 5])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('part_seconds');

    Queue::fake();
    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/clip-sources/{$source->id}/split", ['part_seconds' => 60])
        ->assertCreated()
        ->assertJsonPath('parts', 60);

    Queue::assertPushed(ProcessSplit::class);
    expect($this->user->refresh()->balance_micros)->toBe(20_000_000);
});

it('does not split a YouTube video, which plays in full', function () {
    $source = ClipSource::create(['user_id' => $this->user->id, 'kind' => 'youtube', 'status' => 'ready', 'youtube_id' => 'dQw4w9WgXcQ']);

    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/clip-sources/{$source->id}/split", ['part_seconds' => 30])
        ->assertUnprocessable();
});

it('cuts every part of a real video, and the parts add up to its length', function () {
    if (! studioHasFfmpeg()) {
        $this->markTestSkipped('ffmpeg is not installed.');
    }
    $sample = studioSampleVideo(20);
    $source = ClipSource::create(['user_id' => $this->user->id, 'kind' => 'upload', 'status' => 'ready', 'duration_seconds' => 20, 'storage_path' => 'clips/sources/s/source.mp4']);
    Storage::disk('local')->put($source->storage_path, file_get_contents($sample));
    $run = ClipRun::create(['user_id' => $this->user->id, 'clip_source_id' => $source->id, 'request' => 'split', 'model_key' => 'none', 'count' => 4, 'min_seconds' => 5, 'max_seconds' => 5, 'status' => 'queued']);

    (new ProcessSplit($run->id))->handle();

    $run->refresh();
    expect($run->status)->toBe('succeeded')
        ->and(Clip::where('clip_run_id', $run->id)->count())->toBe(4);

    $total = Clip::where('clip_run_id', $run->id)->get()->sum(fn (Clip $c) => $c->end_seconds - $c->start_seconds);
    expect($total)->toBeGreaterThan(19.5)->toBeLessThan(20.5);

    @unlink($sample);
});

// Manual trims

it('trims a clip to new times and replaces its file', function () {
    if (! studioHasFfmpeg()) {
        $this->markTestSkipped('ffmpeg is not installed.');
    }
    $sample = studioSampleVideo(20);
    $source = ClipSource::create(['user_id' => $this->user->id, 'kind' => 'upload', 'status' => 'ready', 'duration_seconds' => 20, 'storage_path' => 'clips/sources/t/source.mp4']);
    Storage::disk('local')->put($source->storage_path, file_get_contents($sample));
    $run = ClipRun::create(['user_id' => $this->user->id, 'clip_source_id' => $source->id, 'request' => 'x', 'model_key' => 'claude-haiku-5-5', 'count' => 1, 'min_seconds' => 1, 'max_seconds' => 60, 'status' => 'succeeded']);
    $clip = Clip::create(['clip_run_id' => $run->id, 'position' => 1, 'start_seconds' => 0, 'end_seconds' => 10, 'title' => 'T', 'storage_path' => "clips/runs/{$run->id}/1.mp4"]);
    Storage::disk('public')->makeDirectory("clips/runs/{$run->id}");
    Storage::disk('public')->put("clips/runs/{$run->id}/1.mp4", 'old');

    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/clips/{$clip->id}/trim", ['start' => 4, 'end' => 8])
        ->assertOk()
        ->assertJsonPath('end_seconds', 8);

    $clip->refresh();
    expect($clip->start_seconds)->toBe(4.0)
        ->and($clip->storage_path)->not->toBe("clips/runs/{$run->id}/1.mp4");
    Storage::disk('public')->assertExists($clip->storage_path);

    @unlink($sample);
});

it('refuses a trim that is too short, and one that is not the owner\'s', function () {
    $source = ClipSource::create(['user_id' => $this->user->id, 'kind' => 'upload', 'status' => 'ready', 'duration_seconds' => 20, 'storage_path' => 'x.mp4']);
    $run = ClipRun::create(['user_id' => $this->user->id, 'clip_source_id' => $source->id, 'request' => 'x', 'model_key' => 'claude-haiku-5-5', 'count' => 1, 'min_seconds' => 1, 'max_seconds' => 60, 'status' => 'succeeded']);
    $clip = Clip::create(['clip_run_id' => $run->id, 'position' => 1, 'start_seconds' => 0, 'end_seconds' => 10, 'title' => 'T', 'storage_path' => 'x.mp4']);

    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/clips/{$clip->id}/trim", ['start' => 4, 'end' => 4.2])
        ->assertUnprocessable();

    $stranger = User::factory()->create();
    $this->actingAs($stranger, 'sanctum')
        ->postJson("/api/v1/clips/{$clip->id}/trim", ['start' => 1, 'end' => 5])
        ->assertNotFound();
});

it('trims a generated video, replacing its output with the trimmed file', function () {
    if (! studioHasFfmpeg()) {
        $this->markTestSkipped('ffmpeg is not installed.');
    }
    $sample = studioSampleVideo(12);
    Storage::disk('public')->put('generations/g1.mp4', file_get_contents($sample));
    $run = Generation::create([
        'user_id' => $this->user->id, 'tool' => 'video', 'capability' => 'video', 'provider_key' => 'fal', 'model_key' => 'bytedance/seedance-2.0/image-to-video',
        'prompt' => 'a slow pan', 'options' => [], 'status' => 'succeeded', 'output_type' => 'video', 'output_path' => 'generations/g1.mp4',
    ]);

    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/generations/{$run->id}/trim", ['start' => 2, 'end' => 6])
        ->assertOk();

    $run->refresh();
    expect($run->output_path)->not->toBe('generations/g1.mp4');
    expect(Ffmpeg::fromConfig()->probe(Storage::disk('public')->path($run->output_path))['duration'])->toBeGreaterThan(3.5)->toBeLessThan(4.5);

    @unlink($sample);
});

it('lists the style menus from the API', function () {
    expect(array_keys(StylePresets::GROUPS))->toBe(['environment', 'character', 'look']);
});
