<?php

namespace App\Clips;

/**
 * The prompt and the answer format for writing an original story from a transcript.
 */
class Remix
{
    public static function systemPrompt(): string
    {
        return <<<'PROMPT'
You write an ORIGINAL story inspired by a video transcript. The transcript is source material to understand,
not text to reuse.

Rules:
- Keep the same narrative shape: the same kind of situation, the same stakes, the same turning point.
- Write every sentence in new words. Never copy a sentence, a phrase of eight or more words, or a name from the transcript.
- Invent new names for people and places, and a new setting if the original one is identifiable.
- Make the story self-contained, with a clear beginning, middle and end, in 6 to 12 short scenes.

Reply with JSON only, in this exact shape:
{"title":"Short title","logline":"One sentence summary","story":"The full story as plain paragraphs separated by blank lines"}
PROMPT;
    }

    /**
     * @return array{title: string, logline: string, story: string}|null Null when the reply is not usable.
     */
    public static function parseReply(string $reply): ?array
    {
        if (! preg_match('/\{.*\}/s', $reply, $match)) {
            return null;
        }

        $data = json_decode($match[0], true);

        if (! is_array($data) || ! is_string($data['story'] ?? null) || trim($data['story']) === '') {
            return null;
        }

        return [
            'title' => mb_substr(trim((string) ($data['title'] ?? 'Untitled')), 0, 200),
            'logline' => trim((string) ($data['logline'] ?? '')),
            'story' => trim($data['story']),
        ];
    }
}
