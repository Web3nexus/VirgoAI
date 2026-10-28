<?php

namespace App\Ai\Adapters;

use App\Ai\Contracts\AsyncProviderAdapter;
use App\Ai\Data\GenerationRequest;
use App\Ai\Data\GenerationResult;
use App\Ai\Fal\FalEndpoints;
use App\Ai\Fal\FalInputValidator;
use App\Enums\Capability;
use App\Settings\ProviderCredentials;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * fal.ai queue adapter for image and video.
 *
 * generate() submits the job and returns it as queued, so no web request waits for a render. The
 * GenerationCompleter calls poll() later. The key is sent only in the Authorization header and never
 * appears in a returned error or a log line.
 */
class FalAdapter implements AsyncProviderAdapter
{
    private const QUEUE_BASE = 'https://queue.fal.run';

    public function key(): string
    {
        return 'fal';
    }

    /**
     * @return list<Capability>
     */
    public function capabilities(): array
    {
        return [Capability::Image, Capability::Video];
    }

    public function isConfigured(): bool
    {
        return $this->apiKey() !== null;
    }

    public function generate(GenerationRequest $request): GenerationResult
    {
        $apiKey = $this->apiKey();

        if ($apiKey === null) {
            return GenerationResult::failure('Fal API key is not configured.', $this->key(), $request->modelKey);
        }

        if (! in_array($request->capability, $this->capabilities(), true)) {
            return GenerationResult::failure(
                "Fal adapter does not handle [{$request->capability->value}] generation.",
                $this->key(),
                $request->modelKey,
            );
        }

        $endpoint = FalEndpoints::resolve($request->modelKey, $request->inputs);

        if ($endpoint === null) {
            return GenerationResult::failure("Fal model [{$request->modelKey}] is not one this app supports.", $this->key(), $request->modelKey);
        }

        // Refused before the submit, so a bad option costs nothing.
        if (($problem = FalInputValidator::problem($endpoint, $request)) !== null) {
            return GenerationResult::failure($problem, $this->key(), $request->modelKey);
        }

        try {
            $payload = FalEndpoints::payload($endpoint, $request);
        } catch (InvalidArgumentException $e) {
            return GenerationResult::failure($e->getMessage(), $this->key(), $request->modelKey);
        }

        try {
            $submit = $this->client($apiKey)->post(self::QUEUE_BASE.'/'.$endpoint, $payload);
        } catch (Throwable $e) {
            Log::warning('fal submit failed', ['endpoint' => $endpoint, 'error' => $e->getMessage()]);

            return GenerationResult::failure('Could not reach fal. Check the network and try again.', $this->key(), $request->modelKey);
        }

        if (! $submit->successful()) {
            return $this->httpFailure('submit', $submit->status(), $request->modelKey);
        }

        $statusUrl = $submit->json('status_url');
        $responseUrl = $submit->json('response_url');
        $requestId = $submit->json('request_id');

        if (! $this->isQueueUrl($statusUrl) || ! $this->isQueueUrl($responseUrl) || ! is_string($requestId)) {
            return GenerationResult::failure('Fal accepted the request but returned no usable queue URLs.', $this->key(), $request->modelKey);
        }

        return GenerationResult::queued([
            'endpoint_id' => $endpoint,
            'model_key' => $request->modelKey,
            'request_id' => $requestId,
            'status_url' => $statusUrl,
            'response_url' => $responseUrl,
        ], $this->key(), $request->modelKey);
    }

    public function poll(array $job): GenerationResult
    {
        $apiKey = $this->apiKey();
        $modelKey = (string) ($job['model_key'] ?? '');

        if ($apiKey === null) {
            return GenerationResult::failure('Fal API key is not configured.', $this->key(), $modelKey);
        }

        $statusUrl = $job['status_url'] ?? null;
        $responseUrl = $job['response_url'] ?? null;
        $endpoint = (string) ($job['endpoint_id'] ?? '');

        if (! $this->isQueueUrl($statusUrl) || ! $this->isQueueUrl($responseUrl) || $endpoint === '') {
            return GenerationResult::failure('The queued fal job is missing its queue URLs.', $this->key(), $modelKey);
        }

        try {
            $status = $this->client($apiKey)->get($statusUrl);

            // A 4xx means the job itself is gone or rejected. A 5xx or a network error is retried by the completer.
            if ($status->clientError()) {
                return $this->httpFailure('status', $status->status(), $modelKey);
            }

            if (! $status->successful()) {
                return GenerationResult::queued($job, $this->key(), $modelKey);
            }

            $state = $status->json('status');

            if ($state === 'IN_QUEUE' || $state === 'IN_PROGRESS') {
                return GenerationResult::queued($job, $this->key(), $modelKey);
            }

            if ($state !== 'COMPLETED') {
                return GenerationResult::failure("Fal ended the job with status [{$state}].", $this->key(), $modelKey);
            }

            $result = $this->client($apiKey)->get($responseUrl);

            if ($result->clientError()) {
                return $this->httpFailure('result', $result->status(), $modelKey);
            }

            if (! $result->successful()) {
                return GenerationResult::queued($job, $this->key(), $modelKey);
            }

            $media = FalEndpoints::media($endpoint, $result->json() ?? []);

            if ($media === null) {
                return GenerationResult::failure('Fal finished but returned no media.', $this->key(), $modelKey);
            }

            return GenerationResult::success(
                output: $media,
                providerKey: $this->key(),
                modelKey: $modelKey,
                usage: ['request_id' => $job['request_id'] ?? null],
            );
        } catch (Throwable $e) {
            Log::warning('fal poll failed; will retry', ['endpoint' => $endpoint, 'error' => $e->getMessage()]);

            return GenerationResult::queued($job, $this->key(), $modelKey);
        }
    }

    private function isQueueUrl(mixed $url): bool
    {
        return is_string($url) && str_starts_with($url, self::QUEUE_BASE.'/');
    }

    private function client(string $apiKey): PendingRequest
    {
        return Http::withHeaders([
            'Authorization' => 'Key '.$apiKey,
            'Accept' => 'application/json',
        ])->timeout((int) config('virgoai.fal.timeout_seconds', 30))->asJson();
    }

    private function apiKey(): ?string
    {
        $key = ProviderCredentials::get('FAL_KEY');

        return is_string($key) && $key !== '' ? $key : null;
    }

    private function httpFailure(string $stage, int $status, string $modelKey): GenerationResult
    {
        return GenerationResult::failure(
            "Fal {$stage} request failed with HTTP {$status}.",
            $this->key(),
            $modelKey,
        );
    }
}
