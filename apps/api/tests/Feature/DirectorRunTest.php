<?php

use App\Ai\Contracts\ProviderAdapter;
use App\Ai\Data\GenerationRequest;
use App\Ai\Data\GenerationResult;
use App\Enums\Capability;
use App\Models\User;

/**
 * Test-only text model. It records the system prompt it was given and replies with a fixed reply,
 * so the director path can be tested end to end without a real provider.
 */
class FakeDirectorAdapter implements ProviderAdapter
{
    public static ?string $reply = null;
    public static ?string $systemSeen = null;

    public function key(): string
    {
        return 'fake';
    }

    public function capabilities(): array
    {
        return [Capability::Text];
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function generate(GenerationRequest $request): GenerationResult
    {
        self::$systemSeen = $request->options['system'] ?? null;

        return GenerationResult::success(
            output: ['text' => self::$reply ?? ''],
            providerKey: 'fake',
            modelKey: $request->modelKey,
        );
    }
}

beforeEach(function () {
    $this->user = User::factory()->create();
    config([
        'virgoai.providers.fake' => ['adapter' => FakeDirectorAdapter::class],
        'pricing.free_providers' => ['echo', 'fake'],
        'virgoai.voices' => ['af_bella', 'am_adam'],
    ]);
    FakeDirectorAdapter::$systemSeen = null;
});

function fakeTextModel(): void
{
    $provider = App\Models\AiProvider::create([
        'key' => 'fake', 'name' => 'Fake', 'status' => App\Enums\ProviderStatus::Active, 'priority' => 1,
    ]);
    $model = App\Models\AiModel::create([
        'ai_provider_id' => $provider->id, 'key' => 'fake-text', 'name' => 'Fake text',
        'enabled' => true, 'priority' => 1,
    ]);
    App\Models\ProviderCapability::create(['ai_model_id' => $model->id, 'capability' => Capability::Text->value]);
}

it('sends the director instructions with the connected voices and saves a cast with voices', function () {
    fakeTextModel();
    FakeDirectorAdapter::$reply = json_encode([
        'title' => 'Compass',
        'logline' => 'A woman follows a compass home.',
        'characters' => [[
            'character_id' => 'mia', 'name' => 'Mia',
            'identity_block' => 'Mia, 30, short red hair, green raincoat, freckles, carries a brass compass',
            'voice' => ['voice_id' => 'af_bella', 'speed' => 0.9, 'description' => 'warm, calm, mid pitch'],
        ]],
        'environments' => [[
            'environment_id' => 'kitchen', 'name' => 'Kitchen',
            'prompt' => 'A rain soaked kitchen at dusk with one warm lamp over a wooden table',
            'lighting' => 'warm lamp', 'time_of_day' => 'dusk', 'palette' => ['#A1B2C3'],
        ]],
        'sections' => [[
            'section_id' => 'hook', 'purpose' => 'hook', 'environment_id' => 'kitchen',
            'character_ids' => ['mia'], 'start_seconds' => 0, 'end_seconds' => 10, 'narration' => '',
        ]],
        'lines' => [[
            'line_id' => 'l1', 'section_id' => 'hook', 'speaker' => 'mia',
            'text' => 'Where did you put the compass?', 'emotion' => 'tense',
            'start_seconds' => 1, 'end_seconds' => 4,
        ]],
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/tools/story/run', [
            'prompt' => 'A mother and daughter argue about the last train',
            'model_key' => 'fake-text',
            'script_type' => 'dialogue',
            'skill' => 'director',
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'succeeded');

    // The model was told which voices it may use.
    expect(FakeDirectorAdapter::$systemSeen)->toContain('choose only from this list): af_bella, am_adam.');

    // The saved script names each character and keeps the voice assignment.
    $script = json_decode($response->json('data.output_text'), true);
    expect($script['audio_mode'])->toBe('dialogue');
    expect($script['characters'][0]['name'])->toBe('Mia');
    expect($script['characters'][0]['voice']['voice_id'])->toBe('af_bella');
    expect($script['lines'][0]['speaker'])->toBe('mia');
});

it('fails the run and keeps the reasons when the model names a voice that is not connected', function () {
    fakeTextModel();
    FakeDirectorAdapter::$reply = json_encode([
        'title' => 'Compass',
        'characters' => [[
            'character_id' => 'mia', 'name' => 'Mia',
            'identity_block' => 'Mia, 30, short red hair, green raincoat, freckles, carries a brass compass',
            'voice' => ['voice_id' => 'made_up_voice', 'speed' => 1.0, 'description' => 'warm'],
        ]],
        'environments' => [[
            'environment_id' => 'kitchen', 'name' => 'Kitchen',
            'prompt' => 'A rain soaked kitchen at dusk with one warm lamp over a wooden table',
            'lighting' => 'warm lamp', 'time_of_day' => 'dusk', 'palette' => ['#A1B2C3'],
        ]],
        'sections' => [[
            'section_id' => 'hook', 'purpose' => 'hook', 'environment_id' => 'kitchen',
            'character_ids' => ['mia'], 'start_seconds' => 0, 'end_seconds' => 10, 'narration' => '',
        ]],
        'lines' => [[
            'line_id' => 'l1', 'section_id' => 'hook', 'speaker' => 'mia', 'text' => 'Hello.',
            'start_seconds' => 1, 'end_seconds' => 2,
        ]],
    ]);

    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/tools/story/run', [
            'prompt' => 'A quiet argument in a kitchen',
            'model_key' => 'fake-text',
            'script_type' => 'dialogue',
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'failed')
        ->assertJsonPath('data.error', fn ($error) => str_contains($error, '"made_up_voice" is not a connected voice'));
});
