<?php

namespace App\Clips;

use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Our own transcription engine: whisper.cpp, which runs on the server with no API and no per-minute charge.
 * It needs the binary and a model file on the server. Check with available().
 */
class LocalTranscriber
{
    public function __construct(
        protected string $binary,
        protected string $model,
        protected int $threads = 4,
        protected int $timeoutSeconds = 7200,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            (string) config('virgoai.clips.whisper_binary', 'whisper-cli'),
            (string) config('virgoai.clips.whisper_model'),
            (int) config('virgoai.clips.whisper_threads', 4),
        );
    }

    public function available(): bool
    {
        if (! is_file($this->model)) {
            return false;
        }

        $process = new Process(['sh', '-c', 'command -v '.escapeshellarg($this->binary)]);
        $process->run();

        return $process->isSuccessful();
    }

    /**
     * @return array<int, array{start: float, end: float, text: string}>
     */
    public function transcribe(string $wavPath): array
    {
        $prefix = $wavPath.'-out';

        $process = new Process([
            $this->binary, '-m', $this->model, '-f', $wavPath,
            '-oj', '-of', $prefix, '-np', '-t', (string) $this->threads,
        ]);
        $process->setTimeout($this->timeoutSeconds);
        $process->run();

        if (! $process->isSuccessful()) {
            $detail = trim($process->getErrorOutput()) ?: 'exit '.$process->getExitCode();
            throw new RuntimeException('The transcription engine failed: '.$detail);
        }

        $json = json_decode((string) @file_get_contents($prefix.'.json'), true);
        @unlink($prefix.'.json');

        if (! is_array($json) || ! isset($json['transcription'])) {
            throw new RuntimeException('The transcription engine returned no transcript.');
        }

        $segments = [];
        foreach ($json['transcription'] as $item) {
            $text = trim((string) ($item['text'] ?? ''));
            $from = $item['offsets']['from'] ?? null;
            $to = $item['offsets']['to'] ?? null;

            if ($text === '' || ! is_numeric($from) || ! is_numeric($to)) {
                continue;
            }

            $segments[] = ['start' => round(((float) $from) / 1000, 2), 'end' => round(((float) $to) / 1000, 2), 'text' => $text];
        }

        return $segments;
    }
}
