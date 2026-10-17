<?php

namespace App\Clips;

/**
 * Plans the parts of an automatic split. Cuts land near the requested length, snapped to the end of a sentence in
 * the transcript when one is close, so a part does not stop mid-word.
 */
class Split
{
    public const MAX_PARTS = 600;

    /**
     * @param  array<int, array{start: float, end: float, text: string}>  $transcript
     * @return array<int, array{start: float, end: float}>
     */
    public static function plan(float $duration, int $partSeconds, array $transcript = []): array
    {
        $count = (int) ceil($duration / $partSeconds);
        $boundaries = [0.0];

        for ($i = 1; $i < $count; $i++) {
            $target = $i * $partSeconds;
            $boundaries[] = self::snap($target, $partSeconds, $transcript, $duration);
        }
        $boundaries[] = $duration;

        $parts = [];
        for ($i = 0; $i < count($boundaries) - 1; $i++) {
            $start = $boundaries[$i];
            $end = $boundaries[$i + 1];
            if ($end - $start >= 1.0) {
                $parts[] = ['start' => round($start, 3), 'end' => round($end, 3)];
            }
        }

        return $parts;
    }

    /**
     * The nearest sentence end within 20% of the part length, otherwise the target itself.
     *
     * @param  array<int, array{start: float, end: float, text: string}>  $transcript
     */
    private static function snap(float $target, int $partSeconds, array $transcript, float $duration): float
    {
        $window = max(3.0, $partSeconds * 0.2);
        $best = null;

        foreach ($transcript as $segment) {
            $end = (float) $segment['end'];
            if ($end >= $duration) {
                continue;
            }
            if (abs($end - $target) <= $window && ($best === null || abs($end - $target) < abs($best - $target))) {
                $best = $end;
            }
        }

        return $best ?? min($target, $duration);
    }
}
