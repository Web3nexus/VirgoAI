<?php

use App\Models\Generation;
use App\Models\Production;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

function finishedProduction(User $user, string $file = 'productions/x/final.mp4'): Production
{
    Storage::disk('public')->put($file, 'video-bytes');

    return Production::create([
        'user_id' => $user->id,
        'prompt' => 'A fox crosses a frozen river at dawn.',
        'format' => 'landscape_16_9',
        'quality' => '720p',
        'model_key' => 'claude-sonnet-5-5',
        'status' => 'succeeded',
        'output_path' => $file,
    ]);
}

it('deletes a finished video file for its owner and keeps the record', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    $production = finishedProduction($user);
    Storage::disk('public')->put("productions/{$production->id}/part-1.mp4", 'part');

    $this->deleteJson("/api/v1/productions/{$production->id}")->assertNoContent();

    Storage::disk('public')->assertMissing('productions/'.$production->id.'/final.mp4');
    expect($production->refresh()->media_deleted_at)->not->toBeNull()
        ->and($production->output_path)->toBeNull();
    $this->getJson('/api/v1/productions')->assertOk()->assertJsonCount(0, 'data');
});

it('refuses to delete a video that is still rendering', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    $production = finishedProduction($user);
    $production->forceFill(['status' => 'animating', 'output_path' => null])->save();

    $this->deleteJson("/api/v1/productions/{$production->id}")->assertStatus(409);
    expect($production->refresh()->media_deleted_at)->toBeNull();
});

it('will not let one account delete another account\'s video', function () {
    Storage::fake('public');
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $production = finishedProduction($owner);
    Sanctum::actingAs($intruder);

    $this->deleteJson("/api/v1/productions/{$production->id}")->assertNotFound();
    Storage::disk('public')->assertExists('productions/x/final.mp4');
});

it('removes files older than the retention period and keeps recent ones', function () {
    Storage::fake('public');
    config(['virgoai.media.retention_days' => 30]);
    $user = User::factory()->create();

    $old = Generation::create(['user_id' => $user->id, 'tool' => 'video', 'capability' => 'video', 'prompt' => 'old', 'status' => 'succeeded', 'output_type' => 'video', 'output_path' => 'generations/old.mp4']);
    $new = Generation::create(['user_id' => $user->id, 'tool' => 'video', 'capability' => 'video', 'prompt' => 'new', 'status' => 'succeeded', 'output_type' => 'video', 'output_path' => 'generations/new.mp4']);
    Storage::disk('public')->put('generations/old.mp4', 'a');
    Storage::disk('public')->put('generations/new.mp4', 'b');
    DB::table('generations')->where('id', $old->id)->update(['created_at' => now()->subDays(40)]);

    $this->artisan('media:prune')->assertSuccessful();

    Storage::disk('public')->assertMissing('generations/old.mp4');
    Storage::disk('public')->assertExists('generations/new.mp4');
    expect($old->refresh()->media_deleted_at)->not->toBeNull()
        ->and($new->refresh()->media_deleted_at)->toBeNull();
});
