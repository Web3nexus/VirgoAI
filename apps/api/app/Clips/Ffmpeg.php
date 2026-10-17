<?php

namespace App\Clips;

use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Thin wrapper over the ffmpeg and ffprobe binaries. Every command has a timeout, and a failure reports the
 * last lines of ffmpeg's own output, so the reason is visible on the run.
 */
class Ffmpeg
{
    public function __construct(
        protected string $ffmpeg = 'ffmpeg',
        protected string $ffprobe = 'ffprobe',
        protected int $timeoutSeconds = 3600,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            (string) config('virgoai.clips.ffmpeg', 'ffmpeg'),
            (string) config('virgoai.clips.ffprobe', 'ffprobe'),
            (int) config('virgoai.clips.command_timeout_seconds', 3600),
        );
    }

    /**
     * @return array{duration: float, width: int|null, height: int|null}
     */
    public function probe(string $path): array
    {
        $json = $this->run([$this->ffprobe, '-v', 'error', '-print_format', 'json', '-show_format', '-show_streams', $path]);
        $data = json_decode($json, true);

        if (! is_array($data)) {
            throw new RuntimeException('The file could not be read as a video.');
        }

        $duration = (float) ($data['format']['duration'] ?? 0);
        $video = collect($data['streams'] ?? [])->firstWhere('codec_type', 'video');

        if ($duration <= 0) {
            throw new RuntimeException('The file has no readable duration. Upload a video file.');
        }

        return [
            'duration' => round($duration, 3),
            'width' => isset($video['width']) ? (int) $video['width'] : null,
            'height' => isset($video['height']) ? (int) $video['height'] : null,
        ];
    }

    /** Mono 16 kHz speech audio, small enough to send to the transcriber. */
    public function extractAudio(string $source, string $destination): void
    {
        $this->run([$this->ffmpeg, '-y', '-i', $source, '-vn', '-ac', '1', '-ar', '16000', '-b:a', '48k', $destination]);
    }

    /** Mono 16 kHz PCM WAV, the input the local engine expects. */
    public function extractWav(string $source, string $destination): void
    {
        $this->run([$this->ffmpeg, '-y', '-i', $source, '-vn', '-ac', '1', '-ar', '16000', '-c:a', 'pcm_s16le', $destination]);
    }

    /**
     * Fit a video to exactly width x height: scale to cover, then crop the overflow from the centre. The
     * output always has the same codec settings, so parts can be joined without re-checking them.
     */
    public function normalize(string $source, string $destination, int $width, int $height): void
    {
        $filter = sprintf('scale=%d:%d:force_original_aspect_ratio=increase,crop=%d:%d,setsar=1', $width, $height, $width, $height);

        $this->run([
            $this->ffmpeg, '-y', '-i', $source,
            '-vf', $filter,
            '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '20', '-pix_fmt', 'yuv420p',
            '-map', '0:v:0', '-map', '0:a?',
            '-c:a', 'aac', '-ar', '48000', '-ac', '2', '-b:a', '128k',
            '-movflags', '+faststart',
            $destination,
        ]);
    }

    /**
     * Join already-normalised parts, in order, into one file.
     *
     * @param  list<string>  $parts
     */
    public function concat(array $parts, string $destination): void
    {
        $list = $destination.'.list.txt';
        file_put_contents($list, collect($parts)->map(fn (string $part) => "file '".str_replace("'", "'\\''", $part)."'")->implode("\n"));

        try {
            $this->run([$this->ffmpeg, '-y', '-f', 'concat', '-safe', '0', '-i', $list, '-c', 'copy', '-movflags', '+faststart', $destination]);
        } finally {
            @unlink($list);
        }
    }

    /**
     * Cut one clip. Re-encoded, so the cut lands on the exact frame and the file plays everywhere.
     */
    public function cut(string $source, float $start, float $end, string $destination): void
    {
        $this->run([
            $this->ffmpeg, '-y', '-i', $source,
            '-ss', number_format($start, 3, '.', ''),
            '-to', number_format($end, 3, '.', ''),
            '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '23',
            '-c:a', 'aac', '-b:a', '128k',
            '-movflags', '+faststart',
            $destination,
        ]);
    }

    /** @param  list<string>  $command */
    private function run(array $command): string
    {
        $process = new Process($command);
        $process->setTimeout($this->timeoutSeconds);
        $process->run();

        if (! $process->isSuccessful()) {
            $tail = collect(preg_split('/\r?\n/', trim($process->getErrorOutput())))->filter()->slice(-3)->implode(' ');
            throw new RuntimeException('Video processing failed: '.($tail !== '' ? $tail : 'exit code '.$process->getExitCode()));
        }

        return $process->getOutput();
    }
}
