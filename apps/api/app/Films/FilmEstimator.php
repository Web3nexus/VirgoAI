<?php

namespace App\Films;

use App\Ai\Fal\FalEndpoints;
use App\Billing\PriceQuoter;
use App\Billing\Quote;
use App\Enums\Capability;
use App\Productions\ProductionService;

/**
 * Prices a whole film before it starts, in three parts: the director (the story bible and one call per chapter), the
 * board (the cast and location references), and the takes (each one at its exact length). Each part is priced at the
 * sell price of its provider cost, so the director credit is the sum of the three, and the reservation is that sum.
 */
final class FilmEstimator
{
    /**
     * A director call is priced as if its prompt were this long. Later chapters carry the memory of the earlier ones,
     * so the allowance is large enough for a feature-length film.
     */
    public const DIRECTOR_PROMPT_CHARS = 30000;

    public function __construct(protected PriceQuoter $quotes) {}

    /**
     * @return array{price_micros: int, cost_micros: int, takes: int, chapters: int, references: int, parts: array{director: array{price_micros: int, cost_micros: int}, board: array{price_micros: int, cost_micros: int}, takes: array{price_micros: int, cost_micros: int}}}
     */
    public function estimate(int $lengthSeconds, string $format, string $quality): array
    {
        $chapters = FilmSchedule::chapters($lengthSeconds);
        $takes = FilmSchedule::flatTakes($lengthSeconds);

        $director = ['price_micros' => 0, 'cost_micros' => 0];
        $board = ['price_micros' => 0, 'cost_micros' => 0];
        $takesPart = ['price_micros' => 0, 'cost_micros' => 0];

        $add = function (array &$part, Quote $quote, int $times = 1): void {
            $part['price_micros'] += $quote->priceMicros * $times;
            $part['cost_micros'] += $quote->costMicros * $times;
        };

        // The story bible, then one director call per chapter.
        $directorPrompt = str_repeat('x', self::DIRECTOR_PROMPT_CHARS);
        $add($director, $this->quotes->quote('anthropic', ProductionService::TEXT_MODEL, Capability::Text, $directorPrompt, ['system' => FilmPlanner::BIBLE_SYSTEM], []));
        $add($director, $this->quotes->quote('anthropic', ProductionService::TEXT_MODEL, Capability::Text, $directorPrompt, ['system' => FilmPlanner::CHAPTER_SYSTEM], []), count($chapters));

        // The board: the most references the planner may ask for.
        $references = (int) config('virgoai.films.max_cast', 6) + (int) config('virgoai.films.max_locations', 5);
        $add($board, $this->quotes->quote('fal', FalEndpoints::IMAGE_TEXT, Capability::Image, str_repeat('x', 600), ['size' => $format], []), $references);

        // Each take, at its own length.
        $byLength = [];
        foreach ($takes as $take) {
            $byLength[$take['seconds']] = ($byLength[$take['seconds']] ?? 0) + 1;
        }
        foreach ($byLength as $seconds => $count) {
            $add($takesPart, $this->quotes->quote('fal', FalEndpoints::VIDEO_SEEDANCE_REF, Capability::Video, '', ['duration' => $seconds, 'resolution' => $quality, 'size' => $format], ['image_urls' => ['https://example.invalid/r.png']]), $count);
        }

        return [
            'price_micros' => $director['price_micros'] + $board['price_micros'] + $takesPart['price_micros'],
            'cost_micros' => $director['cost_micros'] + $board['cost_micros'] + $takesPart['cost_micros'],
            'takes' => count($takes),
            'chapters' => count($chapters),
            'references' => $references,
            'parts' => ['director' => $director, 'board' => $board, 'takes' => $takesPart],
        ];
    }
}
