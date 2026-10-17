<?php

use App\Ai\Adapters\AnthropicAdapter;
use App\Ai\Anthropic\AnthropicModelSync;
use App\Ai\Data\GenerationRequest;
use App\Enums\Capability;
use App\Models\AiModel;
use Illuminate\Support\Facades\Http;

const ANTHROPIC_MESSAGES = 'https://api.anthropic.com/v1/messages';

beforeEach(function () {
    config(['virgoai.credentials.ANTHROPIC_API_KEY' => 'test-anthropic-key']);
});

function textRequest(string $prompt = 'Write a fox story', array $options = [], string $model = 'claude-sonnet-5-5'): GenerationRequest
{
    return new GenerationRequest(capability: Capability::Text, modelKey: $model, prompt: $prompt, options: $options);
}

it('is text only and not configured without a key', function () {
    config(['virgoai.credentials.ANTHROPIC_API_KEY' => null]);
    putenv('ANTHROPIC_API_KEY');
    Http::fake();

    $adapter = new AnthropicAdapter;

    expect($adapter->capabilities())->toBe([Capability::Text])
        ->and($adapter->isConfigured())->toBeFalse()
        ->and($adapter->generate(textRequest())->error)->toContain('not configured');
    Http::assertNothingSent();
});

it('sends the prompt and system prompt and returns the text', function () {
    Http::fake([ANTHROPIC_MESSAGES => Http::response([
        'content' => [['type' => 'text', 'text' => '{"title":"Fox"}']],
        'stop_reason' => 'end_turn',
        'usage' => ['input_tokens' => 120, 'output_tokens' => 40],
    ])]);

    $result = (new AnthropicAdapter)->generate(textRequest('Write a fox story', ['system' => 'You are a director.']));

    expect($result->ok)->toBeTrue()
        ->and($result->output['text'])->toBe('{"title":"Fox"}')
        ->and($result->usage)->toBe(['input_tokens' => 120, 'output_tokens' => 40])
        ->and($result->modelKey)->toBe('claude-sonnet-5-5');

    Http::assertSent(fn ($request) => $request->url() === ANTHROPIC_MESSAGES
        && $request->hasHeader('x-api-key', 'test-anthropic-key')
        && $request->hasHeader('anthropic-version', '2023-06-01')
        && $request['model'] === 'claude-sonnet-5-5'
        && $request['system'] === 'You are a director.'
        && $request['messages'] === [['role' => 'user', 'content' => 'Write a fox story']]
        && $request['max_tokens'] === 8192);
});

it('joins multiple text blocks and ignores non-text blocks', function () {
    Http::fake([ANTHROPIC_MESSAGES => Http::response([
        'content' => [
            ['type' => 'text', 'text' => 'Hello '],
            ['type' => 'thinking', 'thinking' => 'hidden'],
            ['type' => 'text', 'text' => 'world'],
        ],
        'stop_reason' => 'end_turn',
    ])]);

    expect((new AnthropicAdapter)->generate(textRequest())->output['text'])->toBe('Hello world');
});

it('fails a reply cut off at the token limit rather than saving broken JSON', function () {
    Http::fake([ANTHROPIC_MESSAGES => Http::response([
        'content' => [['type' => 'text', 'text' => '{"title":"Fo']],
        'stop_reason' => 'max_tokens',
    ])]);

    $result = (new AnthropicAdapter)->generate(textRequest());

    expect($result->ok)->toBeFalse()
        ->and($result->error)->toContain('token limit');
});

it('reports the provider message without leaking the key', function () {
    Http::fake([ANTHROPIC_MESSAGES => Http::response(['error' => ['message' => 'invalid x-api-key']], 401)]);

    $result = (new AnthropicAdapter)->generate(textRequest());

    expect($result->ok)->toBeFalse()
        ->and($result->error)->toContain('HTTP 401')
        ->and($result->error)->toContain('invalid x-api-key')
        ->and($result->error)->not->toContain('test-anthropic-key');
});

it('refuses a non-text capability before any HTTP call', function () {
    Http::fake();

    $result = (new AnthropicAdapter)->generate(new GenerationRequest(
        capability: Capability::Image,
        modelKey: 'claude-sonnet-5-5',
        prompt: 'a fox',
    ));

    expect($result->ok)->toBeFalse();
    Http::assertNothingSent();
});

it('reads the model list from the API key and keeps only active models', function () {
    Http::fake([AnthropicModelSync::MODELS_URL.'*' => Http::sequence()
        ->push([
            'data' => [
                ['id' => 'claude-sonnet-5-5', 'display_name' => 'Claude Sonnet 5.5', 'lifecycle' => 'active', 'max_input_tokens' => 200000, 'max_tokens' => 64000],
                ['id' => 'claude-old', 'display_name' => 'Claude Old', 'lifecycle' => 'deprecated'],
            ],
            'has_more' => true,
            'last_id' => 'claude-old',
        ])
        ->push([
            'data' => [['id' => 'claude-opus-5-5', 'display_name' => 'Claude Opus 5.5', 'lifecycle' => 'active']],
            'has_more' => false,
            'last_id' => 'claude-opus-5-5',
        ]),
    ]);

    $summary = app(AnthropicModelSync::class)->sync();

    expect($summary)->toMatchArray(['provider' => 'anthropic', 'upserted' => 2, 'skipped' => 1]);

    Http::assertSent(fn ($request) => $request->hasHeader('x-api-key', 'test-anthropic-key')
        && $request->hasHeader('anthropic-version', '2023-06-01'));
    Http::assertSent(fn ($request) => ($request->data()['after_id'] ?? null) === 'claude-old');

    expect(AiModel::where('key', 'claude-sonnet-5-5')->exists())->toBeTrue()
        ->and(AiModel::where('key', 'claude-old')->exists())->toBeFalse();
});

it('refuses the model sync without a key', function () {
    config(['virgoai.credentials.ANTHROPIC_API_KEY' => null]);
    putenv('ANTHROPIC_API_KEY');
    Http::fake();

    expect(fn () => app(AnthropicModelSync::class)->sync())
        ->toThrow(RuntimeException::class, 'ANTHROPIC_API_KEY is not configured');
    Http::assertNothingSent();
});
