<?php

namespace App\Films;

use App\Ai\Adapters\FalAdapter;
use App\Ai\Data\GenerationRequest;
use App\Ai\Fal\FalEndpoints;
use App\Ai\GenerationService;
use App\Ai\MediaStore;
use App\Billing\PriceQuoter;
use App\Billing\Wallet;
use App\Clips\Ffmpeg;
use App\Enums\Capability;
use App\Models\Film;
use App\Models\FilmAsset;
use App\Models\FilmTake;
use App\Models\Generation;
use App\Models\Project;
use App\Styles\StylePresets;
use DomainException;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Moves one film through its stages. Each call does what the current stage can do now and returns. Nothing waits:
 * the video model's jobs are checked on the next call.
 *
 *   queued -> planning -> board -> rendering -> assembling -> succeeded
 *
 * Planning writes the story bible and then one chapter at a time, each chapter reading the memory of the one before.
 * The board makes the cast and location references. Rendering sends takes in a window and keeps each one's references.
 * Assembling joins the takes into chapters, and the chapters into the film.
 */
class FilmRunner
{
    public function __construct(
        protected GenerationService $generations,
        protected PriceQuoter $quotes,
        protected Wallet $wallet,
        protected FilmPlanner $planner,
        protected FilmService $service,
        protected MediaStore $media,
    ) {}

    public function advance(Film $film): void
    {
        $film->refresh();

        if ($film->isTerminal()) {
            return;
        }

        if ($this->timedOut($film)) {
            $this->service->fail($film, 'The film took too long and was stopped. Work already sent is charged; the rest is returned.');

            return;
        }

        try {
            match ($film->status) {
                'queued' => $this->start($film),
                'planning' => $this->plan($film),
                'board' => $this->board($film),
                'rendering' => $this->render($film),
                'assembling' => $this->assemble($film),
                default => null,
            };
        } catch (Throwable $e) {
            $this->service->fail($film->refresh(), $e->getMessage());
        }
    }

    private function start(Film $film): void
    {
        $film->forceFill(['status' => 'planning', 'plan' => ['bible' => null, 'chapters' => []]])->save();
    }

    private function plan(Film $film): void
    {
        $plan = $film->plan ?? ['bible' => null, 'chapters' => []];

        if ($plan['bible'] === null) {
            $result = $this->planner->bible($film);

            $plan['bible'] = $result['bible'];
            $film->forceFill(['plan' => $plan, 'title' => $film->title ?? $result['bible']['title']])->save();

            return;
        }

        $chapters = FilmSchedule::chapters($film->length_seconds);
        $done = count($plan['chapters']);

        if ($done < count($chapters)) {
            $this->planChapter($film, $plan, $chapters, $chapters[$done]);

            return;
        }

        $film->forceFill(['status' => 'board'])->save();
    }

    /**
     * @param  array<string, mixed>  $plan
     * @param  list<array{index: int, seconds: int, takes: list<int>}>  $chapters
     * @param  array{index: int, seconds: int, takes: list<int>}  $chapter
     */
    private function planChapter(Film $film, array $plan, array $chapters, array $chapter): void
    {
        $flat = collect(FilmSchedule::flatTakes($film->length_seconds));
        $mine = $flat->where('chapter', $chapter['index'])->values();
        $total = $flat->count();

        $earlier = collect($plan['chapters'])->map(fn (array $c) => ['index' => $c['index'], 'summary' => $c['summary'], 'memory' => $c['memory']])->all();

        $result = $this->planner->chapter(
            $film,
            $plan['bible'],
            $chapter['index'],
            count($chapters),
            $mine->map(fn (array $t) => ['number' => $t['position'], 'seconds' => $t['seconds']])->all(),
            $earlier,
        );

        $styles = StylePresets::directive((array) $film->styles);
        $cast = $plan['bible']['cast'];
        $locations = $plan['bible']['locations'];

        foreach ($mine->values() as $i => $slot) {
            $take = $result['takes'][$i];
            $composed = TakePrompt::compose(
                $take,
                $slot['position'],
                $total,
                $slot['seconds'],
                $plan['bible']['visual_style'],
                $cast,
                $locations,
                $styles,
            );

            $price = $this->quotes->quote('fal', FalEndpoints::VIDEO_SEEDANCE_REF, Capability::Video, $composed['prompt'], ['duration' => $slot['seconds'], 'resolution' => $film->quality, 'size' => $film->format], ['image_urls' => []]);

            $film->takes()->create([
                'chapter' => $chapter['index'],
                'position' => $slot['position'],
                'seconds' => $slot['seconds'],
                'prompt' => $composed['prompt'],
                'refs' => $composed['refs'],
                'status' => 'pending',
                'price' => $price->priceMicros,
                'cost' => $price->costMicros,
            ]);
        }

        $plan['chapters'][] = [
            'index' => $chapter['index'],
            'seconds' => $chapter['seconds'],
            'takes' => $mine->count(),
            'summary' => $result['summary'],
            'memory' => $result['memory'],
            'output_path' => null,
        ];

        $film->forceFill(['plan' => $plan])->save();
    }

