<?php

use App\Models\Project;
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

it('offers the Quick Start skills of the reference studio', function () {
    $keys = collect(SkillLibrary::all())->keys();

    expect($keys)->toContain('real_estate', 'ugc_ad', 'micro_drama', 'brand_film', 'film_trailer', 'promo_video')
        ->and(SkillLibrary::brief('micro_drama'))->toContain('Skill: Micro Drama')
        ->and(SkillLibrary::brief('micro_drama'))->toContain('Format: Vertical 9:16');
});

it('gives every skill a format that exists, and says what it produces', function () {
    foreach (SkillLibrary::all() as $key => $skill) {
        expect(array_key_exists($skill['defaults']['format'] ?? '', Project::FORMATS))->toBeTrue($key)
            ->and($skill['produces'])->not->toBeEmpty($key);
    }
    expect(SkillLibrary::brief('long_vertical'))->toContain('Format: Vertical 9:16 (1080 x 1920 pixels)')
        ->toContain('Produces: Full video in parts, vertical 9:16');
});

it('writes a Quick Start prompt from its answers, in form order, and leaves out empty optional answers', function () {
    $prompt = SkillLibrary::compose('real_estate', ['property' => '3 bed house, pool and sea view.', 'preferences' => '']);

    expect($prompt)->toBe('Property: 3 bed house, pool and sea view.');

    $film = SkillLibrary::compose('video_generator', ['story' => 'A lighthouse keeper finds a letter.', 'style' => 'monochrome', 'length' => 20]);
    expect($film)->toBe("Film: A lighthouse keeper finds a letter.\nVisual style: Monochrome\nLength: 20 seconds");
});

it('reports the Quick Start answers that are missing, unknown or out of range', function () {
    expect(SkillLibrary::validateInputs('real_estate', []))->toHaveKey('property')
        ->and(SkillLibrary::validateInputs('explainer', ['topic' => 'Photosynthesis', 'style' => 'neon']))->toHaveKey('style')
        ->and(SkillLibrary::validateInputs('video_generator', ['story' => 'A fox.', 'length' => 60]))->toHaveKey('length')
        ->and(SkillLibrary::validateInputs('video_generator', ['story' => 'A fox.']))->toBe([]);
});

it('refuses a Quick Start run whose required answers are missing, before any provider is called', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/productions', [
        'quality' => '720p',
        'skill_key' => 'micro_drama',
        'inputs' => ['characters' => 'A nervous barista in her twenties.'],
    ])->assertUnprocessable()->assertJsonValidationErrors(['inputs.story']);

    $this->postJson('/api/v1/productions', [
        'quality' => '720p',
        'skill_key' => 'ad_spot',
        'inputs' => ['product_name' => 'Glow Lip Balm', 'benefits' => 'Keeps lips soft all day.'],
    ])->assertUnprocessable()->assertJsonValidationErrors(['reference_url']);
});

it('lists each Quick Start with its form fields', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $found = collect($this->getJson('/api/v1/skills')->assertOk()->json('data'))->firstWhere('key', 'micro_drama');
    expect(collect($found['inputs'])->pluck('key')->all())->toBe(['characters', 'story', 'vibe']);
});
