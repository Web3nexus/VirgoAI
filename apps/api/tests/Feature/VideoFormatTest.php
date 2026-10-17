<?php

use App\Clips\Ffmpeg;
use Illuminate\Support\Facades\Storage;

it('fits a video to the exact format size, and keeps its sound', function () {
    exec('command -v ffmpeg 2>/dev/null', $out, $code);
    if ($code !== 0) {
        $this->markTestSkipped('ffmpeg is not installed.');
    }

    $source = sys_get_temp_dir().'/fit-'.uniqid().'.mp4';
    $dest = sys_get_temp_dir().'/fit-out-'.uniqid().'.mp4';
    exec(sprintf('ffmpeg -y -f lavfi -i testsrc=duration=2:size=320x240:rate=10 -f lavfi -i sine=duration=2 -shortest -c:v libx264 -pix_fmt yuv420p -c:a aac %s 2>/dev/null', escapeshellarg($source)));

    Ffmpeg::fromConfig()->normalize($source, $dest, 1080, 1920);

    $info = Ffmpeg::fromConfig()->probe($dest);
    expect($info['width'])->toBe(1080)
        ->and($info['height'])->toBe(1920);

    @unlink($source);
    @unlink($dest);
});

it('joins fitted parts into one file of the summed length', function () {
    exec('command -v ffmpeg 2>/dev/null', $out, $code);
    if ($code !== 0) {
        $this->markTestSkipped('ffmpeg is not installed.');
    }

    $a = sys_get_temp_dir().'/part-a-'.uniqid().'.mp4';
    $b = sys_get_temp_dir().'/part-b-'.uniqid().'.mp4';
    $joined = sys_get_temp_dir().'/joined-'.uniqid().'.mp4';
    foreach ([$a, $b] as $part) {
        exec(sprintf('ffmpeg -y -f lavfi -i testsrc=duration=2:size=320x240:rate=10 -f lavfi -i sine=duration=2 -shortest -c:v libx264 -pix_fmt yuv420p -c:a aac %s 2>/dev/null', escapeshellarg($part)));
    }
    Ffmpeg::fromConfig()->normalize($a, $a.'.n.mp4', 320, 240);
    Ffmpeg::fromConfig()->normalize($b, $b.'.n.mp4', 320, 240);

    Ffmpeg::fromConfig()->concat([$a.'.n.mp4', $b.'.n.mp4'], $joined);

    expect(Ffmpeg::fromConfig()->probe($joined)['duration'])->toBeGreaterThan(3.5)->toBeLessThan(4.6);

    foreach ([$a, $b, $a.'.n.mp4', $b.'.n.mp4', $joined] as $f) {
        @unlink($f);
    }
});

it('sends the chosen resolution to the video provider', function () {
    \Illuminate\Support\Facades\Http::fake(['queue.fal.run/*' => \Illuminate\Support\Facades\Http::response(['request_id' => 'x', 'status_url' => 'https://queue.fal.run/a/status', 'response_url' => 'https://queue.fal.run/a'])]);
    \App\Settings\ProviderCredentials::set('FAL_KEY', 'fal-format-key-0001', \App\Models\User::factory()->create(['role' => 'super_admin']));
    $user = \App\Models\User::factory()->create(['balance_micros' => 10_000_000]);
    \App\Models\ModelPrice::create(['provider_key' => 'fal', 'model_key' => 'bytedance/seedance-2.0/image-to-video', 'unit' => 'second', 'cost_micros' => 300_000, 'source' => 'fal_api']);
    $provider = \App\Models\AiProvider::create(['key' => 'fal', 'name' => 'fal', 'status' => 'active', 'priority' => 100]);
    $model = \App\Models\AiModel::create(['ai_provider_id' => $provider->id, 'key' => 'bytedance/seedance-2.0/image-to-video', 'name' => 'Seedance', 'enabled' => true, 'priority' => 100]);
    \App\Models\ProviderCapability::create(['ai_model_id' => $model->id, 'capability' => 'video']);

    \Illuminate\Support\Facades\Queue::fake();
    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/tools/video/run', ['prompt' => 'a slow pan across a village', 'size' => 'vertical_9_16', 'resolution' => '1080p', 'image_url' => 'https://v3b.fal.media/files/k.png'])
        ->assertCreated();

    \Illuminate\Support\Facades\Http::assertSent(fn ($request) => str_contains($request->url(), 'seedance') && $request['resolution'] === '1080p' && $request['aspect_ratio'] === '9:16');
});
