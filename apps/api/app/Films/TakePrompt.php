<?php

namespace App\Films;

use App\Ai\Fal\FalEndpoints;
use App\Ai\Guardrails;
use DomainException;

/**
 * Writes the prompt for one take and the list of board references it is shot from. Seedance refers to references by
 * their place in the list (@Image1, @Image2...), so the order of the list and the wording of the prompt are built
 * together here, and never by a model.
 */
final class TakePrompt
{
    /** The most cast members one take may show. Each one uses one reference image. */
    public const MAX_CAST_PER_TAKE = 4;

    /**
     * @param  array{cast: list<string>, location: string, action: string, dialogue: list<array{speaker: string, line: string}>}  $take
     * @param  array<string, array{name: string, look: string}>  $cast  Cast members by key.
     * @param  array<string, array{name: string, look: string}>  $locations  Locations by key.
     * @return array{prompt: string, refs: list<string>}
     */
    public static function compose(array $take, int $number, int $total, int $seconds, string $style, array $cast, array $locations, string $directive): array
    {
        $castKeys = array_values(array_unique($take['cast']));

        if ($castKeys === [] || count($castKeys) > self::MAX_CAST_PER_TAKE) {
            throw new DomainException('Each take shows between 1 and '.self::MAX_CAST_PER_TAKE.' cast members.');
        }

        foreach ($castKeys as $key) {
            if (! isset($cast[$key])) {
                throw new DomainException("Take {$number} uses the unknown cast member [{$key}].");
            }
        }

        if (! isset($locations[$take['location']])) {
            throw new DomainException("Take {$number} uses the unknown location [{$take['location']}].");
        }

        $refs = [...$castKeys, $take['location']];

        if (count($refs) > FalEndpoints::SEEDANCE_REF_MAX_IMAGES) {
            throw new DomainException("Take {$number} has too many references.");
        }

        $legend = [];
        foreach ($refs as $i => $key) {
            $index = $i + 1;
            $legend[] = isset($cast[$key])
                ? "@Image{$index} is the character reference sheet of {$cast[$key]['name']}."
                : "@Image{$index} is the location reference of {$locations[$key]['name']}.";
        }

        $people = [];
        foreach ($castKeys as $key) {
            $index = array_search($key, $refs, true) + 1;
            $people[] = "{$cast[$key]['name']} (@Image{$index}): {$cast[$key]['look']}";
        }

        $location = $locations[$take['location']];
        $dialogue = array_map(fn (array $line) => ucfirst($line['speaker']).' says: "'.$line['line'].'"', $take['dialogue']);

        $parts = [
            implode(' ', $legend),
            "Shot {$number} of {$total}, {$seconds} seconds. Style: {$style}.",
            "Place: {$location['name']}. {$location['look']}",
            'People: '.implode(' ', $people),
            'Action: '.trim($take['action']),
        ];

        if ($dialogue !== []) {
            $parts[] = 'Dialogue, spoken and lip-synced: '.implode(' ', $dialogue);
        }

        $parts[] = 'Keep every person exactly as in their reference sheet, with the same face, hair and outfit. Keep the place as in its reference.';

        if ($directive !== '') {
            $parts[] = $directive;
        }

        return [
            'prompt' => Guardrails::withMedia(implode("\n", $parts)),
            'refs' => $refs,
        ];
    }
}
