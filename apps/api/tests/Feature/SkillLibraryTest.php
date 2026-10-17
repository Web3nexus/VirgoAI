<?php

use App\Models\User;
use App\Skills\SkillLibrary;
use Laravel\Sanctum\Sanctum;

it('lists the skills, filters them by search and category, and never sends the instructions', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $all = $this->getJson('/api/v1/skills')->assertOk()->json('data');
    expect(count($all))->toBeGreaterThanOrEqual(8)
        ->and($all[0])->not->toHaveKey('instructions');

    $video = $this->getJson('/api/v1/skills?category=Video')->assertOk()->json('data');
    expect($video)->not->toBeEmpty()
        ->and(collect($video)->every(fn ($s) => $s['category'] === 'Video'))->toBeTrue();

    $found = $this->getJson('/api/v1/skills?q=product')->assertOk()->json('data');
    expect(collect($found)->pluck('key'))->toContain('ad_spot');
});

it('gives the model the instructions of the selected skill, and nothing when no skill is selected', function () {
    expect(SkillLibrary::brief(null))->toBe('')
        ->and(SkillLibrary::brief('not_a_skill'))->toBe('')
        ->and(SkillLibrary::brief('ad_spot'))->toContain('Skill: Product Ad')
        ->and(SkillLibrary::brief('ad_spot'))->toContain('15 to 30 second')
        ->and(SkillLibrary::brief('image_generator'))->toContain('keep that format');
});

it('refuses a production with a skill that does not exist', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/productions', [
        'prompt' => 'A fox crosses a frozen river at dawn.',
        'format' => 'landscape_16_9',
        'quality' => '720p',
        'skill_key' => 'made_up',
    ])->assertUnprocessable()->assertJsonValidationErrors(['skill_key']);
});

it('refuses a tool run with a skill that does not exist', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/tools/story/run', [
        'prompt' => 'A fox crosses a frozen river at dawn.',
        'skill_key' => 'made_up',
    ])->assertUnprocessable()->assertJsonValidationErrors(['skill_key']);
});

it('gives every skill a format that exists, and says what it produces', function () {
    foreach (SkillLibrary::all() as $key => $skill) {
        expect(array_key_exists($skill['defaults']['format'] ?? '', \App\Models\Project::FORMATS))->toBeTrue($key)
            ->and($skill['produces'])->not->toBeEmpty($key);
    }
    expect(SkillLibrary::brief('long_vertical'))->toContain('Format: Vertical 9:16 (1080 x 1920 pixels)')
        ->toContain('Produces: Full video in parts, vertical 9:16');
});
