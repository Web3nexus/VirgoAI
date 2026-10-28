<?php

use App\Ai\Fal\FalEndpoints;
use App\Billing\InsufficientCredit;
use App\Films\FilmRunner;
use App\Films\FilmSchedule;
use App\Films\FilmService;
use App\Films\TakePrompt;
use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\Film;
use App\Models\Generation;
use App\Models\ModelPrice;
use App\Models\ProviderCapability;
use App\Models\UsageEvent;
use App\Models\User;
use App\Settings\ProviderCredentials;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/** The film's director reply for the story bible, in the shape the planner checks. */
function filmBibleReply(): array
{
    return [
        'title' => 'The Compass',
        'logline' => 'A woman finds a compass that points home.',
        'visual_style' => 'Warm, cinematic and quiet, with soft rain light.',
        'tone' => 'Gentle mystery',
        'cast' => [['key' => 'mia', 'name' => 'Mia', 'look' => 'A woman in her thirties with dark hair in a plait, a green wool coat and small silver earrings.']],
        'locations' => [['key' => 'kitchen', 'name' => 'The kitchen', 'look' => 'A small kitchen with a wooden table, a window onto the rain and a blue kettle on the stove.']],
        'story' => str_repeat('Mia finds a brass compass on her kitchen table and follows it into the rain to a house she has never seen. ', 3),
    ];
}

/** A chapter reply for the takes it was asked for: one entry per take, with the same number. */
function filmChapterReply(array $takes): array
{
    return [
        'summary' => 'Mia follows the compass out into the rain.',
        'memory' => 'Mia now carries the compass and is walking toward the house by the river. She has not told anyone where she is going.',
        'takes' => array_map(fn (array $take) => [
            'n' => $take['number'],
            'cast' => ['mia'],
            'location' => 'kitchen',
            'action' => "Mia turns the compass in her palm, shot {$take['number']}.",
            'dialogue' => [['speaker' => 'mia', 'line' => 'It points home.']],
        ], $takes),
    ];
}

function filmMp4(): string
{
    $path = sys_get_temp_dir().'/film-'.uniqid().'.mp4';
    exec(sprintf('ffmpeg -y -f lavfi -i testsrc=duration=1:size=320x240:rate=10 -f lavfi -i sine=duration=1 -shortest -c:v libx264 -pix_fmt yuv420p -c:a aac %s 2>/dev/null', escapeshellarg($path)));
    $bytes = (string) file_get_contents($path);
    @unlink($path);

    return $bytes;
}

/**
 * The provider calls a film makes. Each fal job completes on its first status check. A take listed in $failingTakes
 * fails every time, so the retry and stop rules can be tested.
 *
 * @param  list<int>  $failingTakes
 */
function filmHttp(array $failingTakes = []): void
{
    $imageQueue = 'https://queue.fal.run/fal-ai/gpt-image-1.5';
    $videoQueue = 'https://queue.fal.run/bytedance/seedance-2.0/reference-to-video';
    $jobs = [];
    $submitted = [];

    Http::fake(function (Request $request) use ($imageQueue, $videoQueue, $failingTakes, &$jobs, &$submitted) {
        $url = $request->url();

        if (str_starts_with($url, 'https://api.anthropic.com/v1/messages')) {
            $system = (string) ($request->data()['system'] ?? '');
            $user = json_decode((string) ($request->data()['messages'][0]['content'] ?? '{}'), true) ?: [];

            $reply = str_contains($system, 'director and writer')
                ? filmBibleReply()
                : filmChapterReply($user['takes'] ?? []);

            return Http::response([
                'content' => [['type' => 'text', 'text' => json_encode($reply)]],
                'stop_reason' => 'end_turn',
                'usage' => ['input_tokens' => 900, 'output_tokens' => 600],
            ]);
        }

        if ($url === $imageQueue) {
            $id = 'img'.(count($jobs) + 1);
            $jobs[$id] = ['kind' => 'image'];

            return Http::response(['request_id' => $id, 'status_url' => "{$imageQueue}/requests/{$id}/status", 'response_url' => "{$imageQueue}/requests/{$id}"]);
        }

        if ($url === $videoQueue) {
            $n = count($submitted) + 1;
            $id = 'vid'.$n;
            $submitted[] = $request->data();
            $jobs[$id] = ['kind' => 'video', 'number' => $n];

            return Http::response(['request_id' => $id, 'status_url' => "{$videoQueue}/requests/{$id}/status", 'response_url' => "{$videoQueue}/requests/{$id}"]);
        }

        if (preg_match('~/requests/((?:img|vid)\d+)/status$~', $url, $m)) {
            $job = $jobs[$m[1]];
            $fails = $job['kind'] === 'video' && in_array($job['number'], $failingTakes, true);

            return Http::response(['status' => $fails ? 'FAILED' : 'COMPLETED']);
        }

        if (preg_match('~/requests/((?:img|vid)\d+)$~', $url, $m)) {
            $job = $jobs[$m[1]];

            if ($job['kind'] === 'image') {
                return Http::response(['images' => [['url' => "https://v3b.fal.media/files/{$m[1]}.png", 'content_type' => 'image/png']]]);
            }

            return Http::response(['video' => ['url' => "https://storage.googleapis.com/falserverless/{$m[1]}.mp4"]]);
        }

        // Downloads of finished files: a new body each time.
        return Http::response(filmMp4(), 200);
    });
}