    /**
     * The board: one reference per cast member and per location. Every reference is generated before any take starts,
     * so each take can be shot from them. Before the first one is sent, the whole film is checked against its reservation.
     */
    private function board(Film $film): void
    {
        if (! $film->assets()->exists()) {
            $this->createBoard($film);
        }

        $pending = $film->assets()->where('status', 'pending')->get();

        foreach ($pending as $asset) {
            $this->sendAsset($film, $asset);
        }

        foreach ($film->assets()->where('status', 'rendering')->get() as $asset) {
            $this->collectAsset($film, $asset);
        }

        if ($film->assets()->where('status', '!=', 'ready')->exists()) {
            return;
        }

        $film->forceFill(['status' => 'rendering'])->save();
    }

    private function createBoard(Film $film): void
    {
        $bible = $film->plan['bible'];
        $format = $film->format;
        $directive = StylePresets::directive((array) $film->styles);

        foreach ($bible['cast'] as $key => $member) {
            $prompt = "Character reference sheet of {$member['name']}. {$member['look']} {$bible['visual_style']} Full body, front view, plain light grey background, even studio light. No text, no logos. {$directive}";
            $this->createAsset($film, FilmAsset::KIND_CAST, $key, $member['name'], $prompt, $format);
        }

        foreach ($bible['locations'] as $key => $place) {
            $prompt = "Location reference of {$place['name']}. {$place['look']} {$bible['visual_style']} Wide view, no people, even light. No text, no logos. {$directive}";
            $this->createAsset($film, FilmAsset::KIND_LOCATION, $key, $place['name'], $prompt, $format);
        }

        $this->checkBudget($film);
    }

    /** Creates a board row with its price. The total is checked against the reservation by checkBudget(). */
    private function createAsset(Film $film, string $kind, string $key, string $name, string $prompt, string $format): void
    {
        $quote = $this->quotes->quote('fal', FalEndpoints::IMAGE_TEXT, Capability::Image, $prompt, ['size' => $format], []);

        $film->assets()->create([
            'kind' => $kind,
            'key' => $key,
            'name' => $name,
            'prompt' => $prompt,
            'status' => 'pending',
            'price' => $quote->priceMicros,
            'cost' => $quote->costMicros,
        ]);
    }

    /**
     * Everything the film will send, priced, must fit inside what was reserved. If it does not, nothing is sent: the film
     * stops now, and only the planning already paid for is charged.
     */
    private function checkBudget(Film $film): void
    {
        $planned = (int) $film->planning_price
            + (int) $film->assets()->sum('price')
            + (int) $film->takes()->sum('price');

        $reserved = $this->service->reservedMicros($film);

        if ($planned > $reserved) {
            throw new DomainException('This film would cost more than was reserved for it. Ask for a shorter film or 720p.');
        }
    }

