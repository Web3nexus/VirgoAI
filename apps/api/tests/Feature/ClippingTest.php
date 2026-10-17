<?php

use App\Ai\Fal\FalQueue;
use App\Billing\PriceQuoter;
use App\Billing\Wallet;
use App\Clips\ClipPicker;
use App\Clips\ClipService;
use App\Clips\Ffmpeg;
use App\Jobs\PollTranscription;
use App\Jobs\ProcessClipRun;
use App\Jobs\ProcessClipSource;
use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\Clip;
use App\Models\ClipSource;
use App\Models\Generation;
use App\Models\ModelPrice;
use App\Models\ProviderCapability;
use App\Models\User;
use App\Settings\ProviderCredentials;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/** A short real video with speech-like audio, made by the ffmpeg on this machine. */
function makeSampleVideo(float $seconds = 20): ?string
{
    $ffmpeg = (string) config('virgoai.clips.ffmpeg', 'ffmpeg');
    $path = sys_get_temp_dir().'/virgo-sample-'.uniqid().'.mp4';

    $command = sprintf(
        '%s -y -f lavfi -i testsrc=duration=%s:size=320x240:rate=10 -f lavfi -i sine=frequency=440:duration=%s -shortest -c:v libx264 -pix_fmt yuv420p -c:a aac %s 2>/dev/null',
        escapeshellcmd($ffmpeg), $seconds, $seconds, escapeshellarg($path),
    );
    exec($command, $output, $code);

    return $code === 0 && is_file($path) ? $path : null;
}

function ffmpegAvailable(): bool
{
    exec('command -v '.escapeshellarg((string) config('virgoai.clips.ffmpeg', 'ffmpeg')).' 2>/dev/null', $out, $code);

    return $code === 0;
}

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
    $this->user = User::factory()->create();
    $this->user->forceFill(['balance_micros' => 50_000_000])->save();
});

// Pure rules

it('keeps clips inside the video and inside the requested lengths, and drops overlaps', function () {
    $result = ClipPicker::validate([
        ['start' => -5, 'end' => 40, 'title' => 'Opening', 'reason' => 'Hook'],   // clamped to 0..40 (40s, ok)
        ['start' => 30, 'end' => 60, 'title' => 'Overlaps', 'reason' => ''],      // overlaps the first
        ['start' => 100, 'end' => 103, 'title' => 'Too short', 'reason' => ''],   // 3s < 10s minimum
        ['start' => 200, 'end' => 500, 'title' => 'Too long', 'reason' => ''],    // 300s > 120s maximum
        ['start' => 90, 'end' => 130, 'title' => 'Ends past the video', 'reason' => ''], // clamped to 90..100 = 10s, ok
        'not an array',
    ], 100.0, 10, 120, 5);

    expect(collect($result['clips'])->pluck('title')->all())->toBe(['Opening', 'Ends past the video'])
        ->and($result['clips'][1]['end'])->toBe(100.0)
        ->and($result['dropped'])->toBe(4);
});

it('stops at the requested number of clips', function () {
    $candidates = collect(range(0, 9))->map(fn ($i) => ['start' => $i * 20, 'end' => $i * 20 + 15, 'title' => "C{$i}", 'reason' => ''])->all();

    expect(ClipPicker::validate($candidates, 300, 10, 60, 3)['clips'])->toHaveCount(3);
});

it('reads the JSON answer even when it is wrapped in a code fence', function () {
    $reply = "Here you go:\n```json\n{\"clips\":[{\"start\":1,\"end\":20,\"title\":\"A\",\"reason\":\"B\"}]}\n```";

    expect(ClipPicker::parseReply($reply))->toHaveCount(1)
        ->and(ClipPicker::parseReply('I cannot help with that.'))->toBeNull();
});

it('turns whisper chunks into timed segments, and closes an open-ended last chunk at the video end', function () {
    $segments = ClipPicker::segmentsFromChunks([
        ['timestamp' => [0.0, 4.5], 'text' => ' Hello there. '],
        ['timestamp' => [4.5, null], 'text' => 'Goodbye.'],
        ['timestamp' => [9.0, 9.5], 'text' => '   '],
    ], 12.0);

    expect($segments)->toHaveCount(2)
        ->and($segments[0]['text'])->toBe('Hello there.')
        ->and($segments[1]['end'])->toBe(12.0);
});

