<?php

namespace App\Films;

use App\Billing\Quote;
use App\Billing\Wallet;
use App\Enums\Capability;
use App\Jobs\FilmTick;
use App\Models\Film;
use App\Models\Generation;
use App\Models\Project;
use App\Models\User;
use DomainException;

/**
 * Starts, stops and prices a film. The whole estimate is reserved before anything is sent, and the film settles to
 * what it actually sent when it ends, so the reserve is never spent on work that was not done.
 */
class FilmService
{
    public function __construct(
        protected FilmEstimator $estimator,
        protected Wallet $wallet,
    ) {}

    /**
     * @param  array<string, mixed>|null  $styles
     */
    public function start(User $user, string $brief, int $lengthSeconds, string $format, string $quality, ?array $styles = null, ?string $title = null): Film
    {
        $brief = trim($brief);

        if (mb_strlen($brief) < 20) {
            throw new DomainException('Describe the film in at least 20 characters.');
        }
        if (! array_key_exists($format, Project::FORMATS)) {
            throw new DomainException('Choose a video format.');
        }
        if (! in_array($quality, ['720p', '1080p'], true)) {
            throw new DomainException('Choose 720p or 1080p.');
        }

        $styles = array_filter((array) $styles);
        $estimate = $this->estimator->estimate($lengthSeconds, $format, $quality);

        if ($estimate['price_micros'] > (int) config('virgoai.films.max_price_micros')) {
            throw new DomainException('This film costs more than the limit for one film. Make it shorter or use 720p.');
        }

        $quote = new Quote($estimate['cost_micros'], $estimate['price_micros'], 'film', $estimate['takes'], true);

        $generation = $this->wallet->reserveRun($user, $quote, [
            'user_id' => $user->id,
            'tool' => 'film',
            'capability' => Capability::Video->value,
            'provider_key' => 'fal',
            'model_key' => 'bytedance/seedance-2.0/reference-to-video',
            'prompt' => mb_substr($brief, 0, 4000),
            'options' => ['format' => $format, 'quality' => $quality, 'styles' => $styles, 'length_seconds' => $lengthSeconds, 'takes' => $estimate['takes']],
            'output_type' => 'video',
        ]);

        $film = Film::create([
            'user_id' => $user->id,
            'generation_id' => $generation->id,
            'title' => $title !== null ? mb_substr(trim($title), 0, 200) : null,
            'brief' => $brief,
            'length_seconds' => $lengthSeconds,
            'format' => $format,
            'quality' => $quality,
            'styles' => $styles === [] ? null : $styles,
            'status' => 'queued',
        ]);

        FilmTick::dispatch($film->id);

        return $film;
    }

    /**
     * Stop the film. Work already sent to the video model is charged, because fal bills it. Work never sent is returned.
     */
    public function fail(Film $film, string $reason): void
    {
        $generation = Generation::query()->find($film->generation_id);

        if ($generation !== null) {
            $this->wallet->settle($generation, $this->submittedQuote($film));
            $generation->forceFill(['status' => Generation::STATUS_FAILED, 'error' => $reason])->save();
        }

        $film->forceFill(['status' => 'failed', 'error' => $reason])->save();
    }

    /** The user stops the film. Nothing more is sent; what was already sent is charged. */
    public function cancel(Film $film): void
    {
        if ($film->isTerminal()) {
            throw new DomainException('This film has already finished.');
        }

        $this->fail($film, 'Stopped by you. Work already sent to the video model is charged.');
    }

    /**
     * What the film has sent to providers so far. A board reference or a take counts once it was sent (rendering) and
     * stays counted once it is done. A take that failed is not counted, because fal does not bill a failed job.
     */
    public function submittedQuote(Film $film): Quote
    {
        $assets = $film->assets()->whereIn('status', ['rendering', 'ready'])->get();
        $takes = $film->takes()->whereIn('status', ['rendering', 'done'])->get();

        $price = (int) $film->planning_price + (int) $assets->sum('price') + (int) $takes->sum('price');
        $cost = (int) $film->planning_cost + (int) $assets->sum('cost') + (int) $takes->sum('cost');

        return new Quote($cost, $price, 'film', $takes->count(), false);
    }

    /** The reservation the film was started with, in micro-dollars. */
    public function reservedMicros(Film $film): int
    {
        return (int) (Generation::query()->find($film->generation_id)?->price_micros ?? 0);
    }
}
