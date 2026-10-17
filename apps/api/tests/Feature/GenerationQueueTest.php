<?php

use App\Ai\GenerationCompleter;
use App\Enums\Capability;
use App\Jobs\PollGeneration;
use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\ModelPrice;
use App\Models\AiUsageRecord;
use App\Models\Generation;
use App\Models\ProviderCapability;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

const FAL_IMAGE_QUEUE = 'https://queue.fal.run/fal-ai/gpt-image-1.5';
const MEDIA_HOST = 'https://v3b.fal.media/files/out.png';

beforeEach(function () {
    Storage::fake('public');
    config(['virgoai.credentials.FAL_KEY' => 'test-fal-key']);
    $this->user = User::factory()->create();
    $provider = AiProvider::create(['key' => 'fal', 'name' => 'fal.ai', 'status' => 'active', 'priority' => 100]);

    // The models the sync would register for the two selectable endpoints, with fal's prices.
    foreach ([
        ['fal-ai/gpt-image-1.5', Capability::Image, 'image', 34_000],
        ['bytedance/seedance-2.0/image-to-video', Capability::Video, 'second', 302_400],
    ] as [$key, $capability, $unit, $costMicros]) {
        $model = AiModel::create(['ai_provider_id' => $provider->id, 'key' => $key, 'name' => $key, 'enabled' => true, 'priority' => 100]);
        ProviderCapability::create(['ai_model_id' => $model->id, 'capability' => $capability->value]);
        ModelPrice::create(['provider_key' => 'fal', 'model_key' => $key, 'unit' => $unit, 'cost_micros' => $costMicros, 'source' => 'fal_api']);
    }

    // Enough credit for the test runs.
    $this->user->forceFill(['balance_micros' => 10_000_000])->save();
});

/** A queued generation in the state the controller leaves it in. */
function queuedGeneration(User $user, string $outputType = 'image', ?string $statusUrl = null): Generation
{
    $base = 'https://queue.fal.run/fal-ai/gpt-image-1.5/requests/req-9';

    return Generation::create([
        'user_id' => $user->id,
        'tool' => $outputType === 'video' ? 'video' : 'image',
        'capability' => $outputType === 'video' ? 'video' : 'image',
        'provider_key' => 'fal',
        'model_key' => 'fal-ai/gpt-image-1.5',
        'prompt' => 'a brass compass',
        'options' => [],
        'status' => Generation::STATUS_RUNNING,
        'output_type' => $outputType,
        'provider_job' => [
            'endpoint_id' => 'fal-ai/gpt-image-1.5',
            'model_key' => 'fal-ai/gpt-image-1.5',
            'request_id' => 'req-9',
            'status_url' => $statusUrl ?? $base.'/status',
            'response_url' => $base,
        ],
    ]);
}

it('saves a finished image locally and closes the usage record', function () {
    $usage = AiUsageRecord::create(['capability' => 'image', 'status' => 'pending']);
    $generation = queuedGeneration($this->user);
    $generation->update(['provider_job' => [...$generation->provider_job, 'usage_record_id' => $usage->id]]);

    Http::fake([
        'queue.fal.run/*/status' => Http::response(['status' => 'COMPLETED']),
        'queue.fal.run/*/requests/req-9' => Http::response(['images' => [['url' => MEDIA_HOST, 'content_type' => 'image/png']]]),
        'v3b.fal.media/*' => Http::response('png-bytes', 200, ['Content-Type' => 'image/png']),
    ]);

    (new PollGeneration($generation->id))->handle(app(GenerationCompleter::class));

    $generation->refresh();

    expect($generation->status)->toBe(Generation::STATUS_SUCCEEDED)
        ->and($generation->output_path)->toBe("generations/{$generation->id}.png")
        ->and($generation->provider_job['output_url'])->toBe(MEDIA_HOST);
    Storage::disk('public')->assertExists($generation->output_path);
    expect($usage->fresh()->status)->toBe('succeeded');
});

it('re-queues itself while the job is still running', function () {
    Queue::fake();
    $generation = queuedGeneration($this->user);

    Http::fake(['queue.fal.run/*/status' => Http::response(['status' => 'IN_PROGRESS'])]);

    (new PollGeneration($generation->id))->handle(app(GenerationCompleter::class));

    expect($generation->fresh()->status)->toBe(Generation::STATUS_RUNNING);
    Queue::assertPushed(PollGeneration::class, fn ($job) => $job->generationId === $generation->id);
});

