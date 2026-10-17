<?php

namespace App\Clips;

use InvalidArgumentException;

/**
 * Reads a subtitle file (SRT or WebVTT) into timed segments. This is how a YouTube video gets a transcript
 * without downloading it: the owner exports the captions and we read them here.
 */
class SubtitleParser
{
    /**
     * @return array<int, array{start: float, end: float, text: string}>
     */
    public static function parse(string $content): array
    {
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $segments = [];

        foreach (preg_split('/\n\s*\n/', trim($content)) as $block) {
            $lines = array_values(array_filter(array_map('trim', explode("\n", $block)), fn ($line) => $line !== ''));

            $timingIndex = collect($lines)->search(fn (string $line) => str_contains($line, '-->'));
            if ($timingIndex === false) {
                continue;
            }

            [$startText, $endText] = array_map('trim', explode('-->', $lines[$timingIndex], 2));
            $start = self::seconds($startText);
            $end = self::seconds(preg_replace('/\s.*$/', '', $endText));

            $text = trim(strip_tags(implode(' ', array_slice($lines, $timingIndex + 1))));
            $text = preg_replace('/\s+/', ' ', $text);

            if ($start === null || $end === null || $end <= $start || $text === '') {
                continue;
            }

            $segments[] = ['start' => round($start, 2), 'end' => round($end, 2), 'text' => $text];
        }

        if ($segments === []) {
            throw new InvalidArgumentException('No timed captions were found. Upload an .srt or .vtt file with timings.');
        }

        return $segments;
    }

    /** "00:01:02,500", "01:02.500" or "1:02:03.250" to seconds. */
    private static function seconds(string $time): ?float
    {
        if (! preg_match('/^(?:(\d+):)?(\d{1,2}):(\d{2})[.,](\d{1,3})$|^(\d{1,2}):(\d{2})[.,](\d{1,3})$/', $time, $m)) {
            return null;
        }

        if (($m[5] ?? '') !== '') {
            return (int) $m[5] * 60 + (int) $m[6] + ((int) str_pad($m[7], 3, '0')) / 1000;
        }

        return (int) ($m[1] ?? 0) * 3600 + (int) $m[2] * 60 + (int) $m[3] + ((int) str_pad($m[4], 3, '0')) / 1000;
    }
}