it('prices a Haiku prompt over 100,000 tokens at the long-prompt rate', function () {
    ModelPrice::create(['provider_key' => 'anthropic', 'model_key' => 'claude-haiku-5-5', 'unit' => 'input_mtok', 'cost_micros' => 100_000, 'source' => 'anthropic_docs']);
    ModelPrice::create(['provider_key' => 'anthropic', 'model_key' => 'claude-haiku-5-5', 'unit' => 'output_mtok', 'cost_micros' => 500_000, 'source' => 'anthropic_docs']);
    ModelPrice::create(['provider_key' => 'anthropic', 'model_key' => 'claude-haiku-5-5', 'unit' => 'input_mtok_long', 'cost_micros' => 500_000, 'source' => 'anthropic_docs']);
    ModelPrice::create(['provider_key' => 'anthropic', 'model_key' => 'claude-haiku-5-5', 'unit' => 'output_mtok_long', 'cost_micros' => 2_500_000, 'source' => 'anthropic_docs']);

    // 150k input tokens at the long rate ($0.075) plus 1k output at the long rate ($0.0025), sold at +40%.
    $settled = app(PriceQuoter::class)->settleText('anthropic', 'claude-haiku-5-5', ['input_tokens' => 150_000, 'output_tokens' => 1_000]);
    expect($settled->costMicros)->toBe(77_500)
        ->and($settled->priceMicros)->toBe(108_500);

    // A small prompt stays on the standard rate.
    $small = app(PriceQuoter::class)->settleText('anthropic', 'claude-haiku-5-5', ['input_tokens' => 1_000, 'output_tokens' => 1_000]);
    expect($small->costMicros)->toBe(100 + 500);
});

// Real media

it('reads the duration and size of a real video, and cuts a clip from it', function () {
    if (! ffmpegAvailable()) {
        $this->markTestSkipped('ffmpeg is not installed on this machine.');
    }

    $sample = makeSampleVideo(20);
    expect($sample)->not->toBeNull();

    $info = Ffmpeg::fromConfig()->probe($sample);
    expect($info['duration'])->toBeGreaterThan(19.5)
        ->and($info['width'])->toBe(320)
        ->and($info['height'])->toBe(240);

    $out = sys_get_temp_dir().'/virgo-cut-'.uniqid().'.mp4';
    Ffmpeg::fromConfig()->cut($sample, 5.0, 9.0, $out);

    $cut = Ffmpeg::fromConfig()->probe($out);
    expect($cut['duration'])->toBeGreaterThan(3.5)->toBeLessThan(4.5);

    @unlink($sample);
    @unlink($out);
});

// Pipeline

