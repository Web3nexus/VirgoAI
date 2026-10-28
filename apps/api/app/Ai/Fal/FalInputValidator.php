<?php

namespace App\Ai\Fal;

use App\Ai\Data\GenerationRequest;
use App\Models\Project;

/**
 * Rejects a request the endpoint cannot honour, before anything is sent to fal and before any credit is spent.
 *
 * FalEndpoints::payload() quietly maps some values to the nearest one it supports (a Veo duration of 9 becomes 8,
 * a Hailuo resolution other than 720p becomes 512P, any "generate_audio" value becomes a boolean). That is
 * fine for a value the app chose, but a user who asked for 9 seconds should be told, not given 8.
 */
final class FalInputValidator
{
    /** GPT Image's quality enum in fal's schema. */
    private const IMAGE_QUALITIES = ['low', 'medium', 'high'];

    /**
     * The first problem with the request, or null when it is fine.
     */
    public static function problem(string $endpointId, GenerationRequest $request): ?string
    {
        $options = $request->options;

        if ($endpointId === FalEndpoints::TRANSCRIBE) {
            return null;
        }

        if (array_key_exists('size', $options) && ! in_array($options['size'], array_keys(Project::FORMATS), true)) {
            return 'Choose a supported frame: '.implode(', ', array_keys(Project::FORMATS)).'.';
        }

        if (array_key_exists('generate_audio', $options) && ! is_bool($options['generate_audio'])) {
            return 'Audio must be on or off.';
        }

        if (array_key_exists('duration', $options) && $options['duration'] !== null) {
            $problem = self::durationProblem($endpointId, $options['duration']);

            if ($problem !== null) {
                return $problem;
            }
        }

        if (array_key_exists('quality', $options) && $options['quality'] !== null
            && in_array($endpointId, [FalEndpoints::IMAGE_TEXT, FalEndpoints::IMAGE_EDIT], true)
            && ! in_array($options['quality'], self::IMAGE_QUALITIES, true)) {
            return 'Image quality must be '.implode(', ', self::IMAGE_QUALITIES).'.';
        }

        if (array_key_exists('resolution', $options) && $options['resolution'] !== null) {
            return self::resolutionProblem($endpointId, $options['resolution']);
        }

        return null;
    }

    private static function durationProblem(string $endpointId, mixed $value): ?string
    {
        // Seedance takes "auto" or a whole number of seconds from 4 to 15.
        if (in_array($endpointId, [FalEndpoints::VIDEO_IMAGE, FalEndpoints::VIDEO_SEEDANCE_FAST, FalEndpoints::VIDEO_SEEDANCE_REF], true)) {
            if ($value === 'auto') {
                return null;
            }

            return self::wholeSeconds($value, 4, 15, 'between 4 and 15 seconds, or auto');
        }

        if ($endpointId === FalEndpoints::VIDEO_KLING) {
            return self::wholeSeconds($value, 3, 15, 'between 3 and 15 seconds');
        }

        // Veo and Hailuo take only their listed lengths. Any other number is rounded by the builder, so it is refused here.
        if (in_array($endpointId, [FalEndpoints::VIDEO_VEO_31_FAST, FalEndpoints::VIDEO_VEO_3_FAST], true)) {
            return self::exactSeconds($value, [4, 6, 8]);
        }

        if ($endpointId === FalEndpoints::VIDEO_HAILUO) {
            return self::exactSeconds($value, [6, 10]);
        }

        return null;
    }

    /** Whole seconds in a range. Anything else (a fraction, a word, a string of letters) is refused. */
    private static function wholeSeconds(mixed $value, int $min, int $max, string $allowed): ?string
    {
        $seconds = self::asSeconds($value);

        if ($seconds === null || $seconds < $min || $seconds > $max) {
            return "This model takes a duration {$allowed}.";
        }

        return null;
    }

    /** @param  list<int>  $allowed */
    private static function exactSeconds(mixed $value, array $allowed): ?string
    {
        $seconds = self::asSeconds($value);

        if ($seconds === null || ! in_array($seconds, $allowed, true)) {
            return 'This model takes a duration of '.implode(', ', $allowed).' seconds.';
        }

        return null;
    }

    private static function asSeconds(mixed $value): ?int
    {
        return is_int($value) ? $value : (is_string($value) && ctype_digit($value) ? (int) $value : null);
    }

    private static function resolutionProblem(string $endpointId, mixed $value): ?string
    {
        // The payload builder maps only these values. Anything else would be silently sent as the other one.
        $allowed = match ($endpointId) {
            FalEndpoints::VIDEO_VEO_31_FAST, FalEndpoints::VIDEO_VEO_3_FAST => ['720p', '1080p'],
            FalEndpoints::VIDEO_HAILUO => ['512p', '720p'],
            // Seedance's standard model takes up to 4k. The fast tier stops at 720p. Both sets are in fal's schema.
            FalEndpoints::VIDEO_IMAGE, FalEndpoints::VIDEO_SEEDANCE_REF => ['480p', '720p', '1080p', '4k'],
            FalEndpoints::VIDEO_SEEDANCE_FAST => ['480p', '720p'],
            default => null,
        };

        if ($allowed !== null && ! in_array($value, $allowed, true)) {
            return 'This model takes a resolution of '.implode(' or ', $allowed).'.';
        }

        return null;
    }
}
