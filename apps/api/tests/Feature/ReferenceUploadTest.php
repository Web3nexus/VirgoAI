<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

it('stores an attached reference image and returns the url the provider will fetch', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/v1/uploads/reference', [
        'image' => UploadedFile::fake()->image('look.png', 64, 64),
    ])->assertCreated();

    $url = $response->json('url');
    expect($url)->toContain('/references/'.$user->id.'/');
    $path = str_replace(Storage::disk('public')->url(''), '', $url);
    Storage::disk('public')->assertExists($path);
});

it('refuses a file that is not an image', function () {
    Storage::fake('public');
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/v1/uploads/reference', [
        'image' => UploadedFile::fake()->create('notes.txt', 4, 'text/plain'),
    ])->assertUnprocessable()->assertJsonValidationErrors(['image']);
});

it('refuses an svg or script renamed to look like an image', function () {
    Storage::fake('public');
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/v1/uploads/reference', [
        'image' => UploadedFile::fake()->createWithContent('look.png', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
    ])->assertUnprocessable()->assertJsonValidationErrors(['image']);
});

it('refuses a reference that belongs to another account', function () {
    Storage::fake('public');
    $owner = User::factory()->create();
    $other = User::factory()->create();
    Sanctum::actingAs($other);

    $theirs = Storage::disk('public')->url('references/'.$owner->id.'/secret.jpg');
    $secure = str_replace('http://', 'https://', $theirs);

    $this->postJson('/api/v1/productions', [
        'prompt' => 'A fox crosses a frozen river at dawn.',
        'format' => 'landscape_16_9',
        'quality' => '720p',
        'reference_url' => $secure,
    ])->assertUnprocessable()->assertJsonValidationErrors(['reference_url']);
});