beforeEach(function () {
    Storage::fake('public');
    Queue::fake();
    $this->user = User::factory()->create(['balance_micros' => 50_000_000]);
    $owner = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    ProviderCredentials::set('ANTHROPIC_API_KEY', 'sk-ant-film-0001', $owner);
    ProviderCredentials::set('FAL_KEY', 'fal-film-0001', $owner);

    $anthropic = AiProvider::create(['key' => 'anthropic', 'name' => 'Anthropic', 'status' => 'active', 'priority' => 50]);
    $sonnet = AiModel::create(['ai_provider_id' => $anthropic->id, 'key' => 'claude-sonnet-5-5', 'name' => 'Sonnet', 'enabled' => true, 'priority' => 50]);
    ProviderCapability::create(['ai_model_id' => $sonnet->id, 'capability' => 'text']);
    ModelPrice::create(['provider_key' => 'anthropic', 'model_key' => 'claude-sonnet-5-5', 'unit' => 'input_mtok', 'cost_micros' => 2_000_000, 'source' => 'anthropic_docs']);
    ModelPrice::create(['provider_key' => 'anthropic', 'model_key' => 'claude-sonnet-5-5', 'unit' => 'output_mtok', 'cost_micros' => 10_000_000, 'source' => 'anthropic_docs']);

    $fal = AiProvider::create(['key' => 'fal', 'name' => 'fal.ai', 'status' => 'active', 'priority' => 100]);
    foreach ([[FalEndpoints::IMAGE_TEXT, 'image'], [FalEndpoints::VIDEO_SEEDANCE_REF, 'video']] as [$key, $cap]) {
        $model = AiModel::create(['ai_provider_id' => $fal->id, 'key' => $key, 'name' => $key, 'enabled' => true, 'priority' => 100]);
        ProviderCapability::create(['ai_model_id' => $model->id, 'capability' => $cap]);
    }
    ModelPrice::create(['provider_key' => 'fal', 'model_key' => FalEndpoints::IMAGE_TEXT, 'unit' => 'image', 'cost_micros' => 34_000, 'source' => 'fal_api']);
    ModelPrice::create(['provider_key' => 'fal', 'model_key' => FalEndpoints::VIDEO_SEEDANCE_REF, 'unit' => 'second', 'cost_micros' => 303_400, 'source' => 'fal_api']);
});

// The timing of a film.

it('plans a film in chapters of up to ten minutes, each made of takes of up to fifteen seconds', function () {
    $hour = FilmSchedule::chapters(3600);

    expect($hour)->toHaveCount(6)
        ->and(array_sum(array_column($hour, 'seconds')))->toBe(3600)
        ->and(max(array_column($hour, 'seconds')))->toBe(600)
        ->and(array_sum(array_map('count', array_column($hour, 'takes'))))->toBe(240);

    // An uneven length is shared across the chapters, and no chapter is longer than ten minutes.
    $uneven = FilmSchedule::chapters(601);
    expect($uneven)->toHaveCount(2)
        ->and(array_column($uneven, 'seconds'))->toBe([301, 300]);

    // A 16-second chapter is two takes of 8 seconds, never a take shorter than four.
    expect(FilmSchedule::takes(16))->toBe([8, 8])
        ->and(FilmSchedule::takes(600))->toHaveCount(40)
        ->and(array_unique(FilmSchedule::takes(600)))->toBe([15]);
});

