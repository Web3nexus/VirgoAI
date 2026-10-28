<?php

namespace App\Films;

use DomainException;

/**
 * The timing of a film, decided in code before any model is called. A length becomes chapters of up to ten minutes,
 * and each chapter becomes takes of up to fifteen seconds. Because the schedule is fixed, the price of the whole film
 * is known up front and the reservation covers it exactly.
 */
final class FilmSchedule
{
    /**
     * @return list<array{index: int, seconds: int, takes: list<int>}> Chapters from 1, each with its take lengths.
     */
    public static function chapters(int $lengthSeconds): array
    {
        self::assertLength($lengthSeconds);

        $max = (int) config('virgoai.films.chapter_seconds', 600);
        $count = intdiv($lengthSeconds + $max - 1, $max);
        $base = intdiv($lengthSeconds, $count);
        $extra = $lengthSeconds % $count;

        $chapters = [];

        for ($i = 0; $i < $count; $i++) {
            $seconds = $base + ($i < $extra ? 1 : 0);
            $chapters[] = ['index' => $i + 1, 'seconds' => $seconds, 'takes' => self::takes($seconds)];
        }

        return $chapters;
    }

    /**
     * The take lengths for one chapter: as few takes as possible, each no longer than the maximum, shared evenly.
     *
     * @return list<int>
     */
    public static function takes(int $seconds): array
    {
        $max = (int) config('virgoai.films.take_max_seconds', 15);
        $min = (int) config('virgoai.films.take_min_seconds', 4);

        $count = intdiv($seconds + $max - 1, $max);
        $base = intdiv($seconds, $count);
        $extra = $seconds % $count;

        if ($base < $min) {
            throw new DomainException('A chapter cannot be split into takes of at least '.$min.' seconds.');
        }

        $takes = [];

        for ($i = 0; $i < $count; $i++) {
            $takes[] = $base + ($i < $extra ? 1 : 0);
        }

        return $takes;
    }

    /** Every take of the film, in order, with its chapter and its place in the whole film. */
    public static function flatTakes(int $lengthSeconds): array
    {
        $flat = [];
        $position = 0;

        foreach (self::chapters($lengthSeconds) as $chapter) {
            foreach ($chapter['takes'] as $seconds) {
                $position++;
                $flat[] = ['chapter' => $chapter['index'], 'position' => $position, 'seconds' => $seconds];
            }
        }

        return $flat;
    }

    public static function assertLength(int $lengthSeconds): void
    {
        $min = (int) config('virgoai.films.min_length_seconds', 15);
        $max = (int) config('virgoai.films.max_length_seconds', 3600);

        if ($lengthSeconds < $min || $lengthSeconds > $max) {
            throw new DomainException("A film is between {$min} seconds and ".intdiv($max, 60).' minutes long.');
        }
    }
}