it('accepts an upload, probes it and starts transcription with a charge', function () {
    if (! ffmpegAvailable()) {
        $this->markTestSkipped('ffmpeg is not installed on this machine.');
    }

    Queue::fake();
    $sample = makeSampleVideo(20);

    $this->actingAs($this->user, 'sanctum')
        ->post('/api/v1/clip-sources', ['file' => new UploadedFile($sample, 'talk.mp4', 'video/mp4', null, true)], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('kind', 'upload')
        ->assertJsonPath('status', 'uploaded');

    Queue::assertPushed(ProcessClipSource::class);
    @unlink($sample);
});

it('transcribes an upload with fal Whisper, charged per second of audio, and saves the transcript', function () {
    if (! ffmpegAvailable()) {
        $this->markTestSkipped('ffmpeg is not installed on this machine.');
    }

    Queue::fake();
    config(['virgoai.clips.transcriber' => 'fal']);
    ProviderCredentials::set('FAL_KEY', 'fal-clip-key-0001', $this->user);
    $provider = AiProvider::create(['key' => 'fal', 'name' => 'fal.ai', 'status' => 'active', 'priority' => 100]);
    ModelPrice::create(['provider_key' => 'fal', 'model_key' => 'fal-ai/whisper', 'unit' => 'second', 'cost_micros' => 1000, 'source' => 'fal_api']);

    $sample = makeSampleVideo(20);
    $source = ClipSource::create(['user_id' => $this->user->id, 'kind' => 'upload', 'status' => 'uploaded', 'original_name' => 'talk.mp4']);
    Storage::disk('local')->put("clips/sources/{$source->id}/source.mp4", file_get_contents($sample));
    $source->forceFill(['storage_path' => "clips/sources/{$source->id}/source.mp4"])->save();

    Http::fake([
        'queue.fal.run/fal-ai/whisper' => Http::response([
            'request_id' => 'w1',
            'status_url' => 'https://queue.fal.run/fal-ai/whisper/requests/w1/status',
            'response_url' => 'https://queue.fal.run/fal-ai/whisper/requests/w1',
        ]),
    ]);

    (new ProcessClipSource($source->id))->handle(app(ClipService::class));

    $source->refresh();
    expect($source->status)->toBe('transcribing', (string) $source->error)
        ->and($source->duration_seconds)->toBeGreaterThan(19.5)
        ->and($this->user->refresh()->balance_micros)->toBeLessThan(50_000_000);

    Http::assertSent(fn ($request) => str_contains($request['audio_url'], "clips/audio/{$source->id}.mp3") && $request['chunk_level'] === 'segment');

    Http::fake([
        'queue.fal.run/fal-ai/whisper/requests/w1/status' => Http::response(['status' => 'COMPLETED']),
        'queue.fal.run/fal-ai/whisper/requests/w1' => Http::response(['text' => 'hi', 'chunks' => [
            ['timestamp' => [0.0, 10.0], 'text' => 'Welcome to the talk.'],
            ['timestamp' => [10.0, 19.0], 'text' => 'Pricing is simple.'],
        ]]),
    ]);

    (new PollTranscription($source->id))->handle(app(FalQueue::class), app(Wallet::class));

    $source->refresh();
    expect($source->status)->toBe('ready')
        ->and($source->transcript)->toHaveCount(2)
        ->and($source->can_clip ?? true)->toBeTrue();

    @unlink($sample);
});

it('refunds the transcription when fal reports a failure', function () {
    Queue::fake();
    ProviderCredentials::set('FAL_KEY', 'fal-clip-key-0002', $this->user);
    AiProvider::create(['key' => 'fal', 'name' => 'fal.ai', 'status' => 'active', 'priority' => 100]);
    ModelPrice::create(['provider_key' => 'fal', 'model_key' => 'fal-ai/whisper', 'unit' => 'second', 'cost_micros' => 1000, 'source' => 'fal_api']);

    $source = ClipSource::create(['user_id' => $this->user->id, 'kind' => 'upload', 'status' => 'transcribing', 'duration_seconds' => 20]);
    $generation = Generation::create([
        'user_id' => $this->user->id, 'tool' => 'transcribe', 'capability' => 'audio', 'provider_key' => 'fal',
        'model_key' => 'fal-ai/whisper', 'prompt' => 'Transcribe', 'options' => [], 'status' => 'running',
        'output_type' => 'transcript', 'price_micros' => 20_000, 'cost_micros' => 17_000,
        'provider_job' => ['endpoint_id' => 'fal-ai/whisper', 'request_id' => 'w2', 'status_url' => 'https://queue.fal.run/fal-ai/whisper/requests/w2/status', 'response_url' => 'https://queue.fal.run/fal-ai/whisper/requests/w2'],
    ]);
    $source->forceFill(['transcript_generation_id' => $generation->id])->save();

    Http::fake(['queue.fal.run/*/status' => Http::response(['status' => 'FAILED'])]);

    (new PollTranscription($source->id))->handle(app(FalQueue::class), app(Wallet::class));

    expect($source->refresh()->status)->toBe('failed')
        ->and($generation->refresh()->status)->toBe('failed');
});

it('cuts the clips the model picked, and settles the analysis to the tokens used', function () {
    if (! ffmpegAvailable()) {
        $this->markTestSkipped('ffmpeg is not installed on this machine.');
    }

    Queue::fake();

    $provider = AiProvider::create(['key' => 'anthropic', 'name' => 'Anthropic', 'status' => 'active', 'priority' => 50]);
    $model = AiModel::create(['ai_provider_id' => $provider->id, 'key' => 'claude-haiku-5-5', 'name' => 'Haiku', 'enabled' => true, 'priority' => 50]);
    ProviderCapability::create(['ai_model_id' => $model->id, 'capability' => 'text']);
    ProviderCredentials::set('ANTHROPIC_API_KEY', 'sk-ant-clip-key-0003', $this->user);
    ModelPrice::create(['provider_key' => 'anthropic', 'model_key' => 'claude-haiku-5-5', 'unit' => 'input_mtok', 'cost_micros' => 100_000, 'source' => 'anthropic_docs']);
    ModelPrice::create(['provider_key' => 'anthropic', 'model_key' => 'claude-haiku-5-5', 'unit' => 'output_mtok', 'cost_micros' => 500_000, 'source' => 'anthropic_docs']);

    $sample = makeSampleVideo(20);
    $source = ClipSource::create([
        'user_id' => $this->user->id, 'kind' => 'upload', 'status' => 'ready', 'duration_seconds' => 20,
        'storage_path' => 'clips/sources/x/source.mp4',
        'transcript' => [['start' => 0, 'end' => 10, 'text' => 'Intro'], ['start' => 10, 'end' => 20, 'text' => 'The key pricing point']],
    ]);
    Storage::disk('local')->put($source->storage_path, file_get_contents($sample));

    $clips = app(ClipService::class);
    $run = $clips->requestRun($this->user, $source, 'The pricing point', 'claude-haiku-5-5', 2, 3, 15);

    Http::fake(['api.anthropic.com/v1/messages' => Http::response([
        'content' => [['type' => 'text', 'text' => json_encode(['clips' => [
            ['start' => 10, 'end' => 18, 'title' => 'The key pricing point', 'reason' => 'Directly answers the request'],
        ]])]],
        'stop_reason' => 'end_turn',
        'usage' => ['input_tokens' => 200, 'output_tokens' => 80],
    ])]);

    (new ProcessClipRun($run->id))->handle($clips, app(Wallet::class));

    $run->refresh();
    expect($run->status)->toBe("succeeded", $run->error ?? "")
        ->and(Clip::where('clip_run_id', $run->id)->count())->toBe(1)
        ->and(Clip::first()->title)->toBe('The key pricing point');

    Storage::disk('public')->assertExists(Clip::first()->storage_path);

    // Settled: 200 input and 80 output tokens at Haiku's standard rate, sold at +40%.
    $generation = Generation::find($run->generation_id);
    expect($generation->price_micros)->toBe((int) ceil(((200 * 100_000 + 80 * 500_000) / 1_000_000) * 1.4));

    @unlink($sample);
});

it('refunds the analysis when no moment matches', function () {
    Queue::fake();
    $provider = AiProvider::create(['key' => 'anthropic', 'name' => 'Anthropic', 'status' => 'active', 'priority' => 50]);
    $model = AiModel::create(['ai_provider_id' => $provider->id, 'key' => 'claude-haiku-5-5', 'name' => 'Haiku', 'enabled' => true, 'priority' => 50]);
    ProviderCapability::create(['ai_model_id' => $model->id, 'capability' => 'text']);
    ProviderCredentials::set('ANTHROPIC_API_KEY', 'sk-ant-clip-key-0004', $this->user);
    ModelPrice::create(['provider_key' => 'anthropic', 'model_key' => 'claude-haiku-5-5', 'unit' => 'input_mtok', 'cost_micros' => 100_000, 'source' => 'anthropic_docs']);
    ModelPrice::create(['provider_key' => 'anthropic', 'model_key' => 'claude-haiku-5-5', 'unit' => 'output_mtok', 'cost_micros' => 500_000, 'source' => 'anthropic_docs']);

    $source = ClipSource::create([
        'user_id' => $this->user->id, 'kind' => 'upload', 'status' => 'ready', 'duration_seconds' => 20,
        'storage_path' => 'clips/sources/y/source.mp4',
        'transcript' => [['start' => 0, 'end' => 20, 'text' => 'Nothing relevant']],
    ]);
    $clips = app(ClipService::class);
    $run = $clips->requestRun($this->user, $source, 'Find the cooking part', 'claude-haiku-5-5', 2, 3, 15);
    $before = $this->user->refresh()->balance_micros;

    Http::fake(['api.anthropic.com/v1/messages' => Http::response([
        'content' => [['type' => 'text', 'text' => '{"clips":[]}']],
        'stop_reason' => 'end_turn',
        'usage' => ['input_tokens' => 100, 'output_tokens' => 10],
    ])]);

    (new ProcessClipRun($run->id))->handle($clips, app(Wallet::class));

    expect($run->refresh()->status)->toBe('failed')
        ->and($run->error)->toContain('No moment')
        ->and($this->user->refresh()->balance_micros)->toBeGreaterThan($before);
});

// YouTube and access

it('plays a YouTube video in full and never offers to download or clip it', function () {
    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/clip-sources', ['youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'])
        ->assertCreated()
        ->assertJsonPath('kind', 'youtube')
        ->assertJsonPath('status', 'ready')
        ->assertJsonPath('youtube_embed_url', 'https://www.youtube.com/embed/dQw4w9WgXcQ')
        ->assertJsonPath('can_clip', false);

    $source = ClipSource::where('kind', 'youtube')->firstOrFail();

    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/clip-sources/{$source->id}/runs", ['request' => 'best bits', 'count' => 3, 'min_seconds' => 10, 'max_seconds' => 60])
        ->assertUnprocessable();
});

it('reads the video id from every YouTube link form, and refuses other links', function () {
    expect(ClipService::youtubeId('https://youtu.be/dQw4w9WgXcQ'))->toBe('dQw4w9WgXcQ')
        ->and(ClipService::youtubeId('https://www.youtube.com/shorts/dQw4w9WgXcQ'))->toBe('dQw4w9WgXcQ')
        ->and(ClipService::youtubeId('https://example.com/watch?v=dQw4w9WgXcQ'))->toBeNull();

    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/clip-sources', ['youtube_url' => 'https://example.com/video'])
        ->assertUnprocessable();
});

it('keeps a clip source private to its owner', function () {
    $source = ClipSource::create(['user_id' => $this->user->id, 'kind' => 'youtube', 'status' => 'ready', 'youtube_id' => 'dQw4w9WgXcQ']);
    $stranger = User::factory()->create();

    $this->actingAs($stranger, 'sanctum')->getJson("/api/v1/clip-sources/{$source->id}")->assertNotFound();
});