it('fails a render that runs past the maximum wait', function () {
    config(['virgoai.generation.max_wait_seconds' => 60]);
    $generation = queuedGeneration($this->user);
    $generation->forceFill(['created_at' => now()->subMinutes(10)])->saveQuietly();

    Http::fake(['queue.fal.run/*/status' => Http::response(['status' => 'IN_QUEUE'])]);

    (new PollGeneration($generation->id))->handle(app(GenerationCompleter::class));

    $generation->refresh();

    expect($generation->status)->toBe(Generation::STATUS_FAILED)
        ->and($generation->error)->toContain('did not finish in time');
});

it('fails the run when the finished file comes from an unknown host', function () {
    $generation = queuedGeneration($this->user);

    Http::fake([
        'queue.fal.run/*/status' => Http::response(['status' => 'COMPLETED']),
        'queue.fal.run/*/requests/req-9' => Http::response(['images' => [['url' => 'https://evil.example/out.png']]]),
    ]);

    (new PollGeneration($generation->id))->handle(app(GenerationCompleter::class));

    $generation->refresh();

    expect($generation->status)->toBe(Generation::STATUS_FAILED)
        ->and($generation->error)->toContain('could not be saved');
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'evil.example'));
});

it('does nothing for a generation that already finished', function () {
    $generation = queuedGeneration($this->user);
    $generation->update(['status' => Generation::STATUS_SUCCEEDED]);

    Http::fake();

    (new PollGeneration($generation->id))->handle(app(GenerationCompleter::class));

    Http::assertNothingSent();
});

it('queues an image run and returns it as running', function () {
    Queue::fake();
    Http::fake([FAL_IMAGE_QUEUE => Http::response([
        'request_id' => 'req-9',
        'status_url' => 'https://queue.fal.run/fal-ai/gpt-image-1.5/requests/req-9/status',
        'response_url' => 'https://queue.fal.run/fal-ai/gpt-image-1.5/requests/req-9',
    ])]);

    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/tools/image/run', [
            'prompt' => 'A brass compass on a wet wooden table',
            'model_key' => 'fal-ai/gpt-image-1.5',
            'size' => 'vertical_9_16',
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'running')
        ->assertJsonPath('data.output_type', 'image');

    Queue::assertPushed(PollGeneration::class);
});

it('queues a video run from a keyframe and sends the Seedance payload', function () {
    Queue::fake();
    Http::fake(['queue.fal.run/bytedance/*' => Http::response([
        'request_id' => 'req-v',
        'status_url' => 'https://queue.fal.run/bytedance/seedance-2.0/image-to-video/requests/req-v/status',
        'response_url' => 'https://queue.fal.run/bytedance/seedance-2.0/image-to-video/requests/req-v',
    ])]);

    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/tools/video/run', [
            'prompt' => 'the compass needle turns slowly',
            'model_key' => 'bytedance/seedance-2.0/image-to-video',
            'size' => 'vertical_9_16',
            'image_url' => 'https://v3b.fal.media/files/keyframe.png',
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'running')
        ->assertJsonPath('data.output_type', 'video');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'bytedance/seedance-2.0/image-to-video')
        && $request['image_url'] === 'https://v3b.fal.media/files/keyframe.png'
        && $request['aspect_ratio'] === '9:16'
        && $request['generate_audio'] === true);
});

it('requires a keyframe for a video run', function () {
    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/tools/video/run', [
            'prompt' => 'the compass needle turns slowly',
            'model_key' => 'bytedance/seedance-2.0/image-to-video',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('image_url');
});

it('rejects a keyframe link that is not https', function () {
    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/tools/video/run', [
            'prompt' => 'the compass needle turns slowly',
            'image_url' => 'http://v3b.fal.media/files/keyframe.png',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('image_url');
});

it('shows only the signed-in user their own run', function () {
    $run = queuedGeneration($this->user);
    $stranger = User::factory()->create();

    $this->actingAs($this->user, 'sanctum')
        ->getJson("/api/v1/generations/{$run->id}")
        ->assertOk()
        ->assertJsonPath('data.status', 'running');

    $this->actingAs($stranger, 'sanctum')
        ->getJson("/api/v1/generations/{$run->id}")
        ->assertNotFound();
});

it('saves a run that fal rejects at submit, with the reason', function () {
    Queue::fake();
    Http::fake([FAL_IMAGE_QUEUE => Http::response(['detail' => 'Unauthorized'], 401)]);

    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/tools/image/run', [
            'prompt' => 'A red bicycle',
            'model_key' => 'fal-ai/gpt-image-1.5',
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'failed')
        ->assertJsonPath('data.error', 'Fal submit request failed with HTTP 401.');

    Queue::assertNotPushed(PollGeneration::class);
});
