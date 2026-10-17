<?php

use App\Ai\Adapters\FalAdapter;
use App\Ai\Contracts\AsyncProviderAdapter;
use App\Ai\Data\GenerationRequest;
use App\Ai\Fal\FalEndpoints;
use App\Enums\Capability;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

const FAL_QUEUE = 'https://queue.fal.run';
const FAL_IMAGE_URL = FAL_QUEUE.'/fal-ai/gpt-image-1.5';
const FAL_EDIT_URL = FAL_QUEUE.'/fal-ai/gpt-image-1.5/edit';
const FAL_VIDEO_URL = FAL_QUEUE.'/bytedance/seedance-2.0/image-to-video';

beforeEach(function () {
    config(['virgoai.credentials.FAL_KEY' => 'test-fal-key']);
});

/** A queue submit response for one endpoint. */
function falSubmitResponse(string $endpoint, string $requestId = 'req-1'): array
{
    return [
        'request_id' => $requestId,
        'status_url' => FAL_QUEUE."/{$endpoint}/requests/{$requestId}/status",
        'response_url' => FAL_QUEUE."/{$endpoint}/requests/{$requestId}",
    ];
}

function falJob(string $endpoint, string $modelKey, string $requestId = 'req-1'): array
{
    $submit = falSubmitResponse($endpoint, $requestId);

    return [
        'endpoint_id' => $endpoint,
        'model_key' => $modelKey,
        'request_id' => $requestId,
        'status_url' => $submit['status_url'],
        'response_url' => $submit['response_url'],
    ];
}

function imageRequest(array $options = [], array $inputs = [], string $modelKey = 'fal-ai/gpt-image-1.5'): GenerationRequest
{
    return new GenerationRequest(
        capability: Capability::Image,
        modelKey: $modelKey,
        prompt: 'a brass compass on a wet table',
        inputs: $inputs,
        options: $options,
    );
}

function videoRequest(array $options = [], string $imageUrl = 'https://v3b.fal.media/files/keyframe.png'): GenerationRequest
{
    return new GenerationRequest(
        capability: Capability::Video,
        modelKey: 'bytedance/seedance-2.0/image-to-video',
        prompt: 'the compass needle turns slowly',
        inputs: ['image_url' => $imageUrl],
        options: $options,
    );
}

it('is an async adapter for image and video only', function () {
    $adapter = new FalAdapter;

    expect($adapter)->toBeInstanceOf(AsyncProviderAdapter::class)
        ->and($adapter->capabilities())->toEqualCanonicalizing([Capability::Image, Capability::Video])
        ->and($adapter->isConfigured())->toBeTrue();
});

it('is not configured without a key and makes no HTTP call', function () {
    config(['virgoai.credentials.FAL_KEY' => null]);
    putenv('FAL_KEY');
    Http::fake();

    $result = (new FalAdapter)->generate(imageRequest());

    expect($result->ok)->toBeFalse()
        ->and($result->error)->toContain('not configured');
    Http::assertNothingSent();
});

it('refuses a model this app does not call', function () {
    Http::fake();

    $result = (new FalAdapter)->generate(imageRequest(modelKey: 'fal-ai/flux/schnell'));

    expect($result->ok)->toBeFalse()
        ->and($result->error)->toContain('not one this app supports');
    Http::assertNothingSent();
});

it('submits a text-to-image job with the verified payload and returns it queued', function () {
    Http::fake([FAL_IMAGE_URL => Http::response(falSubmitResponse('fal-ai/gpt-image-1.5'))]);

    $result = (new FalAdapter)->generate(imageRequest(['size' => 'vertical_9_16']));

    expect($result->ok)->toBeTrue()
        ->and($result->isQueued())->toBeTrue()
        ->and($result->job)->toMatchArray([
            'endpoint_id' => 'fal-ai/gpt-image-1.5',
            'model_key' => 'fal-ai/gpt-image-1.5',
            'request_id' => 'req-1',
        ]);

    Http::assertSent(fn ($request) => $request->url() === FAL_IMAGE_URL
        && $request->hasHeader('Authorization', 'Key test-fal-key')
        && $request['prompt'] === 'a brass compass on a wet table'
        && $request['image_size'] === '1024x1536'
        && $request['quality'] === 'medium'
        && $request['num_images'] === 1
        && $request['output_format'] === 'png');
});

it('uses the edit endpoint when a reference image is given', function () {
    Http::fake([FAL_EDIT_URL => Http::response(falSubmitResponse('fal-ai/gpt-image-1.5/edit'))]);

    $result = (new FalAdapter)->generate(imageRequest(['size' => 'square_1_1'], ['image_url' => 'https://v3b.fal.media/files/ref.png']));

    expect($result->isQueued())->toBeTrue()
        ->and($result->job['endpoint_id'])->toBe('fal-ai/gpt-image-1.5/edit');

    Http::assertSent(fn ($request) => $request->url() === FAL_EDIT_URL
        && $request['image_urls'] === ['https://v3b.fal.media/files/ref.png']
        && $request['image_size'] === '1024x1024');
});

it('submits an image-to-video job with the verified Seedance payload', function () {
    Http::fake([FAL_VIDEO_URL => Http::response(falSubmitResponse('bytedance/seedance-2.0/image-to-video'))]);

    $result = (new FalAdapter)->generate(videoRequest(['size' => 'vertical_9_16']));

    expect($result->isQueued())->toBeTrue();

    Http::assertSent(fn ($request) => $request->url() === FAL_VIDEO_URL
        && $request['prompt'] === 'the compass needle turns slowly'
        && $request['image_url'] === 'https://v3b.fal.media/files/keyframe.png'
        && $request['resolution'] === '720p'
        && $request['duration'] === '5'
        && $request['aspect_ratio'] === '9:16'
        && $request['generate_audio'] === true);
});

