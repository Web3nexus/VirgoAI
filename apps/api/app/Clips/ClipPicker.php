<?php

namespace App\Clips;

/**
 * Turns the model's answer into clips that can be cut. Anything the model gets wrong is corrected or dropped,
 * never trusted: times are clamped to the video, lengths must be in range, and clips may not overlap.
 */
class ClipPicker
{
    private const MAX_TITLE = 160;

    private const MAX_REASON = 500;

    /**
     * System prompt for clip selection. Asks for JSON only, with times taken from the transcript.
     */
    public static function systemPrompt(int $count, int $minSeconds, int $maxSeconds): string
    {
        return <<<PROMPT
You select clips from a timestamped video transcript for a user's request.

Reply with JSON only, no prose, in this exact shape:
{"clips":[{"start":12.5,"end":48.0,"title":"Short headline","reason":"Why this moment matches the request"}]}

Rules:
- Use only times from the transcript: "start" and "end" are seconds from the start of the video.
- Each clip lasts between {$minSeconds} and {$maxSeconds} seconds. Start and end on natural sentence boundaries.
- Return at most {$count} clips, best first. Clips must not overlap.
- Pick moments that answer the request. If nothing fits, return {"clips":[]}.
- Titles are at most 8 words. Reasons are one sentence.
PROMPT;
    }

    /**
     * @param  array<int, array{start: float, end: float, text: string}>  $transcript
     */
    public static function transcriptBlock(array $transcript): string
    {
        return collect($transcript)
            ->map(fn (array $segment) => sprintf('[%.2f-%.2f] %s', $segment['start'], $segment['end'], trim($segment['text'])))
            ->implode("\n");
    }

    /**
     * Pull the JSON object out of the model's reply, even if it wrapped it in a code fence.
     *
     * @return array<int, array<string, mixed>>|null Null when the reply is not usable JSON.
     */
    public static function parseReply(string $reply): ?array
    {
        if (! preg_match('/\{.*\}/s', $reply, $match)) {
            return null;
        }

        $data = json_decode($match[0], true);

        return is_array($data) && isset($data['clips']) && is_array($data['clips']) ? $data['clips'] : null;
    }

    /**
     * @param  array<int, mixed>  $candidates
     * @return array{clips: list<array{start: float, end: float, title: string, reason: string}>, dropped: int}
     */
    public static function validate(array $candidates, float $duration, int $minSeconds, int $maxSeconds, int $count): array
    {
        $accepted = [];
        $dropped = 0;

        foreach ($candidates as $candidate) {
            if (! is_array($candidate) || ! is_numeric($candidate['start'] ?? null) || ! is_numeric($candidate['end'] ?? null)) {
                $dropped++;

                continue;
            }

            $start = max(0.0, (float) $candidate['start']);
            $end = min($duration, (float) $candidate['end']);
            $length = $end - $start;

            if ($length < $minSeconds || $length > $maxSeconds) {
                $dropped++;

                continue;
            }

            $overlaps = collect($accepted)->contains(fn (array $clip) => $start < $clip['end'] && $end > $clip['start']);
            if ($overlaps) {
                $dropped++;

                continue;
            }

            $accepted[] = [
                'start' => round($start, 3),
                'end' => round($end, 3),
                'title' => mb_substr(trim((string) ($candidate['title'] ?? 'Clip')) ?: 'Clip', 0, self::MAX_TITLE),
                'reason' => mb_substr(trim((string) ($candidate['reason'] ?? '')), 0, self::MAX_REASON),
            ];

            if (count($accepted) >= $count) {
                break;
            }
        }

        return ['clips' => $accepted, 'dropped' => $dropped];
    }

    /**
     * Convert a whisper transcript (timestamp pairs) into segments with numbers we can check.
     *
     * @param  array<int, array<string, mixed>>  $chunks
     * @return array<int, array{start: float, end: float, text: string}>
     */
    public static function segmentsFromChunks(array $chunks, float $duration): array
    {
        $segments = [];

        foreach ($chunks as $index => $chunk) {
            $timestamp = $chunk['timestamp'] ?? [];
            $start = is_numeric($timestamp[0] ?? null) ? (float) $timestamp[0] : null;
            $end = is_numeric($timestamp[1] ?? null) ? (float) $timestamp[1] : null;
            $text = trim((string) ($chunk['text'] ?? ''));

            if ($start === null || $text === '') {
                continue;
            }

            // An open-ended last chunk runs to the end of the video.
            $end ??= $duration;

            $segments[] = ['start' => round($start, 2), 'end' => round(min($end, $duration), 2), 'text' => $text];
        }

        return $segments;
    }
}