    private function sendAsset(Film $film, FilmAsset $asset): void
    {
        $result = $this->generations->generate(new GenerationRequest(
            capability: Capability::Image,
            modelKey: '',
            prompt: $asset->prompt,
            options: ['size' => $film->format],
            requestedModelKey: FalEndpoints::IMAGE_TEXT,
        ));

        if (! $result->ok || ! $result->isQueued()) {
            throw new DomainException('A board reference could not be started: '.($result->error ?? 'unknown error'));
        }

        $asset->forceFill(['status' => 'rendering', 'job' => $result->job])->save();
    }

    private function collectAsset(Film $film, FilmAsset $asset): void
    {
        $result = app(FalAdapter::class)->poll((array) $asset->job);

        if ($result->isQueued()) {
            return;
        }

        if (! $result->ok) {
            throw new DomainException("The {$asset->name} reference failed: ".($result->error ?? 'unknown error'));
        }

        $url = (string) $result->output['url'];
        $path = "films/{$film->id}/board/{$asset->kind}-{$asset->key}.png";
        Storage::disk('public')->makeDirectory("films/{$film->id}/board");

        if (! $this->media->store($url, $path)) {
            throw new DomainException("The {$asset->name} reference could not be saved.");
        }

        $asset->forceFill(['status' => 'ready', 'image_url' => $url, 'image_path' => $path])->save();
    }

    /**
     * Send takes in a window and collect the finished ones. A take that fails is sent again until its attempts run out,
     * and then the film stops.
     */
    private function render(Film $film): void
    {
        $maxAttempts = (int) config('virgoai.films.max_take_attempts', 2);

        foreach ($film->takes()->where('status', 'rendering')->get() as $take) {
            $this->collectTake($film, $take, $maxAttempts);
        }

        $room = (int) config('virgoai.films.max_in_flight', 16) - $film->takes()->where('status', 'rendering')->count();

        if ($room > 0) {
            foreach ($film->takes()->where('status', 'pending')->limit($room)->get() as $take) {
                $this->sendTake($film, $take);
            }
        }

        if ($film->takes()->where('status', '!=', 'done')->exists()) {
            return;
        }

        $film->forceFill(['status' => 'assembling'])->save();
    }

    private function sendTake(Film $film, FilmTake $take): void
    {
        // The price is read again when the take is sent. If fal's price has moved, the take is charged at the new price,
        // and the whole film is checked against its reservation before anything is sent.
        $current = $this->quotes->quote('fal', FalEndpoints::VIDEO_SEEDANCE_REF, Capability::Video, $take->prompt, ['duration' => $take->seconds, 'resolution' => $film->quality, 'size' => $film->format], ['image_urls' => []]);

        if ($current->priceMicros !== (int) $take->price || $current->costMicros !== (int) $take->cost) {
            $take->forceFill(['price' => $current->priceMicros, 'cost' => $current->costMicros])->save();
            $this->checkBudget($film);
        }

        $urls = $this->referenceUrls($film, $take->refs);

        $result = $this->generations->generate(new GenerationRequest(
            capability: Capability::Video,
            modelKey: '',
            prompt: $take->prompt,
            inputs: ['image_urls' => $urls],
            options: ['duration' => $take->seconds, 'resolution' => $film->quality, 'size' => $film->format],
            requestedModelKey: FalEndpoints::VIDEO_SEEDANCE_REF,
        ));

        if (! $result->ok || ! $result->isQueued()) {
            throw new DomainException("Shot {$take->position} could not be started: ".($result->error ?? 'unknown error'));
        }

        $take->forceFill(['status' => 'rendering', 'job' => $result->job, 'attempts' => $take->attempts + 1, 'error' => null])->save();
    }

