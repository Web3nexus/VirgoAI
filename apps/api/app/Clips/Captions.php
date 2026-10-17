<?php

namespace App\Clips;

/**
 * Captions from a transcript, for a whole video or one clip. Times are shifted so a clip's captions start at zero.
 * Delivered as WebVTT (for the player and the web) or SRT (for editors). Burning them into the picture needs an
 * ffmpeg build with subtitle support, which this server does not have yet.
 */
class Captions
{
    /**
     * @param  array<int, array{start: float, end: float, text: string}>  $segments
     * @return array<int, array{start: float, end: float, text: string}>
     */
    public static function window(array $segments, float $from, float $to): array
    {
        $out = [];

        foreach ($segments as $segment) {
            if ($segment['end'] <= $from || $segment['start'] >= $to) {
                continue;
            }

            $out[] = [
                'start' => max(0.0, $segment['start'] - $from),
                'end' => min($to, $segment['end']) - $from,
                'text' => trim($segment['text']),
            ];
        }

        return $out;
    }

    /** @param  array<int, array{start: float, end: float, text: string}>  $cues */
    public static function vtt(array $cues): string
    {
        $body = collect($cues)->map(fn (array $cue) => self::time($cue['start'], '.').' --> '.self::time($cue['end'], '.')."\n".self::escape($cue['text']));

        return "WEBVTT\n\n".$body->implode("\n\n")."\n";
    }

    /** @param  array<int, array{start: float, end: float, text: string}>  $cues */
    public static function srt(array $cues): string
    {
        $body = collect($cues)->values()->map(fn (array $cue, int $index) => ($index + 1)."\n".self::time($cue['start'], ',').' --> '.self::time($cue['end'], ',')."\n".$cue['text']);

        return $body->implode("\n\n")."\n";
    }

    public static function time(float $seconds, string $separator): string
    {
        $ms = (int) round(max(0.0, $seconds) * 1000);
        $h = intdiv($ms, 3_600_000);
        $m = intdiv($ms % 3_600_000, 60_000);
        $s = intdiv($ms % 60_000, 1000);
        $milli = $ms % 1000;

        return sprintf('%02d:%02d:%02d%s%03d', $h, $m, $s, $separator, $milli);
    }

    private static function escape(string $text): string
    {
        // One pass, so the & written for < and > is not escaped again.
        return strtr($text, ['&' => '&amp;', '<' => '&lt;', '>' => '&gt;']);
    }
}
