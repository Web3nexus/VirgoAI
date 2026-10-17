<?php

namespace App\Http\Controllers\Api;

use App\Ai\Exceptions\NoSuitableModel;
use App\Ai\Fal\FalEndpoints;
use App\Ai\ModelRouter;
use App\Billing\Money;
use App\Billing\PriceQuoter;
use App\Billing\PriceUnavailable;
use App\Billing\Quote;
use App\Clips\ClipService;
use App\Clips\LocalTranscriber;
use App\Enums\Capability;
use App\Http\Controllers\Controller;
use App\Models\ClipSource;
use App\Tools\Skills\DirectorSkill;
use App\Tools\ToolCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The price of an action, before the user presses it. The same pricing the wallet charges, so the number
 * shown and the number charged cannot disagree.
 */
class QuoteController extends Controller
{
    public function __construct(
        protected PriceQuoter $quotes,
        protected ModelRouter $router,
    ) {}

    public function quote(Request $request): JsonResponse
    {
        $data = $request->validate([
            'kind' => ['required', 'string', Rule::in(['tool', 'clips', 'transcribe'])],
            'tool' => ['required_if:kind,tool', 'nullable', 'string', Rule::in(array_keys(ToolCatalog::TOOLS))],
            'model_key' => ['nullable', 'string', 'max:128'],
            'prompt' => ['nullable', 'string', 'max:4000'],
            'size' => ['nullable', 'string'],
            'duration' => ['nullable', 'integer', 'between:4,15'],
            'source_id' => ['required_if:kind,clips,transcribe', 'nullable', 'string', 'size:26'],
            'request' => ['nullable', 'string', 'max:1000'],
        ]);

        $user = $request->user();

        try {
            $quote = match ($data['kind']) {
                'tool' => $this->tool($data),
                'clips' => $this->clips($request, $data),
                'transcribe' => $this->transcribe($request, $data),
            };
        } catch (PriceUnavailable|NoSuitableModel $e) {
            return response()->json([
                'affordable' => false,
                'price_usd' => null,
                'tokens' => null,
                'balance_tokens' => Money::toTokens((int) $user->balance_micros),
                'message' => $e->getMessage(),
            ]);
        }

        return response()->json([
            ...$quote,
            'tokens' => Money::toTokens($quote['price_micros']),
            'balance_tokens' => Money::toTokens((int) $user->balance_micros),
            'affordable' => (int) $user->balance_micros >= $quote['price_micros'],
            'message' => null,
        ]);
    }

    /** @return array<string, mixed> */
    private function tool(array $data): array
    {
        $definition = ToolCatalog::get($data['tool']);
        $capability = $definition['capability'];

        $modelKey = $data['model_key'] ?? $this->router->route($capability)->modelKey();
        $routed = $this->router->routeTo($capability, $modelKey);

        $options = [];
        if ($definition['sizes'] && isset($data['size'])) {
            $options['size'] = $data['size'];
        }
        if (isset($data['duration'])) {
            $options['duration'] = $data['duration'];
        }
        if (isset($definition['choices'])) {
            $options['system'] = DirectorSkill::systemPrompt('director', 'story', config('virgoai.voices', [])) ?? '';
        }

        $quote = $this->quotes->quote($routed->providerKey(), $modelKey, $capability, $data['prompt'] ?? 'x', $options, []);

        return $this->present($quote, $capability, $modelKey);
    }

    /** @return array<string, mixed> */
    private function clips(Request $request, array $data): array
    {
        $source = ClipSource::query()->where('user_id', $request->user()->id)->whereKey($data['source_id'])->firstOrFail();
        $modelKey = $data['model_key'] ?? ClipService::TEXT_MODEL_DEFAULT;
        $prompt = app(ClipService::class)->analysisPrompt($source, (string) ($data['request'] ?? 'moments'));

        $quote = $this->quotes->quote('anthropic', $modelKey, Capability::Text, $prompt, ['system' => 'clip'], []);

        return $this->present($quote, Capability::Text, $modelKey);
    }

    /** @return array<string, mixed> */
    private function transcribe(Request $request, array $data): array
    {
        $source = ClipSource::query()->where('user_id', $request->user()->id)->whereKey($data['source_id'])->firstOrFail();

        // Our own engine: no API charge.
        if (config('virgoai.clips.transcriber') === 'local' && LocalTranscriber::fromConfig()->available()) {
            return [
                'price_micros' => 0,
                'price_usd' => 0.0,
                'unit' => 'local transcript',
                'quantity' => 1,
                'estimate' => false,
                'label' => 'Free: transcribed on our engine',
            ];
        }

        $quote = $this->quotes->quote('fal', FalEndpoints::TRANSCRIBE, Capability::Audio, 'transcribe', ['duration' => (float) $source->duration_seconds], []);

        return $this->present($quote, Capability::Audio, FalEndpoints::TRANSCRIBE);
    }

    /** @return array<string, mixed> */
    private function present(Quote $quote, Capability $capability, string $modelKey): array
    {
        return [
            'price_micros' => $quote->priceMicros,
            'price_usd' => Money::toUsd($quote->priceMicros),
            'unit' => $quote->unit,
            'quantity' => $quote->quantity,
            'estimate' => $quote->estimate,
            'label' => $this->label($quote, $capability, $modelKey),
        ];
    }

    private function label(Quote $quote, Capability $capability, string $modelKey): string
    {
        if ($quote->priceMicros === 0) {
            return 'Free';
        }

        $tokens = number_format(Money::toTokens($quote->priceMicros), 1);

        return $tokens.' tokens'.match ($quote->unit) {
            'second' => ' for '.(int) $quote->quantity.' s of video',
            'video' => ' per video',
            'image' => ' per image',
            'tokens' => $quote->estimate ? ' at most, for this request' : ' for this request',
            default => ' for this request',
        };
    }
}
