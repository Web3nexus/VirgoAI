<?php

namespace App\Tools\Skills;

/**
 * Turns a director model reply into a validated script, or a list of reasons it is not usable.
 * Runs the same rules as the pipeline contracts: unique ids, known speakers, timing inside
 * sections, voices from the registry, speed in range, and the story/dialogue split.
 */
final class DirectorScript
{
    private const ID = '/^[a-z][a-z0-9_]*$/';

    private const PURPOSES = ['hook', 'setup', 'build', 'climax', 'landing'];

    private const NARRATOR = 'narrator';

    /**
     * @param  list<string>  $voiceIds
     * @return array{ok: bool, script: array<string, mixed>|null, errors: list<string>}
     */
    public static function parse(string $reply, string $scriptType, array $voiceIds): array
    {
        $json = self::extractObject($reply);
        if ($json === null) {
            return self::fail(['The reply was not a JSON object.']);
        }

        $errors = [];
        $dialogue = $scriptType === DirectorSkill::SCRIPT_DIALOGUE;

        $title = $json['title'] ?? null;
        if (! is_string($title) || trim($title) === '') {
            $errors[] = 'title is missing.';
        }

        // Characters
        $characters = self::list($json, 'characters', $errors);
        $cast = [];
        foreach ($characters as $i => $character) {
            $path = "characters[{$i}]";
            if (! self::validId($character['character_id'] ?? null)) {
                $errors[] = "{$path}.character_id must be a lowercase id.";

                continue;
            }
            $id = $character['character_id'];
            if (isset($cast[$id])) {
                $errors[] = "{$path}: character_id \"{$id}\" is used twice.";
            }
            $cast[$id] = true;

            if (! is_string($character['name'] ?? null) || trim($character['name']) === '') {
                $errors[] = "{$path}.name is missing.";
            }
            if (mb_strlen((string) ($character['identity_block'] ?? '')) < 40) {
                $errors[] = "{$path}.identity_block must be at least 40 characters.";
            }

            $voice = $character['voice'] ?? null;
            $voiceId = is_array($voice) ? ($voice['voice_id'] ?? null) : null;
            if ($dialogue) {
                if (! is_string($voiceId) || $voiceId === '') {
                    $errors[] = "{$path}.voice.voice_id is required for dialogue.";
                } elseif (! in_array($voiceId, $voiceIds, true)) {
                    $errors[] = "{$path}.voice.voice_id \"{$voiceId}\" is not a connected voice.";
                }
            }
            if (is_array($voice) && isset($voice['speed'])) {
                $speed = $voice['speed'];
                if (! is_numeric($speed) || $speed < DirectorSkill::SPEED_MIN || $speed > DirectorSkill::SPEED_MAX) {
                    $errors[] = "{$path}.voice.speed must be between ".DirectorSkill::SPEED_MIN.' and '.DirectorSkill::SPEED_MAX.'.';
                }
            }
        }

        // Environments
        $environments = self::list($json, 'environments', $errors);
        $places = [];
        foreach ($environments as $i => $environment) {
            $path = "environments[{$i}]";
            if (! self::validId($environment['environment_id'] ?? null)) {
                $errors[] = "{$path}.environment_id must be a lowercase id.";

                continue;
            }
            $places[$environment['environment_id']] = true;
            if (mb_strlen((string) ($environment['prompt'] ?? '')) < 40) {
                $errors[] = "{$path}.prompt must be at least 40 characters.";
            }
        }

        // Sections
        $sections = self::list($json, 'sections', $errors);
        $sectionIds = [];
        $lastEnd = 0;
        foreach ($sections as $i => $section) {
            $path = "sections[{$i}]";
            $id = $section['section_id'] ?? null;
            if (! self::validId($id)) {
                $errors[] = "{$path}.section_id must be a lowercase id.";

                continue;
            }
            $sectionIds[$id] = ['start' => $section['start_seconds'] ?? null, 'end' => $section['end_seconds'] ?? null];

            if (! in_array($section['purpose'] ?? null, self::PURPOSES, true)) {
                $errors[] = "{$path}.purpose must be one of ".implode(', ', self::PURPOSES).'.';
            }
            if (! isset($places[$section['environment_id'] ?? ''])) {
                $errors[] = "{$path}.environment_id does not match an environment.";
            }
            foreach ((array) ($section['character_ids'] ?? []) as $cid) {
                if (! isset($cast[$cid])) {
                    $errors[] = "{$path} uses character \"{$cid}\", which is not in characters.";
                }
            }

            $start = $section['start_seconds'] ?? null;
            $end = $section['end_seconds'] ?? null;
            if (! is_numeric($start) || ! is_numeric($end) || $end <= $start) {
                $errors[] = "{$path} needs numeric start_seconds and a later end_seconds.";
            } else {
                if ($start < $lastEnd) {
                    $errors[] = "{$path} starts before the previous section ends.";
                }
                $lastEnd = $end;
            }

            if (! $dialogue && trim((string) ($section['narration'] ?? '')) === '') {
                $errors[] = "{$path}.narration is empty. A story needs narration for every section.";
            }
        }

        // Lines
        $lines = self::list($json, 'lines', $errors, allowEmpty: ! $dialogue);
        if (! $dialogue && $lines !== []) {
            $errors[] = 'lines must be empty for a story script.';
        }
        $lineIds = [];
        foreach ($lines as $i => $line) {
            $path = "lines[{$i}]";
            $id = $line['line_id'] ?? null;
            if (! self::validId($id)) {
                $errors[] = "{$path}.line_id must be a lowercase id.";

                continue;
            }
            if (isset($lineIds[$id])) {
                $errors[] = "{$path}: line_id \"{$id}\" is used twice.";
            }
            $lineIds[$id] = true;

            $speaker = $line['speaker'] ?? null;
            if ($speaker !== self::NARRATOR && ! isset($cast[$speaker])) {
                $errors[] = "{$path}.speaker \"".(is_string($speaker) ? $speaker : '').'" is not a character or narrator.';
            }
            if (! is_string($line['text'] ?? null) || trim($line['text']) === '') {
                $errors[] = "{$path}.text is empty.";
            }

            $section = $sectionIds[$line['section_id'] ?? ''] ?? null;
            if ($section === null) {
                $errors[] = "{$path}.section_id does not match a section.";
            } elseif (is_numeric($line['start_seconds'] ?? null) && is_numeric($line['end_seconds'] ?? null)) {
                if ($line['start_seconds'] < $section['start'] || $line['end_seconds'] > $section['end']
                    || $line['end_seconds'] <= $line['start_seconds']) {
                    $errors[] = "{$path} timing falls outside its section.";
                }
            } else {
                $errors[] = "{$path} needs numeric start_seconds and end_seconds.";
            }
        }

        if ($errors !== []) {
            return self::fail($errors);
        }

        return [
            'ok' => true,
            'script' => [
                'title' => trim($title),
                'logline' => (string) ($json['logline'] ?? ''),
                'audio_mode' => $dialogue ? 'dialogue' : 'narration',
                'characters' => array_values($characters),
                'environments' => array_values($environments),
                'sections' => array_values($sections),
                'lines' => array_values($lines),
            ],
            'errors' => [],
        ];
    }

    /** @return array<string, mixed>|null */
    private static function extractObject(string $reply): ?array
    {
        $start = strpos($reply, '{');
        $end = strrpos($reply, '}');
        if ($start === false || $end === false || $end <= $start) {
            return null;
        }
        $decoded = json_decode(substr($reply, $start, $end - $start + 1), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<string, mixed>  $json
     * @param  list<string>  $errors
     * @return list<array<string, mixed>>
     */
    private static function list(array $json, string $key, array &$errors, bool $allowEmpty = false): array
    {
        $value = $json[$key] ?? null;
        if (! is_array($value) || (! $allowEmpty && $value === [])) {
            if (! ($allowEmpty && is_array($value))) {
                $errors[] = "{$key} is missing or empty.";
            }

            return [];
        }

        return array_values(array_filter($value, 'is_array'));
    }

    private static function validId(mixed $value): bool
    {
        return is_string($value) && preg_match(self::ID, $value) === 1;
    }

    /** @param  list<string>  $errors */
    private static function fail(array $errors): array
    {
        return ['ok' => false, 'script' => null, 'errors' => $errors];
    }
}