it('refuses a film shorter than 15 seconds or longer than the limit', function () {
    expect(fn () => FilmSchedule::chapters(14))->toThrow(DomainException::class)
        ->and(fn () => FilmSchedule::chapters(3601))->toThrow(DomainException::class);
});

it('writes each take prompt against its references, in the order the references are sent', function () {
    $cast = ['mia' => ['name' => 'Mia', 'look' => 'Dark hair, green coat.']];
    $locations = ['kitchen' => ['name' => 'Kitchen', 'look' => 'A wooden table by a rainy window.']];
    $take = ['cast' => ['mia'], 'location' => 'kitchen', 'action' => 'She lifts the compass.', 'dialogue' => [['speaker' => 'mia', 'line' => 'Home.']]];

    $composed = TakePrompt::compose($take, 1, 40, 15, 'Warm cinematic.', $cast, $locations, '');

    expect($composed['refs'])->toBe(['mia', 'kitchen'])
        ->and($composed['prompt'])->toContain('@Image1 is the character reference sheet of Mia.')
        ->and($composed['prompt'])->toContain('@Image2 is the location reference of Kitchen.')
        ->and($composed['prompt'])->toContain('Mia says: "Home."');

    expect(fn () => TakePrompt::compose(['cast' => ['ghost'], 'location' => 'kitchen', 'action' => 'x', 'dialogue' => []], 1, 1, 15, '', $cast, $locations, ''))
        ->toThrow(DomainException::class, 'unknown cast member');
});

// The reservation.

it('reserves the whole film up front and refuses it when the balance cannot cover it', function () {
    $start = fn (User $user) => app(FilmService::class)->start($user, 'A woman finds a compass that points home, in the rain.', 60, 'landscape_16_9', '720p');

    $film = $start($this->user);
    $generation = Generation::find($film->generation_id);

    expect($generation->price_micros)->toBeGreaterThan(0)
        ->and($this->user->refresh()->balance_micros)->toBe(50_000_000 - $generation->price_micros)
        ->and($film->length_seconds)->toBe(60);

    $broke = User::factory()->create(['balance_micros' => 1_000_000]);
    expect(fn () => $start($broke))->toThrow(InsufficientCredit::class);
    expect(Film::where('user_id', $broke->id)->count())->toBe(0);
});

it('refuses a film over the per-film price limit before reserving anything', function () {
    config(['virgoai.films.max_price_micros' => 1_000_000]);

    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/films', ['brief' => 'A woman finds a compass that points home, in the rain.', 'length_seconds' => 60, 'format' => 'landscape_16_9', 'quality' => '720p'])
        ->assertUnprocessable();

    expect($this->user->refresh()->balance_micros)->toBe(50_000_000)
        ->and(Film::count())->toBe(0);
});

// A whole film, end to end.

it('makes a film from a brief: a board of references, takes, and one joined file', function () {
    if (exec('command -v ffmpeg 2>/dev/null') === '') {
        $this->markTestSkipped('ffmpeg is not installed.');
    }
    filmHttp();

    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/films', [
            'brief' => 'A woman finds a brass compass that points home, and follows it into the rain.',
            'length_seconds' => 30,
            'format' => 'landscape_16_9',
            'quality' => '720p',
            'styles' => ['look' => 'cinematic'],
        ])
        ->assertCreated()
        ->assertJsonPath('status', 'queued');

    $film = Film::firstOrFail();
    $runner = app(FilmRunner::class);

    for ($i = 0; $i < 20 && ! $film->refresh()->isTerminal(); $i++) {
        $runner->advance($film);
    }

    expect($film->status)->toBe('succeeded', (string) $film->error)
        ->and($film->output_path)->toBe("films/{$film->id}/film.mp4")
        ->and($film->plan['bible']['cast'])->toHaveKey('mia')
        ->and($film->assets)->toHaveCount(2)
        ->and($film->assets->every(fn ($a) => $a->status === 'ready'))->toBeTrue()
        ->and($film->takes)->toHaveCount(2)
        ->and($film->takes->every(fn ($t) => $t->status === 'done'))->toBeTrue();

    Storage::disk('public')->assertExists($film->output_path);

    // Only the work that was sent is charged. The rest of the reservation comes back, to the cent.
    $generation = Generation::find($film->generation_id);
    expect($generation->status)->toBe(Generation::STATUS_SUCCEEDED)
        ->and($this->user->refresh()->balance_micros)->toBe(50_000_000 - $generation->price_micros);

    $sent = $film->planning_price + $film->assets->sum('price') + $film->takes->sum('price');
    expect($generation->price_micros)->toBe($sent);
});

