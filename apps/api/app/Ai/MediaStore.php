<?php

namespace App\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Copies a finished provider file onto our public disk, so a stored run keeps working after the
 * provider's temporary link expires.
 *
 * Only https URLs on the provider hosts are fetched. The URL comes from a provider response, so it is
 * never trusted as a destination for arbitrary requests. The body is streamed, not loaded into memory.
 */
class MediaStore
{
    /** Host suffixes the provider serves finished media from. */
    private const ALLOWED_HOST_SUFFIXES = ['.fal.media', '.fal.run', '.googleapis.com'];

    public function store(string $url, string $path): bool
    {
        if (! $this->isAllowed($url)) {
            return false;
        }

        try {
            $response = Http::timeout((int) config('virgoai.media.timeout_seconds', 600))
                ->withOptions(['stream' => true])
                ->get($url);

            if (! $response->successful()) {
                return false;
            }

            $maxBytes = (int) config('virgoai.media.max_bytes', 524288000);
            $declared = (int) $response->header('Content-Length');

            if ($declared > $maxBytes) {
                return false;
            }

            $body = $response->toPsrResponse()->getBody();
            $stream = $body->detach();

            if (! is_resource($stream)) {
                return false;
            }

            $written = Storage::disk('public')->writeStream($path, $stream);

            if (is_resource($stream)) {
                fclose($stream);
            }

            return $written;
        } catch (Throwable) {
            return false;
        }
    }

    private function isAllowed(string $url): bool
    {
        $parts = parse_url($url);
        $host = $parts['host'] ?? '';

        if (($parts['scheme'] ?? '') !== 'https' || $host === '') {
            return false;
        }

        foreach (self::ALLOWED_HOST_SUFFIXES as $suffix) {
            if ($host === ltrim($suffix, '.') || str_ends_with($host, $suffix)) {
                return true;
            }
        }

        return false;
    }
}
