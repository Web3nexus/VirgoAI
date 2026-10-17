<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Settings\ProviderCredentials;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

/**
 * Provider API keys, managed from the admin area. A saved key is write-only: after saving, the console
 * only ever sees its source and last four characters.
 */
class AdminCredentialController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => collect(ProviderCredentials::AI_NAMES)
                ->map(fn (string $name) => ProviderCredentials::describe($name))
                ->values(),
        ]);
    }

    public function update(Request $request, string $name): JsonResponse
    {
        $this->assertKnown($name);

        $data = $request->validate([
            'value' => ['required', 'string', 'min:12', 'max:500', 'regex:/^\S+$/'],
        ], ['value.regex' => 'The key must not contain spaces.']);

        try {
            ProviderCredentials::set($name, $data['value'], $request->user());
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['value' => $e->getMessage()]);
        }

        return response()->json(ProviderCredentials::describe($name));
    }

    /** Removes the saved key. A key set in the server environment still applies. */
    public function destroy(Request $request, string $name): JsonResponse
    {
        $this->assertKnown($name);

        ProviderCredentials::clear($name, $request->user());

        return response()->json(ProviderCredentials::describe($name));
    }

    /**
     * A cheap call that proves the key works: listing models. Returns the provider's own message on failure.
     */
    public function test(Request $request, string $name): JsonResponse
    {
        $this->assertKnown($name);
        abort_if($name === 'SMTP_PASSWORD', 422, 'Test the email server from Settings.');

        $key = ProviderCredentials::get($name);

        if ($key === null) {
            return response()->json(['ok' => false, 'message' => 'No key is set.'], 200);
        }

        try {
            $response = $name === 'ANTHROPIC_API_KEY'
                ? Http::withHeaders(['x-api-key' => $key, 'anthropic-version' => '2023-06-01'])
                    ->acceptJson()->timeout(20)->get('https://api.anthropic.com/v1/models', ['limit' => 1])
                : Http::withHeaders(['Authorization' => 'Key '.$key])
                    ->acceptJson()->timeout(20)->get('https://api.fal.ai/v1/models', ['endpoint_id' => 'fal-ai/gpt-image-1.5']);
        } catch (Throwable) {
            return response()->json(['ok' => false, 'message' => 'Could not reach the provider. Check the network.'], 200);
        }

        $ok = $response->successful();
        $message = $ok
            ? 'The key works.'
            : (string) ($response->json('error.message') ?? $response->json('detail') ?? "The provider returned HTTP {$response->status()}.");

        return response()->json(['ok' => $ok, 'message' => $message]);
    }

    private function assertKnown(string $name): void
    {
        abort_unless(ProviderCredentials::known($name), 404);
    }
}
