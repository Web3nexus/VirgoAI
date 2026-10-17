<?php

use App\Ai\Data\GenerationRequest;
use App\Ai\Fal\FalEndpoints;
use App\Enums\Capability;
use App\Models\User;
use App\Skills\SkillLibrary;
use Database\Seeders\AiRegistrySeeder;
use Laravel\Sanctum\Sanctum;

function falRequest(Capability $capability, string $modelKey, array $options = [], array $inputs = []): GenerationRequest
{
    return new GenerationRequest(capability: $capability, modelKey: $modelKey, prompt: 'a brass compass on a wet table', inputs: $inputs, options: $options);
}

it('gives a skill the capability its mode renders, so a thumbnail is an image and a short film is a video', function () {
    expect(SkillLibrary::get('thumbnail')['mode'])->toBe('image');

    $skills = collect(SkillLibrary::search());
    expect($skills->firstWhere('key', 'thumbnail')['capability'])->toBe('image')
        ->and($skills->firstWhere('key', 'thumbnail')['default_model'])->toBe(FalEndpoints::IMAGE_TEXT)
        ->and($skills->firstWhere('key', 'video_generator')['capability'])->toBe('video')
        ->and($skills->firstWhere('key', 'video_generator')['default_model'])->toBe(FalEndpoints::VIDEO_IMAGE)
        ->and($skills->firstWhere('key', 'storyboard')['default_model'])->toBe('claude-sonnet-5-5');
});

it('lists the studio models by what they make, with a default for each', function () {
    $this->seed(AiRegistrySeeder::class);
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $data = $this->getJson('/api/v1/model-options')->assertOk()->json('data');

    expect($data['video']['default'])->toBe(FalEndpoints::VIDEO_IMAGE)
        ->and($data['image']['default'])->toBe(FalEndpoints::IMAGE_TEXT)
        ->and($data['text']['default'])->toBe('claude-sonnet-5-5');

    $videoKeys = collect($data['video']['models'])->pluck('key');
    expect($videoKeys)->toContain(FalEndpoints::VIDEO_HAILUO, FalEndpoints::VIDEO_KLING)
        ->and($videoKeys)->not->toContain('echo-1')
        ->and(collect($data['image']['models'])->pluck('key'))->toContain(FalEndpoints::IMAGE_FLUX_SCHNELL);
});

it('refuses a full video with a video model that is not offered', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/productions', [
        'prompt' => 'A fox crosses a frozen river at dawn.',
        'format' => 'landscape_16_9',
        'quality' => '720p',
        'video_model' => 'fal-ai/veo3.1',
    ])->assertUnprocessable()->assertJsonValidationErrors(['video_model']);
});

it('builds the request each cheap endpoint expects', function () {
    $hailuo = FalEndpoints::payload(FalEndpoints::VIDEO_HAILUO, falRequest(Capability::Video, FalEndpoints::VIDEO_HAILUO, ['duration' => 5, 'resolution' => '720p'], ['image_url' => 'https://cdn.test/k.png']));
    expect($hailuo)->toMatchArray(['duration' => '6', 'resolution' => '768P', 'image_url' => 'https://cdn.test/k.png']);

    $kling = FalEndpoints::payload(FalEndpoints::VIDEO_KLING, falRequest(Capability::Video, FalEndpoints::VIDEO_KLING, ['duration' => 12], ['image_url' => 'https://cdn.test/k.png']));
    expect($kling)->toHaveKey('start_image_url')->and($kling)->not->toHaveKey('image_url')->and($kling['duration'])->toBe('12');

    $flux = FalEndpoints::payload(FalEndpoints::IMAGE_FLUX_SCHNELL, falRequest(Capability::Image, FalEndpoints::IMAGE_FLUX_SCHNELL, ['size' => 'vertical_9_16']));
    expect($flux['image_size'])->toBe('portrait_16_9');

    $nano = FalEndpoints::payload(FalEndpoints::IMAGE_NANO_BANANA, falRequest(Capability::Image, FalEndpoints::IMAGE_NANO_BANANA, ['size' => 'landscape_16_9']));
    expect($nano)->toMatchArray(['aspect_ratio' => '16:9', 'resolution' => '1K']);
});

it('builds the Veo and Gemini requests in the shapes their schemas accept', function () {
    $veo = FalEndpoints::payload(FalEndpoints::VIDEO_VEO_31_FAST, falRequest(Capability::Video, FalEndpoints::VIDEO_VEO_31_FAST, ['size' => 'vertical_9_16', 'duration' => 5, 'resolution' => '1080p'], ['image_url' => 'https://cdn.test/k.png']));
    expect($veo)->toMatchArray(['duration' => '6s', 'aspect_ratio' => '9:16', 'resolution' => '1080p', 'generate_audio' => true]);

    $square = FalEndpoints::payload(FalEndpoints::VIDEO_VEO_3_FAST, falRequest(Capability::Video, FalEndpoints::VIDEO_VEO_3_FAST, ['size' => 'square_1_1', 'duration' => 30], ['image_url' => 'https://cdn.test/k.png']));
    expect($square)->toMatchArray(['duration' => '8s', 'aspect_ratio' => 'auto', 'resolution' => '720p']);

    $gemini = FalEndpoints::payload(FalEndpoints::IMAGE_GEMINI, falRequest(Capability::Image, FalEndpoints::IMAGE_GEMINI, ['size' => 'portrait_4_5']));
    expect($gemini)->toHaveKey('aspect_ratio')->and($gemini['num_images'])->toBe(1);
});

it('requires a reference image for Veo, as the other image-to-video models do', function () {
    FalEndpoints::payload(FalEndpoints::VIDEO_VEO_31_FAST, falRequest(Capability::Video, FalEndpoints::VIDEO_VEO_31_FAST));
})->throws(InvalidArgumentException::class);
