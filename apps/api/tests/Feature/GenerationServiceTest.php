<?php

use App\Ai\Data\GenerationRequest;
use App\Ai\GenerationService;
use App\Enums\Capability;
use App\Enums\ProviderStatus;
use App\Enums\TaskStatus;
use App\Enums\WorkflowStatus;
use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\AiUsageRecord;
use App\Models\Project;
use App\Models\ProviderCapability;
use App\Models\User;
use App\Workflows\ApprovalService;
use App\Workflows\WorkflowEngine;

function seedEchoForGeneration(): AiProvider
{
    $provider = AiProvider::create([
        'key' => 'echo',
        'name' => 'Echo',
        'status' => ProviderStatus::Active,
        'priority' => 1,
    ]);

    $model = AiModel::create([
        'ai_provider_id' => $provider->id,
        'key' => 'echo-1',
        'name' => 'Echo 1',
        'enabled' => true,
        'priority' => 1,
    ]);

    ProviderCapability::create([
        'ai_model_id' => $model->id,
        'capability' => Capability::Text->value,
    ]);

    return $provider;
}

it('routes a generation and records usage', function () {
    seedEchoForGeneration();

    $result = app(GenerationService::class)->generate(new GenerationRequest(
        capability: Capability::Text,
        modelKey: '',
        prompt: 'write a story',
    ));

    expect($result->ok)->toBeTrue()
        ->and($result->providerKey)->toBe('echo')
        ->and($result->modelKey)->toBe('echo-1');

    $record = AiUsageRecord::first();

    expect($record)->not->toBeNull()
        ->and($record->status)->toBe('succeeded')
        ->and($record->capability)->toBe(Capability::Text)
        ->and($record->cost_usd)->toEqual(0.0);
});

it('returns a failure result when no provider is configured', function () {
    $result = app(GenerationService::class)->generate(new GenerationRequest(
        capability: Capability::Text,
        modelKey: '',
        prompt: 'write a story',
    ));

    expect($result->ok)->toBeFalse()
        ->and($result->error)->toContain('No configured provider');

    expect(AiUsageRecord::count())->toBe(0);
});

it('runs the provider-backed pipeline through a checkpoint', function () {
    seedEchoForGeneration();

    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $engine = app(WorkflowEngine::class);
    $workflow = $engine->start($project, 'content_pipeline');
    $workflow->load('tasks');

    // guided_only + guided => story pauses for approval; downstream stays pending.
    expect($workflow->status)->toBe(WorkflowStatus::AwaitingApproval)
        ->and($workflow->tasks->firstWhere('key', 'story')->status)->toBe(TaskStatus::AwaitingApproval)
        ->and($workflow->tasks->firstWhere('key', 'script')->status)->toBe(TaskStatus::Pending);

    expect(AiUsageRecord::where('status', 'succeeded')->count())->toBe(1);

    app(ApprovalService::class)->approve($workflow->approvalRequests()->first(), $user);

    $workflow->refresh()->load('tasks');

    expect($workflow->status)->toBe(WorkflowStatus::Completed)
        ->and($workflow->tasks->where('status', TaskStatus::Completed))->toHaveCount(3);

    expect(AiUsageRecord::where('status', 'succeeded')->count())->toBe(3);
});

it('marks a provider-backed workflow as failed when no provider can serve it', function () {
    // No provider seeded.
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $workflow = app(WorkflowEngine::class)->start($project, 'content_pipeline');
    $workflow->load('tasks');

    // story is provider-backed with no approval reached; it should fail immediately.
    expect($workflow->tasks->firstWhere('key', 'story')->status)->toBe(TaskStatus::Failed)
        ->and($workflow->status)->toBe(WorkflowStatus::Failed);
});

it('lists workflow templates over the API', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/workflow-templates')
        ->assertOk()
        ->assertJsonFragment(['key' => 'content_pipeline'])
        ->assertJsonFragment(['key' => 'test_pipeline']);
});