it('sends each take with its references and the duration it was planned for', function () {
    if (exec('command -v ffmpeg 2>/dev/null') === '') {
        $this->markTestSkipped('ffmpeg is not installed.');
    }
    filmHttp();

    $film = app(FilmService::class)->start($this->user, 'A woman finds a brass compass that points home, in the rain.', 30, 'landscape_16_9', '720p');
    $runner = app(FilmRunner::class);

    for ($i = 0; $i < 20 && ! $film->refresh()->isTerminal(); $i++) {
        $runner->advance($film);
    }

    $sentTakes = collect(Http::recorded(fn (Request $r) => $r->url() === 'https://queue.fal.run/bytedance/seedance-2.0/reference-to-video'))
        ->map(fn (array $pair) => $pair[0]->data());

    expect($sentTakes)->toHaveCount(2)
        ->and($sentTakes->pluck('duration')->all())->toBe(['15', '15'])
        ->and($sentTakes->first()['image_urls'])->toHaveCount(2)
        ->and($sentTakes->first()['image_urls'][0])->toStartWith('https://v3b.fal.media/files/img');
});

it('stops a film when a take fails twice, and charges only the work fal bills', function () {
    if (exec('command -v ffmpeg 2>/dev/null') === '') {
        $this->markTestSkipped('ffmpeg is not installed.');
    }
    // The first take fails on both tries, so the film stops with its second take still in flight.
    filmHttp(failingTakes: [1, 3]);

    $film = app(FilmService::class)->start($this->user, 'A woman finds a brass compass that points home, in the rain.', 30, 'landscape_16_9', '720p');
    $runner = app(FilmRunner::class);

    for ($i = 0; $i < 20 && ! $film->refresh()->isTerminal(); $i++) {
        $runner->advance($film);
    }

    $generation = Generation::find($film->generation_id);
    $failed = $film->takes->firstWhere('status', 'failed');

    expect($film->status)->toBe('failed')
        ->and($film->error)->toContain('failed after 2 tries')
        ->and($failed)->not->toBeNull()
        ->and($generation->status)->toBe(Generation::STATUS_FAILED)
        // The failed take is not billed by fal, so it is not charged. The reservation for everything else comes back.
        ->and($generation->price_micros)->toBe($film->planning_price + $film->assets->sum('price') + $film->takes->whereIn('status', ['rendering', 'done'])->sum('price'))
        ->and($this->user->refresh()->balance_micros)->toBe(50_000_000 - $generation->price_micros);
});

it('lets the owner stop a film, and charges what was already sent', function () {
    filmHttp();

    $film = app(FilmService::class)->start($this->user, 'A woman finds a brass compass that points home, in the rain.', 30, 'landscape_16_9', '720p');
    app(FilmRunner::class)->advance($film); // planning

    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/films/{$film->id}/cancel")
        ->assertOk()
        ->assertJsonPath('status', 'failed');

    $generation = Generation::find($film->generation_id);
    expect($generation->status)->toBe(Generation::STATUS_FAILED)
        ->and($this->user->refresh()->balance_micros)->toBe(50_000_000 - $generation->price_micros);

    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/films/{$film->id}/cancel")
        ->assertConflict();
});

it('shows a film to its owner only', function () {
    $film = app(FilmService::class)->start($this->user, 'A woman finds a brass compass that points home, in the rain.', 30, 'landscape_16_9', '720p');
    $other = User::factory()->create();

    $this->actingAs($other, 'sanctum')->getJson("/api/v1/films/{$film->id}")->assertNotFound();
    $this->actingAs($this->user, 'sanctum')->getJson("/api/v1/films/{$film->id}")->assertOk()->assertJsonPath('length_seconds', 30);
});

