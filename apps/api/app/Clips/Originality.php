<?php

namespace App\Clips;

/**
 * Guards the rewrite against copying. A story that shares any run of eight consecutive words with the source
 * is not kept. Words are compared lowercased, with punctuation removed, so a change of case or a comma does not
 * hide a copied sentence.
 */
class Originality
{
    public const RUN_LENGTH = 8;

    /** Number of eight-word runs the story shares with the source. Zero is the only acceptable value. */
    public static function sharedRuns(string $story, string $source): int
    {
        $sourceRuns = self::runs($source);
        $storyRuns = self::runs($story);

        return count(array_intersect_key($storyRuns, $sourceRuns));
    }

    /**
     * @return array<string, true>
     */
    private static function runs(string $text): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $runs = [];

        for ($i = 0; $i + self::RUN_LENGTH <= count($words); $i++) {
            $runs[implode(' ', array_slice($words, $i, self::RUN_LENGTH))] = true;
        }

        return $runs;
    }
}
