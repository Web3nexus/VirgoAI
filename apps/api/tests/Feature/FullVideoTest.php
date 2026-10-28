<?php

use App\Clips\Ffmpeg;
use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\Generation;
use App\Models\ModelPrice;
use App\Models\Production;
use App\Models\ProviderCapability;
use App\Models\User;
use App\Productions\ProductionRunner;
use App\Settings\ProviderCredentials;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/** A director script with two story scenes, in the shape the director must return. */
function fullVideoScript(): array
{
    $script = directorScript();
    $script['characters'][0]['voice'] = ['voice_id' => null, 'speed' => 1.0, 'description' => ''];
    $script['lines'] = [];
    $script['sections'] = [
        ['section_id' => 'hook', 'purpose' => 'hook', 'environment_id' => 'kitchen', 'character_ids' => ['mia'], 'start_seconds' => 0, 'end_seconds' => 5, 'narration' => 'Mia finds the compass on the table.'],
        ['section_id' => 'landing', 'purpose' => 'landing', 'environment_id' => 'kitchen', 'character_ids' => ['mia'], 'start_seconds' => 5, 'end_seconds' => 10, 'narration' => 'She walks out into the rain.'],
    ];

    return $script;
}

function sampleMp4(): string
{
    $path = sys_get_temp_dir().'/full-'.uniqid().'.mp4';
    exec(sprintf('ffmpeg -y -f lavfi -i testsrc=duration=1:size=320x240:rate=10 -f lavfi -i sine=duration=1 -shortest -c:v libx264 -pix_fmt yuv420p -c:a aac %s 2>/dev/null', escapeshellarg($path)));
    $bytes = (string) file_get_contents($path);
    @unlink($path);

    return $bytes;
}

function hasFfmpeg(): bool
{
    exec('command -v ffmpeg 2>/dev/null', $out, $code);

    return $code === 0;
}

/**
 * The provider calls a full video makes, answered per request: each scene gets its own job, and every download
 * gets a fresh body (a reused fake response has a body that can only be read once).
 */
function fullVideoHttp(string $keyframeStatus = 'COMPLETED'): void
{
    $imageQueue = 'https://queue.fal.run/fal-ai/gpt-image-1.5';
    $motionQueue = 'https://queue.fal.run/bytedance/seedance-2.0/image-to-video';
    $imageJobs = 0;
    $motionJobs = 0;

    Http::fake(function (Request $request) use ($imageQueue, $motionQueue, $keyframeStatus, &$imageJobs, &$motionJobs) {
        $url = $request->url();

        if (str_starts_with($url, 'https://api.anthropic.com/v1/messages')) {
            return Http::response([
                'content' => [['type' => 'text', 'text' => "```json\n".json_encode(fullVideoScript())."\n```"]],
                'stop_reason' => 'end_turn',
                'usage' => ['input_tokens' => 600, 'output_tokens' => 400],
            ]);
        }

        if ($url === $imageQueue) {
            $id = 'k'.(++$imageJobs);

            return Http::response(['request_id' => $id, 'status_url' => "{$imageQueue}/requests/{$id}/status", 'response_url' => "{$imageQueue}/requests/{$id}"]);
        }
        if (preg_match('~^'.preg_quote($imageQueue, '~').'/requests/(k\d+)/status$~', $url, $m)) {
            return Http::response(['status' => $keyframeStatus]);
        }
        if (preg_match('~^'.preg_quote($imageQueue, '~').'/requests/(k\d+)$~', $url, $m)) {
            return Http::response(['images' => [['url' => "https://v3b.fal.media/files/{$m[1]}.png", 'content_type' => 'image/png']]]);
        }

        if ($url === $motionQueue) {
            $id = 'm'.(++$motionJobs);

            return Http::response(['request_id' => $id, 'status_url' => "{$motionQueue}/requests/{$id}/status", 'response_url' => "{$motionQueue}/requests/{$id}"]);
        }
        if (preg_match('~/requests/(m\d+)/status$~', $url)) {
            return Http::response(['status' => 'COMPLETED']);
        }
        if (preg_match('~/requests/(m\d+)$~', $url, $m)) {
            return Http::response(['video' => ['url' => "https://storage.googleapis.com/falserverless/{$m[1]}.mp4"]]);
        }

        // Downloads of finished files: a new body for every request.
        return Http::response(sampleMp4(), 200);
    });
}

beforeEach(function () {
    Storage::fake('public');
    Queue::fake();
    $this->user = User::factory()->create(['balance_micros' => 50_000_000]);
    $owner = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    ProviderCredentials::set('ANTHROPIC_API_KEY', 'sk-ant-full-0001', $owner);
    ProviderCredentials::set('FAL_KEY', 'fal-full-0001', $owner);

    $anthropic = AiProvider::create(['key' => 'anthropic', 'name' => 'Anthropic', 'status' => 'active', 'priority' => 50]);
    $sonnet = AiModel::create(['ai_provider_id' => $anthropic->id, 'key' => 'claude-sonnet-5-5', 'name' => 'Sonnet', 'enabled' => true, 'priority' => 50]);
    ProviderCapability::create(['ai_model_id' => $sonnet->id, 'capability' => 'text']);
    ModelPrice::create(['provider_key' => 'anthropic', 'model_key' => 'claude-sonnet-5-5', 'unit' => 'input_mtok', 'cost_micros' => 2_000_000, 'source' => 'anthropic_docs']);
    ModelPrice::create(['provider_key' => 'anthropic', 'model_key' => 'claude-sonnet-5-5', 'unit' => 'output_mtok', 'cost_micros' => 10_000_000, 'source' => 'anthropic_docs']);

    $fal = AiProvider::create(['key' => 'fal', 'name' => 'fal.ai', 'status' => 'active', 'priority' => 100]);
    foreach ([['fal-ai/gpt-image-1.5', 'image'], ['bytedance/seedance-2.0/image-to-video', 'video']] as [$key, $cap]) {
        $model = AiModel::create(['ai_provider_id' => $fal->id, 'key' => $key, 'name' => $key, 'enabled' => true, 'priority' => 100]);
        ProviderCapability::create(['ai_model_id' => $model->id, 'capability' => $cap]);
    }
    ModelPrice::create(['provider_key' => 'fal', 'model_key' => 'fal-ai/gpt-image-1.5', 'unit' => 'image', 'cost_micros' => 34_000, 'source' => 'fal_api']);
    ModelPrice::create(['provider_key' => 'fal', 'model_key' => 'bytedance/seedance-2.0/image-to-video', 'unit' => 'second', 'cost_micros' => 302_400, 'source' => 'fal_api']);
});

