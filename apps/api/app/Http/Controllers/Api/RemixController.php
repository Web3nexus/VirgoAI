<?php

namespace App\Http\Controllers\Api;

use App\Billing\InsufficientCredit;
use App\Billing\Money;
use App\Billing\PriceQuoter;
use App\Billing\PriceUnavailable;
use App\Billing\UsageRecorder;
use App\Billing\Wallet;
use App\Clips\ClipPicker;
use App\Clips\Remix as RemixPrompt;
use App\Enums\Capability;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessRemix;
use App\Models\ClipSource;
use App\Models\Generation;
use App\Models\Remix;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Original stories from a source video's transcript: the transcript is read, and a new story is written.
 */
class RemixController extends Controller
{
    public function __construct(
        protected PriceQuoter $quotes,
        protected Wallet $wallet,
        protected UsageRecorder $usage,
    ) {}

    public function store(Request $request, string $source): JsonResponse
    {
        $clipSource = ClipSource::query()->where('user_id', $request->user()->id)->whereKey($source)->firstOrFail();

        if (! is_array($clipSource->transcript) || $clipSource->transcript === []) {
            throw ValidationException::withMessages(['source' => 'This video has no transcript yet. Wait for it, or add captions.']);
        }

        $data = $request->validate([
            'instructions' => ['nullable', 'string', 'max:1000'],
            'model_key' => ['nullable', 'string', Rule::in(['claude-haiku-5-5', 'claude-sonnet-5-5', 'claude-opus-5-5'])],
        ]);

        $modelKey = $data['model_key'] ?? 'claude-haiku-5-5';
        $prompt = ($data['instructions'] ?? '')."\n".ClipPicker::transcriptBlock($clipSource->transcript);

        try {
            $quote = $this->quotes->quote('anthropic', $modelKey, Capability::Text, $prompt, ['system' => RemixPrompt::systemPrompt()], []);
        } catch (PriceUnavailable $e) {
            throw ValidationException::withMessages(['model_key' => $e->getMessage()]);
        }

        try {
            $generation = $this->wallet->reserveRun($request->user(), $quote, [
                'user_id' => $request->user()->id,
                'tool' => 'remix',
                'capability' => Capability::Text->value,
                'provider_key' => 'anthropic',
                'model_key' => $modelKey,
                'prompt' => 'Original story from a video transcript',
                'options' => ['instructions' => $data['instructions'] ?? null],
                'output_type' => 'story',
            ]);
        } catch (InsufficientCredit $e) {
            return response()->json(['message' => $e->getMessage()], 402);
        }

        $remix = Remix::create([
            'user_id' => $request->user()->id,
            'clip_source_id' => $clipSource->id,
            'generation_id' => $generation->id,
            'model_key' => $modelKey,
            'status' => Remix::STATUS_QUEUED,
            'instructions' => $data['instructions'] ?? null,
        ]);

        ProcessRemix::dispatch($remix->id);

        return response()->json($this->present($remix->refresh()), 201);
    }

    public function show(Request $request, string $remix): JsonResponse
    {
        $record = Remix::query()->where('user_id', $request->user()->id)->whereKey($remix)->firstOrFail();

        return response()->json($this->present($record));
    }

    /** @return array<string, mixed> */
    private function present(Remix $remix): array
    {
        $generation = $remix->generation_id ? Generation::query()->find($remix->generation_id) : null;

        return [
            'id' => $remix->id,
            'source_id' => $remix->clip_source_id,
            'status' => $remix->status,
            'title' => $remix->title,
            'logline' => $remix->logline,
            'story' => $remix->story,
            'error' => $remix->error,
            // Shown as "original" only once the copy check has passed.
            'original' => $remix->status === Remix::STATUS_SUCCEEDED && (int) $remix->shared_runs === 0,
            'model_key' => $remix->model_key,
            'usage' => $this->usage->userSummary(UsageRecorder::TYPE_REMIX, $remix->id),
            'charged_credits' => $generation ? Money::toUsd((int) $generation->price_micros) : null,
            'created_at' => $remix->created_at?->toIso8601String(),
        ];
    }
}
