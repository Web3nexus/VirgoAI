<?php

use App\Enums\Capability;
use App\Enums\ProviderStatus;
use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\Generation;
use App\Models\Project;
use App\Models\ProviderCapability;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->user = User::factory()->create();
    Storage::fake('public');
});

/** A registry entry for one provider and model. */
function registerModel(string $provider, string $model, Capability $capability, int $priority = 1): AiModel
{
    $providerRow = AiProvider::firstOrCreate(['key' => $provider], [
        'name' => ucfirst($provider),
        'status' => ProviderStatus::Active,
        'priority' => $priority,
    ]);

    $modelRow = AiModel::create([
        'ai_provider_id' => $providerRow->id,
        'key' => $model,
        'name' => $model,
        'enabled' => true,
        'priority' => $priority,
    ]);

    ProviderCapability::create([
        'ai_model_id' => $modelRow->id,
        'capability' => $capability->value,
    ]);

    return $modelRow;
}

function tinyPngBase64(): string
{
    // A 1x1 transparent PNG.
    return base64_encode(base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII='
    ));
}

it('lists every tool with its models and whether each can run', function () {
    registerModel('fal', 'fal-ai/gpt-image-1.5', Capability::Image);
    config(['virgoai.credentials.FAL_KEY' => null]);
    putenv('FAL_KEY');
    unset($_ENV['FAL_KEY'], $_SERVER['FAL_KEY']);

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/tools')
        ->assertOk()
        ->assertJsonCount(6, 'data');

    $image = collect($response->json('data'))->firstWhere('key', 'image');
    expect($image['ready'])->toBeFalse();
    expect($image['models'][0]['key'])->toBe('fal-ai/gpt-image-1.5');
    expect($image['models'][0]['usable'])->toBeFalse();
    expect($image['models'][0]['reason'])->toContain('Credentials are missing');
});

it('runs the story tool on a registered text model and saves the result', function () {
    registerModel('echo', 'echo-1', Capability::Text);

    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/tools/story/run', ['prompt' => 'A fox learns to read', 'skill' => 'plain'])
        ->assertCreated()
        ->assertJsonPath('data.status', 'succeeded')
        ->assertJsonPath('data.output_type', 'text')
        ->assertJsonPath('data.model_key', 'echo-1');

    expect(Generation::where('user_id', $this->user->id)->count())->toBe(1);
});

it('rejects a model that does not support the tool', function () {
    registerModel('echo', 'echo-1', Capability::Text);

    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/tools/image/run', ['prompt' => 'A red bicycle', 'model_key' => 'echo-1'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('model_key');
});

it('refuses to attach a run to a project the user does not own', function () {
    registerModel('echo', 'echo-1', Capability::Text);
    $other = Project::factory()->create(['user_id' => User::factory()->create()->id]);

    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/tools/story/run', ['prompt' => 'A fox learns to read', 'project_id' => $other->id])
        ->assertStatus(422);
});

it('lists only the signed-in user runs, filtered by tool', function () {
    registerModel('echo', 'echo-1', Capability::Text);
    $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/tools/story/run', ['prompt' => 'Story one']);

    $other = User::factory()->create();
    Generation::create([
        'user_id' => $other->id, 'tool' => 'story', 'capability' => 'text',
        'prompt' => 'Not mine', 'status' => 'succeeded', 'output_type' => 'text',
    ]);

    $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/generations?tool=story')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.prompt', 'Story one');

    $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/generations?tool=image')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('rejects an unknown tool', function () {
    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/tools/hologram/run', ['prompt' => 'Anything'])
        ->assertNotFound();
});

it('never routes an image job to the text-only echo model', function () {
    // Registry row claims image, but the adapter only declares text. The adapter wins.
    registerModel('echo', 'echo-1', Capability::Text);
    registerModel('echo', 'echo-1-image', Capability::Image);

    $image = collect($this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/tools')
        ->json('data'))->firstWhere('key', 'image');

    $echo = collect($image['models'])->firstWhere('key', 'echo-1-image');
    expect($echo['usable'])->toBeFalse();
    expect($echo['reason'])->toContain('cannot do Image generation');
    expect($image['ready'])->toBeFalse();
});

it('describes the script choices for the story tool', function () {
    $story = collect($this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/tools')
        ->json('data'))->firstWhere('key', 'story');

    expect($story['choices']['script_type']['values'])->toHaveKeys(['story', 'dialogue']);
    expect($story['choices']['skill']['default'])->toBe('director');
});

it('fails a director run when the model does not return a script, and says why', function () {
    registerModel('echo', 'echo-1', Capability::Text);

    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/tools/story/run', [
            'prompt' => 'A mother and daughter argue about the last train',
            'script_type' => 'dialogue',
            'skill' => 'director',
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'failed')
        ->assertJsonPath('data.options.script_type', 'dialogue')
        ->assertJsonPath('data.options.skill', 'director')
        ->assertJsonPath('data.error', fn ($error) => str_contains($error, 'not a JSON object'));

    expect(Generation::first()->options)->not->toHaveKey('system');
});

it('runs a plain prompt without the director instructions', function () {
    $skill = App\Tools\Skills\DirectorSkill::systemPrompt('plain', 'story');
    expect($skill)->toBeNull();
});

it('asks for speaker ids in dialogue and narration per section in story', function () {
    $dialogue = App\Tools\Skills\DirectorSkill::systemPrompt('director', 'dialogue', ['af_bella']);
    $story = App\Tools\Skills\DirectorSkill::systemPrompt('director', 'story', []);

    expect($dialogue)->toContain('"speaker": "mia"')->toContain('Dialogue: every line');
    expect($dialogue)->toContain('Available voice_id values (choose only from this list): af_bella.');
    expect($story)->toContain('"lines": []')->toContain('No voices are connected yet.');
    expect($story)->toContain('"narration"')->not->toContain('Dialogue: every line');
});

it('rejects an unknown script type', function () {
    registerModel('echo', 'echo-1', Capability::Text);

    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/tools/story/run', ['prompt' => 'A fox', 'script_type' => 'musical'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('script_type');
});
