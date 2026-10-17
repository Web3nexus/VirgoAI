<?php

use App\Tools\Skills\DirectorScript;

const DIRECTOR_IDENTITY = 'Mia, 30, short red hair, green raincoat, freckles, carries a brass compass';
const DIRECTOR_PROMPT = 'A rain soaked kitchen at dusk with one warm lamp over a wooden table';

/** A valid dialogue script with one connected voice. Tests change one thing at a time. */
function directorScript(array $overrides = []): array
{
    $script = [
        'title' => 'Compass',
        'logline' => 'A woman follows a compass home.',
        'characters' => [[
            'character_id' => 'mia',
            'name' => 'Mia',
            'identity_block' => DIRECTOR_IDENTITY,
            'voice' => ['voice_id' => 'af_bella', 'speed' => 1.0, 'description' => 'warm, mid pitch, calm'],
        ]],
        'environments' => [[
            'environment_id' => 'kitchen',
            'name' => 'Kitchen',
            'prompt' => DIRECTOR_PROMPT,
            'lighting' => 'warm lamp',
            'time_of_day' => 'dusk',
            'palette' => ['#A1B2C3'],
        ]],
        'sections' => [[
            'section_id' => 'hook',
            'purpose' => 'hook',
            'environment_id' => 'kitchen',
            'character_ids' => ['mia'],
            'start_seconds' => 0,
            'end_seconds' => 10,
            'narration' => '',
        ]],
        'lines' => [[
            'line_id' => 'l1',
            'section_id' => 'hook',
            'speaker' => 'mia',
            'text' => 'Where did you put the compass?',
            'emotion' => 'tense',
            'start_seconds' => 1,
            'end_seconds' => 4,
        ]],
    ];

    return array_replace_recursive($script, $overrides);
}

function directorReply(array $script): string
{
    return "Here is the script:\n```json\n".json_encode($script)."\n```";
}

it('accepts a valid dialogue script and keeps the speaker and voice assignments', function () {
    $result = DirectorScript::parse(directorReply(directorScript()), 'dialogue', ['af_bella']);

    expect($result['ok'])->toBeTrue();
    expect($result['errors'])->toBe([]);
    expect($result['script']['audio_mode'])->toBe('dialogue');
    expect($result['script']['characters'][0]['voice']['voice_id'])->toBe('af_bella');
    expect($result['script']['lines'][0]['speaker'])->toBe('mia');
});

it('accepts narration-only story scripts', function () {
    $story = directorScript([
        'characters' => [[
            'character_id' => 'mia', 'name' => 'Mia', 'identity_block' => DIRECTOR_IDENTITY,
            'voice' => ['voice_id' => null, 'speed' => 1.0, 'description' => ''],
        ]],
        'sections' => [[
            'section_id' => 'hook', 'purpose' => 'hook', 'environment_id' => 'kitchen',
            'character_ids' => ['mia'], 'start_seconds' => 0, 'end_seconds' => 10,
            'narration' => 'Mia finds the compass on the table.',
        ]],
    ]);
    // array_replace_recursive merges lists by index, so clear the dialogue line explicitly.
    $story['lines'] = [];

    $result = DirectorScript::parse(directorReply($story), 'story', []);

    expect($result['ok'])->toBeTrue();
    expect($result['script']['audio_mode'])->toBe('narration');
});

it('rejects a reply that is not JSON', function () {
    $result = DirectorScript::parse('Once upon a time there was a fox.', 'story', []);

    expect($result['ok'])->toBeFalse();
    expect($result['errors'][0])->toContain('not a JSON object');
});

it('rejects a dialogue line from a speaker who is not in the cast', function () {
    $script = directorScript(['lines' => [[
        'line_id' => 'l1', 'section_id' => 'hook', 'speaker' => 'ghost', 'text' => 'Boo.',
        'start_seconds' => 1, 'end_seconds' => 2,
    ]]]);

    $result = DirectorScript::parse(directorReply($script), 'dialogue', ['af_bella']);

    expect($result['ok'])->toBeFalse();
    expect(implode(' ', $result['errors']))->toContain('"ghost" is not a character or narrator');
});

it('rejects a voice that is not connected', function () {
    $result = DirectorScript::parse(directorReply(directorScript()), 'dialogue', ['some_other_voice']);

    expect($result['ok'])->toBeFalse();
    expect(implode(' ', $result['errors']))->toContain('"af_bella" is not a connected voice');
});

it('rejects dialogue when no voice is connected at all', function () {
    $result = DirectorScript::parse(directorReply(directorScript()), 'dialogue', []);

    expect($result['ok'])->toBeFalse();
    expect(implode(' ', $result['errors']))->toContain('is not a connected voice');
});

it('rejects a speed outside 0.5 to 2.0', function () {
    $script = directorScript(['characters' => [[
        'character_id' => 'mia', 'name' => 'Mia', 'identity_block' => DIRECTOR_IDENTITY,
        'voice' => ['voice_id' => 'af_bella', 'speed' => 3.0, 'description' => 'warm'],
    ]]]);

    $result = DirectorScript::parse(directorReply($script), 'dialogue', ['af_bella']);

    expect($result['ok'])->toBeFalse();
    expect(implode(' ', $result['errors']))->toContain('speed must be between 0.5 and 2');
});

it('rejects a line that falls outside its section', function () {
    $script = directorScript(['lines' => [[
        'line_id' => 'l1', 'section_id' => 'hook', 'speaker' => 'mia', 'text' => 'Late line.',
        'start_seconds' => 9, 'end_seconds' => 14,
    ]]]);

    $result = DirectorScript::parse(directorReply($script), 'dialogue', ['af_bella']);

    expect($result['ok'])->toBeFalse();
    expect(implode(' ', $result['errors']))->toContain('timing falls outside its section');
});

it('rejects a section that uses an unknown environment or character', function () {
    $script = directorScript(['sections' => [[
        'section_id' => 'hook', 'purpose' => 'hook', 'environment_id' => 'moon',
        'character_ids' => ['ghost'], 'start_seconds' => 0, 'end_seconds' => 10,
    ]]]);

    $result = DirectorScript::parse(directorReply($script), 'dialogue', ['af_bella']);

    expect($result['ok'])->toBeFalse();
    expect(implode(' ', $result['errors']))->toContain('environment_id does not match');
    expect(implode(' ', $result['errors']))->toContain('uses character "ghost"');
});

it('rejects a short identity block', function () {
    $script = directorScript(['characters' => [[
        'character_id' => 'mia', 'name' => 'Mia', 'identity_block' => 'Red hair.',
        'voice' => ['voice_id' => 'af_bella', 'speed' => 1.0, 'description' => 'warm'],
    ]]]);

    $result = DirectorScript::parse(directorReply($script), 'dialogue', ['af_bella']);

    expect($result['ok'])->toBeFalse();
    expect(implode(' ', $result['errors']))->toContain('identity_block must be at least 40 characters');
});
