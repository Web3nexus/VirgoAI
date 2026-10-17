<?php

namespace App\Jobs;

use App\Ai\Data\GenerationRequest;
use App\Ai\GenerationService;
use App\Billing\PriceQuoter;
use App\Billing\UsageRecorder;
use App\Billing\Wallet;
use App\Clips\ClipPicker;
use App\Clips\Originality;
use App\Clips\Remix;
use App\Enums\Capability;
use App\Models\Generation;
use App\Models\Remix as RemixModel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Write an original story from a transcript. If the first draft copies too much, one more attempt is made with
 * a stricter instruction. Still copied: nothing is kept and the charge is refunded.
 */
class ProcessRemix implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(public readonly string $remixId) {}

    public function handle(GenerationService $generations, PriceQuoter $quotes, Wallet $wallet, UsageRecorder $usage): void
    {
        $remix = RemixModel::query()->with('source')->find($this->remixId);

        if ($remix === null || $remix->status !== RemixModel::STATUS_QUEUED) {
            return;
        }

        $remix->forceFill(['status' => RemixModel::STATUS_RUNNING])->save();
        $generation = Generation::query()->find($remix->generation_id);
        $source = (string) collect($remix->source->transcript ?? [])->pluck('text')->implode(' ');

        try {
            $strict = false;
            $kept = null;

            for ($attempt = 0; $attempt < 2 && $kept === null; $attempt++) {
                $prompt = self::prompt($remix, $strict);
                $result = $generations->generate(new GenerationRequest(
                    capability: Capability::Text,
                    modelKey: '',
                    prompt: $prompt,
                    options: ['system' => Remix::systemPrompt()],
                    requestedModelKey: $remix->model_key,
                ));

                if (! $result->ok) {
                    throw new \DomainException($result->error ?? 'The model did not answer.');
                }

                $actual = $quotes->settleText('anthropic', $remix->model_key, $result->usage);
                if ($actual !== null) {
                    $wallet->settle($generation, $actual);
                    $usage->record($remix->user, UsageRecorder::TYPE_REMIX, $remix->id, 'rewrite', 'anthropic', $remix->model_key, $actual, [
                        'input' => (int) ($result->usage['input_tokens'] ?? 0),
                        'output' => (int) ($result->usage['output_tokens'] ?? 0),
                        'units' => 1,
                        'unit_label' => 'rewrite',
                    ]);
                }

                $story = Remix::parseReply((string) ($result->output['text'] ?? ''));
                if ($story === null) {
                    throw new \DomainException('The model did not return a story in the expected form. Try again.');
                }

                $shared = Originality::sharedRuns($story['story'].' '.$story['logline'].' '.$story['title'], $source);
                $remix->forceFill(['shared_runs' => $shared])->save();

                if ($shared === 0) {
                    $kept = $story;
                } else {
                    $strict = true;
                }
            }

            if ($kept === null) {
                throw new \DomainException('The rewrite kept reusing the source wording, so it was not saved. The charge was refunded.');
            }

            $remix->forceFill([
                'status' => RemixModel::STATUS_SUCCEEDED,
                'title' => $kept['title'],
                'logline' => $kept['logline'],
                'story' => $kept['story'],
                'error' => null,
            ])->save();
        } catch (Throwable $e) {
            if ($generation !== null) {
                $wallet->refund($generation, 'Refunded: rewrite did not finish');
                $generation->forceFill(['status' => Generation::STATUS_FAILED, 'error' => $e->getMessage()])->save();
            }

            $remix->forceFill(['status' => RemixModel::STATUS_FAILED, 'error' => $e->getMessage()])->save();
        }
    }

    private static function prompt(RemixModel $remix, bool $strict): string
    {
        $instructions = trim((string) $remix->instructions);
        $extra = $strict
            ? "\nYour previous draft reused wording from the transcript. Start over and write every sentence fresh."
            : '';

        return ($instructions !== '' ? "Direction from the user: {$instructions}\n\n" : '')
            ."Transcript:\n".ClipPicker::transcriptBlock($remix->source->transcript ?? []).$extra;
    }
}