it('clamps a Seedance duration to 4 to 15 seconds', function () {
    Http::fake([FAL_VIDEO_URL => Http::response(falSubmitResponse('bytedance/seedance-2.0/image-to-video'))]);

    (new FalAdapter)->generate(videoRequest(['duration' => 60, 'generate_audio' => false]));

    Http::assertSent(fn ($request) => $request['duration'] === '15' && $request['generate_audio'] === false);
});

it('refuses video without a reference image before any HTTP call', function () {
    Http::fake();

    $request = new GenerationRequest(
        capability: Capability::Video,
        modelKey: 'bytedance/seedance-2.0/image-to-video',
        prompt: 'a slow pan',
    );

    $result = (new FalAdapter)->generate($request);

    expect($result->ok)->toBeFalse()
        ->and($result->error)->toContain('reference image');
    Http::assertNothingSent();
});

it('reports a submit failure without leaking the key', function () {
    Http::fake([FAL_IMAGE_URL => Http::response(['detail' => 'Unauthorized'], 401)]);

    $result = (new FalAdapter)->generate(imageRequest());

    expect($result->ok)->toBeFalse()
        ->and($result->error)->toBe('Fal submit request failed with HTTP 401.')
        ->and($result->error)->not->toContain('test-fal-key');
});

it('reports when a network error stops the submit', function () {
    Http::fake(fn () => throw new ConnectionException('timed out'));

    $result = (new FalAdapter)->generate(imageRequest());

    expect($result->ok)->toBeFalse()
        ->and($result->error)->toContain('Could not reach fal');
});

it('stays queued while fal is still working', function () {
    $job = falJob('fal-ai/gpt-image-1.5', 'fal-ai/gpt-image-1.5');
    Http::fake([$job['status_url'] => Http::response(['status' => 'IN_PROGRESS'])]);

    $result = (new FalAdapter)->poll($job);

    expect($result->isQueued())->toBeTrue()
        ->and($result->ok)->toBeTrue();
});

it('returns the media URL once the job completes', function () {
    $job = falJob('fal-ai/gpt-image-1.5', 'fal-ai/gpt-image-1.5');
    Http::fake([
        $job['status_url'] => Http::response(['status' => 'COMPLETED']),
        $job['response_url'] => Http::response(['images' => [['url' => 'https://v3b.fal.media/files/out.png', 'content_type' => 'image/png']]]),
    ]);

    $result = (new FalAdapter)->poll($job);

    expect($result->ok)->toBeTrue()
        ->and($result->isQueued())->toBeFalse()
        ->and($result->output)->toBe(['url' => 'https://v3b.fal.media/files/out.png', 'mime' => 'image/png']);
});

it('returns the video URL for a finished Seedance job', function () {
    $job = falJob('bytedance/seedance-2.0/image-to-video', 'bytedance/seedance-2.0/image-to-video');
    Http::fake([
        $job['status_url'] => Http::response(['status' => 'COMPLETED']),
        $job['response_url'] => Http::response(['video' => ['url' => 'https://storage.googleapis.com/falserverless/out.mp4'], 'seed' => 42]),
    ]);

    $result = (new FalAdapter)->poll($job);

    expect($result->output['url'])->toBe('https://storage.googleapis.com/falserverless/out.mp4')
        ->and($result->output['mime'])->toBe('video/mp4');
});

it('fails a completed job that returned no media', function () {
    $job = falJob('fal-ai/gpt-image-1.5', 'fal-ai/gpt-image-1.5');
    Http::fake([
        $job['status_url'] => Http::response(['status' => 'COMPLETED']),
        $job['response_url'] => Http::response(['images' => []]),
    ]);

    $result = (new FalAdapter)->poll($job);

    expect($result->ok)->toBeFalse()
        ->and($result->error)->toContain('no media');
});

it('fails when fal rejects the status check with a client error', function () {
    $job = falJob('fal-ai/gpt-image-1.5', 'fal-ai/gpt-image-1.5');
    Http::fake([$job['status_url'] => Http::response(['detail' => 'Not found'], 404)]);

    $result = (new FalAdapter)->poll($job);

    expect($result->ok)->toBeFalse()
        ->and($result->error)->toBe('Fal status request failed with HTTP 404.');
});

it('keeps a job queued through a server error or a network blip', function () {
    $job = falJob('fal-ai/gpt-image-1.5', 'fal-ai/gpt-image-1.5');
    Http::fake([$job['status_url'] => Http::response('oops', 503)]);

    expect((new FalAdapter)->poll($job)->isQueued())->toBeTrue();

    Http::fake(fn () => throw new ConnectionException('reset'));

    expect((new FalAdapter)->poll($job)->isQueued())->toBeTrue();
});

it('refuses to poll a status URL outside the fal queue', function () {
    Http::fake();

    $job = falJob('fal-ai/gpt-image-1.5', 'fal-ai/gpt-image-1.5');
    $job['status_url'] = 'https://evil.example/status';

    $result = (new FalAdapter)->poll($job);

    expect($result->ok)->toBeFalse()
        ->and($result->error)->toContain('missing its queue URLs');
    Http::assertNothingSent();
});

it('keeps the payload map in sync with the endpoint list', function () {
    foreach (FalEndpoints::all() as $endpointId => $spec) {
        expect($spec['fields'])->not->toBeEmpty("fields for {$endpointId}");
    }

    expect(FalEndpoints::selectableIds())->toBe([FalEndpoints::IMAGE_TEXT, FalEndpoints::VIDEO_IMAGE]);
});