    private function collectTake(Film $film, FilmTake $take, int $maxAttempts): void
    {
        $result = app(FalAdapter::class)->poll((array) $take->job);

        if ($result->isQueued()) {
            return;
        }

        if (! $result->ok) {
            $this->retryOrStop($take, $maxAttempts, $result->error ?? 'unknown error');

            return;
        }

        $url = (string) $result->output['url'];
        $path = "films/{$film->id}/clips/{$take->position}.mp4";
        Storage::disk('public')->makeDirectory("films/{$film->id}/clips");

        if (! $this->media->store($url, $path)) {
            $this->retryOrStop($take, $maxAttempts, 'the clip could not be saved');

            return;
        }

        $take->forceFill(['status' => 'done', 'clip_url' => $url, 'clip_path' => $path, 'error' => null])->save();
    }

    private function retryOrStop(FilmTake $take, int $maxAttempts, string $reason): void
    {
        if ($take->attempts >= $maxAttempts) {
            // A failed job is not billed by fal, so this take stops counting as sent before the film is settled.
            $take->forceFill(['status' => 'failed', 'error' => $reason])->save();

            throw new DomainException("Shot {$take->position} failed after {$take->attempts} tries: {$reason}");
        }

        $take->forceFill(['status' => 'pending', 'error' => $reason])->save();
    }

    /**
     * The board images a take is shot from, in the order its prompt names them. Stops if one is missing.
     *
     * @param  list<string>  $keys
     * @return list<string>
     */
    private function referenceUrls(Film $film, array $keys): array
    {
        $assets = $film->assets()->whereIn('key', $keys)->get()->keyBy('key');

        return array_map(function (string $key) use ($assets) {
            $asset = $assets->get($key);

            if ($asset === null || $asset->image_url === null) {
                throw new DomainException("The board reference [{$key}] is not ready.");
            }

            return $asset->image_url;
        }, $keys);
    }

    /**
     * Normalise every take to the film's format in small batches, then join each chapter, then join the chapters.
     * The film is settled to what was sent, which is the cost of every take.
     */
    private function assemble(Film $film): void
    {
        $format = Project::FORMATS[$film->format] ?? Project::FORMATS[Project::DEFAULT_FORMAT];
        $ffmpeg = Ffmpeg::fromConfig();
        $batch = (int) config('virgoai.films.normalize_batch', 6);

        $todo = $film->takes()->whereNull('normalized_path')->limit($batch)->get();

        foreach ($todo as $take) {
            $source = Storage::disk('public')->path($take->clip_path);
            $path = "films/{$film->id}/norm/{$take->position}.mp4";
            Storage::disk('public')->makeDirectory("films/{$film->id}/norm");

            $ffmpeg->normalize($source, Storage::disk('public')->path($path), $format['width'], $format['height']);
            $take->forceFill(['normalized_path' => $path])->save();
        }

        if ($film->takes()->whereNull('normalized_path')->exists()) {
            return;
        }

        $plan = $film->plan;
        $chapterFiles = [];

        foreach ($plan['chapters'] as $i => $chapter) {
            $parts = $film->takes()->where('chapter', $chapter['index'])->get()
                ->map(fn (FilmTake $take) => Storage::disk('public')->path($take->normalized_path))
                ->values()
                ->all();

            $path = "films/{$film->id}/chapter-{$chapter['index']}.mp4";
            $ffmpeg->concat($parts, Storage::disk('public')->path($path));

            $plan['chapters'][$i]['output_path'] = $path;
            $chapterFiles[] = Storage::disk('public')->path($path);
        }

        $final = "films/{$film->id}/film.mp4";
        $ffmpeg->concat($chapterFiles, Storage::disk('public')->path($final));

        $generation = Generation::query()->find($film->generation_id);
        if ($generation !== null) {
            $this->wallet->settle($generation, $this->service->submittedQuote($film));
            $generation->forceFill(['status' => Generation::STATUS_SUCCEEDED, 'output_path' => $final, 'output_text' => null])->save();
        }

        $film->forceFill(['status' => 'succeeded', 'output_path' => $final, 'plan' => $plan, 'error' => null])->save();
    }

    private function timedOut(Film $film): bool
    {
        $max = (int) config('virgoai.films.max_wait_seconds', 14400);

        return $film->created_at->diffInSeconds(now()) >= $max;
    }
}
