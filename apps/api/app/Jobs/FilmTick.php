<?php

namespace App\Jobs;

use App\Films\FilmRunner;
use App\Models\Film;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

/**
 * Advance a film one step, then check again later until it finishes. A lock stops two steps running at once for the
 * same film, so a slow tick never overlaps the next one.
 */
class FilmTick implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(public readonly string $filmId) {}

    public function handle(FilmRunner $runner): void
    {
        $lock = Cache::lock("film:{$this->filmId}", 1800);

        if (! $lock->get()) {
            return;
        }

        try {
            $film = Film::query()->find($this->filmId);

            if ($film === null || $film->isTerminal()) {
                return;
            }

            $runner->advance($film);

            if (! $film->refresh()->isTerminal()) {
                self::dispatch($this->filmId)->delay(now()->addSeconds((int) config('virgoai.films.tick_seconds', 15)));
            }
        } finally {
            $lock->release();
        }
    }
}