it('makes a full video from a prompt: script, keyframes, motion, and one joined file of the chosen size', function () {
    if (! hasFfmpeg()) {
        $this->markTestSkipped('ffmpeg is not installed.');
    }
    fullVideoHttp();

    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/productions', [
            'prompt' => 'A rainy evening where a woman finds a brass compass that points home',
            'format' => 'vertical_9_16',
            'quality' => '720p',
            'styles' => ['environment' => 'village', 'look' => 'cinematic'],
        ])
        ->assertCreated()
        ->assertJsonPath('status', 'queued')
        ->assertJsonPath('width', 1080)
        ->assertJsonPath('height', 1920);

    $production = Production::firstOrFail();
    $runner = app(ProductionRunner::class);

    $runner->advance($production->refresh()); // script, then keyframes start
    expect($production->refresh()->status)->toBe('keyframing')
        ->and($production->scenes()->count())->toBe(2);

    $runner->advance($production->refresh()); // keyframes finish, motion starts
    expect($production->refresh()->status)->toBe('animating');

    $runner->advance($production->refresh()); // motion finishes, the clips are joined
    $production->refresh();

    expect($production->status)->toBe('succeeded', (string) $production->error)
        ->and($production->output_path)->toBe("productions/{$production->id}/final.mp4");
    Storage::disk('public')->assertExists($production->output_path);

    // The joined file has the chosen format size.
    $info = Ffmpeg::fromConfig()->probe(Storage::disk('public')->path($production->output_path));
    expect($info['width'])->toBe(1080)->and($info['height'])->toBe(1920);

    // The charge is what the steps used, not the reservation: the rest came back.
    $generation = Generation::find($production->generation_id);
    $scenes = $production->scenes()->get();
    $used = $production->script_price + $scenes->sum('keyframe_price') + $scenes->sum('motion_price');
    expect($generation->price_micros)->toBe($used)
        ->and($this->user->refresh()->balance_micros)->toBe(50_000_000 - $used);
});

it('charges only what was sent when a keyframe fails, and returns the rest', function () {
    fullVideoHttp('FAILED');

    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/productions', ['prompt' => 'A rainy evening where a woman finds a brass compass', 'format' => 'landscape_16_9', 'quality' => '720p'])
        ->assertCreated();

    $production = Production::firstOrFail();
    $runner = app(ProductionRunner::class);
    $runner->advance($production->refresh());
    $runner->advance($production->refresh());

    $production->refresh();
    $generation = Generation::find($production->generation_id);
    // The script was paid for, and the second keyframe was already sent to fal and is still in flight. The failed
    // keyframe is not billed by fal, so it is not charged.
    $inFlight = (int) $production->scenes()->where('position', 2)->value('keyframe_price');

    expect($production->status)->toBe('failed')
        ->and($production->error)->toContain('keyframe')
        ->and($generation->price_micros)->toBe($production->script_price + $inFlight)
        ->and($inFlight)->toBeGreaterThan(0)
        ->and($this->user->refresh()->balance_micros)->toBe(50_000_000 - $generation->price_micros);
});

it('refuses a YouTube link as a full-video prompt, and an unknown format', function () {
    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/productions', ['prompt' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'format' => 'landscape_16_9', 'quality' => '720p'])
        ->assertUnprocessable();

    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/productions', ['prompt' => 'A rainy evening where a woman finds a compass', 'format' => 'circle_9', 'quality' => '720p'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('format');
});

it('shows a production to its owner only', function () {
    fullVideoHttp();
    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/productions', ['prompt' => 'A rainy evening where a woman finds a compass', 'format' => 'square_1_1', 'quality' => '1080p'])
        ->assertCreated();

    $production = Production::firstOrFail();
    $stranger = User::factory()->create();

    $this->actingAs($this->user, 'sanctum')->getJson("/api/v1/productions/{$production->id}")->assertOk()->assertJsonPath('quality', '1080p');
    $this->actingAs($stranger, 'sanctum')->getJson("/api/v1/productions/{$production->id}")->assertNotFound();
});

it('reserves the estimate before any step runs, and refuses a full video the balance cannot cover', function () {
    $poor = User::factory()->create(['balance_micros' => 1_000]);

    $this->actingAs($poor, 'sanctum')
        ->postJson('/api/v1/productions', ['prompt' => 'A rainy evening where a woman finds a compass', 'format' => 'landscape_16_9', 'quality' => '720p'])
        ->assertStatus(402);

    expect(Production::count())->toBe(0);
});
