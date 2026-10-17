<?php

namespace App\Tools\Skills;

/**
 * The director skill: instructions that make a text model return a production script as JSON.
 * The JSON matches the pipeline contracts (script, character_design, environment_design), so a
 * result can move into the video pipeline without retyping. DirectorScript validates it.
 */
final class DirectorSkill
{
    public const SCRIPT_STORY = 'story';

    public const SCRIPT_DIALOGUE = 'dialogue';

    public const SKILL_DIRECTOR = 'director';

    public const SKILL_PLAIN = 'plain';

    public const SCRIPT_TYPES = [self::SCRIPT_STORY, self::SCRIPT_DIALOGUE];

    public const SKILLS = [self::SKILL_DIRECTOR, self::SKILL_PLAIN];

    /** Speed bounds shared with the contracts (character_design.voice.speed). */
    public const SPEED_MIN = 0.5;

    public const SPEED_MAX = 2.0;

    /**
     * The system instruction for a run, or null when the user chose a plain prompt.
     *
     * @param  list<string>  $voiceIds  Voices the registry can actually speak. Empty when none are connected.
     */
    public static function systemPrompt(string $skill, string $scriptType, array $voiceIds = []): ?string
    {
        if ($skill !== self::SKILL_DIRECTOR) {
            return null;
        }

        $dialogue = $scriptType === self::SCRIPT_DIALOGUE;
        $voices = $voiceIds === []
            ? 'No voices are connected yet. Leave voice_id as null for every character.'
            : 'Available voice_id values (choose only from this list): '.implode(', ', $voiceIds).'.';

        return implode("\n", [
            'You are the director of a short video. Write a production script a film crew could shoot.',
            'Reply with one JSON object and nothing else. No markdown, no commentary.',
            '',
            'Shape of the object:',
            '{',
            '  "title": "short title",',
            '  "logline": "one sentence",',
            '  "characters": [',
            '    {"character_id": "mia", "name": "Mia",',
            '     "identity_block": "at least 40 characters: age, hair, wardrobe, palette, proportions",',
            '     "voice": {"voice_id": "one from the list or null", "speed": 1.0, "description": "age, timbre, pace, accent"}}',
            '  ],',
            '  "environments": [',
            '    {"environment_id": "kitchen", "name": "Kitchen", "prompt": "at least 40 characters describing the place",',
            '     "lighting": "warm lamp", "time_of_day": "dusk", "palette": ["#A1B2C3"]}',
            '  ],',
            '  "sections": [',
            '    {"section_id": "hook", "purpose": "hook|setup|build|climax|landing", "environment_id": "kitchen",',
            '     "character_ids": ["mia"], "start_seconds": 0, "end_seconds": 10,',
            '     "narration": "narrator text for this section, or empty"}',
            '  ],',
            $dialogue
                ? '  "lines": [{"line_id": "l1", "section_id": "hook", "speaker": "mia", "text": "spoken words", "emotion": "tense", "start_seconds": 1, "end_seconds": 3}]'
                : '  "lines": []',
            '}',
            '',
            'Rules:',
            '- Ids are lowercase letters, digits and underscores, starting with a letter. Every id you reference must be defined above it.',
            '- Each character appears in the list once. Give every character a stable identity_block and reuse it in every shot.',
            '- Each section has an environment_id and character_ids that exist. Sections run in order and do not overlap.',
            $dialogue
                ? '- Dialogue: every line names a speaker that is a character_id, or "narrator" for scene-setting words. Each line sits inside its section\'s time window.'
                : '- Story: lines must be an empty list. The narration field carries the words for each section.',
            "- Voice: speed is between 0.5 and 2.0, where 1.0 is normal pace. Each character's voice description must fit its age and role.",
            "- $voices",
            '- Keep total length close to the target. Assume about 2.5 spoken words per second.',
        ]);
    }

    public static function dialogueMode(string $scriptType): bool
    {
        return $scriptType === self::SCRIPT_DIALOGUE;
    }
}