it('removes a finished film\'s files after the retention period, and keeps its record', function () {
    $old = Film::create([
        'user_id' => $this->user->id,
        'brief' => 'A woman finds a brass compass that points home, in the rain.',
        'length_seconds' => 30,
        'format' => 'landscape_16_9',
        'quality' => '720p',
        'status' => 'succeeded',
    ]);
    Storage::disk('public')->put("films/{$old->id}/film.mp4", 'video');
    $old->forceFill(['output_path' => "films/{$old->id}/film.mp4"]);
    $old->forceFill(['created_at' => now()->subDays(31)])->saveQuietly();

    $new = Film::create([
        'user_id' => $this->user->id,
        'brief' => 'A second film that is recent enough to keep its files.',
        'length_seconds' => 30,
        'format' => 'landscape_16_9',
        'quality' => '720p',
        'status' => 'succeeded',
    ]);
    Storage::disk('public')->put("films/{$new->id}/film.mp4", 'video');
    $new->forceFill(['output_path' => "films/{$new->id}/film.mp4"])->saveQuietly();

    $this->artisan('media:prune')->assertSuccessful();

    Storage::disk('public')->assertMissing("films/{$old->id}/film.mp4");
    Storage::disk('public')->assertExists("films/{$new->id}/film.mp4");
    expect($old->refresh()->media_deleted_at)->not->toBeNull()
        ->and($old->output_path)->toBeNull()
        ->and(Film::find($old->id))->not->toBeNull()
        ->and($new->refresh()->media_deleted_at)->toBeNull();
});

it('charges the director for every call, including a chapter reply that is thrown away', function () {
    Http::fake(function (Request $request) {
        if (str_starts_with($request->url(), 'https://api.anthropic.com/v1/messages')) {
            $system = (string) ($request->data()['system'] ?? '');
            $reply = str_contains($system, 'director and writer')
                ? json_encode(filmBibleReply())
                : 'I am sorry, I cannot write this chapter as JSON.';

            return Http::response([
                'content' => [['type' => 'text', 'text' => $reply]],
                'stop_reason' => 'end_turn',
                'usage' => ['input_tokens' => 900, 'output_tokens' => 600],
            ]);
        }

        return Http::response('', 500);
    });

    $film = app(FilmService::class)->start($this->user, 'A woman finds a brass compass that points home, in the rain.', 30, 'landscape_16_9', '720p');
    $runner = app(FilmRunner::class);

    for ($i = 0; $i < 10 && ! $film->refresh()->isTerminal(); $i++) {
        $runner->advance($film);
    }

    // The bible and both attempts at the chapter were paid for, even though the chapter was never used.
    $generation = Generation::find($film->generation_id);
    expect($film->status)->toBe('failed')
        ->and(UsageEvent::where('production_type', 'film')->count())->toBe(3)
        ->and($film->planning_price)->toBeGreaterThan(0)
        ->and($generation->price_micros)->toBe($film->planning_price)
        ->and($this->user->refresh()->balance_micros)->toBe(50_000_000 - $film->planning_price);
});

it('stops a film before it sends a take when fal has raised the price after planning', function () {
    filmHttp();

    $film = app(FilmService::class)->start($this->user, 'A woman finds a brass compass that points home, in the rain.', 30, 'landscape_16_9', '720p');
    $reserved = Generation::find($film->generation_id)->price_micros;
    $runner = app(FilmRunner::class);

    // Plan and build the board, then stop just before the first take is sent.
    for ($i = 0; $i < 10 && $film->refresh()->status !== 'rendering'; $i++) {
        $runner->advance($film);
    }
    expect($film->status)->toBe('rendering');

    // fal raises the 720p price tenfold. The reservation was made at the old price.
    $rates = config('pricing.fal_rates');
    $rates['bytedance/seedance-2.0/reference-to-video']['tiers']['720p'] = 3.034;
    config(['pricing.fal_rates' => $rates]);

    $runner->advance($film);

    $generation = Generation::find($film->generation_id);
    expect($film->refresh()->status)->toBe('failed')
        ->and($film->error)->toContain('more than was reserved')
        ->and($film->takes->where('status', 'rendering')->count())->toBe(0)
        ->and($generation->price_micros)->toBeLessThanOrEqual($reserved)
        ->and($this->user->refresh()->balance_micros)->toBe(50_000_000 - $generation->price_micros);
});
