<?php

namespace App\Ai;

/**
 * Rules added to every model call, so the AI stays within what was asked and refuses the harmful cases.
 * They are appended, never replaced, so a skill or a director format still applies in full.
 */
final class Guardrails
{
    public const TEXT = 'Stay within the request. Do not add facts, names, products, prices, awards, testimonials or people the request does not contain. Do not write sexual content involving minors, hateful or violent content aimed at real people, or content that impersonates a real person. If the request asks for any of these, do not produce them; keep the reply in the format the instructions above require.';

    public const MEDIA = 'No readable text, logos, watermarks or real public figures unless the request names them. Keep to the scene described.';

    /** A text system prompt with the rules added. */
    public static function withText(?string $system): string
    {
        return trim(implode("\n\n", array_filter([$system, self::TEXT])));
    }

    /** An image or video prompt with the rules added. */
    public static function withMedia(string $prompt): string
    {
        return trim($prompt."\n".self::MEDIA);
    }
}
