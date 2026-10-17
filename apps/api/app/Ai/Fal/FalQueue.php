<?php

namespace App\Ai\Fal;

use App\Settings\ProviderCredentials;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Submit a job to a fal endpoint and read it back later. Used where the result is data (a transcript),
 * not a media file. The key is sent only in the Authorization header.
 */
class FalQueue
{
    private const BASE = 'https://queue.fal.run';

    /**
     * @param  array<string, mixed>  $payload
     * @return array{endpoint_id: string, request_id: string, status_url: string, response_url: string}
     */
    public function submit(string $endpointId, array $payload): array
    {
        $response = $this->client()->post(self::BASE.'/'.$endpointId, $payload);

        $statusUrl = $response->json('status_url');
        $responseUrl = $response->json('response_url');

        if (! $response->successful() || ! is_string($statusUrl) || ! is_string($responseUrl)) {
            throw new RuntimeException('fal did not accept the job: HTTP '.$response->status());
        }

        return [
            'endpoint_id' => $endpointId,
            'request_id' => (string) $response->json('request_id'),
            'status_url' => $statusUrl,
            'response_url' => $responseUrl,
        ];
    }

    /**
     * @param  array{status_url: string, response_url: string}  $job
     * @return array{state: string, body: array<string, mixed>|null}
     */
    public function check(array $job): array
    {
        $status = $this->client()->get($job['status_url']);

        if ($status->clientError()) {
            throw new RuntimeException('fal rejected the job status: HTTP '.$status->status());
        }

        $state = (string) $status->json('status');

        if ($state !== 'COMPLETED') {
            return ['state' => $state, 'body' => null];
        }

        $result = $this->client()->get($job['response_url']);

        if (! $result->successful()) {
            return ['state' => 'PENDING_RESULT', 'body' => null];
        }

        return ['state' => 'COMPLETED', 'body' => (array) $result->json()];
    }

    private function client()
    {
        $key = ProviderCredentials::get('FAL_KEY');

        if ($key === null) {
            throw new RuntimeException('FAL_KEY is not set. Add it under API keys.');
        }

        return Http::withHeaders(['Authorization' => 'Key '.$key])->acceptJson()->asJson()->timeout(30);
    }
}
