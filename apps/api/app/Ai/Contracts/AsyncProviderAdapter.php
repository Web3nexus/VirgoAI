<?php

namespace App\Ai\Contracts;

use App\Ai\Data\GenerationResult;

/**
 * An adapter whose work runs as a provider-side job (image or video that takes seconds to minutes).
 *
 * generate() submits the job and returns GenerationResult::queued() with the job handle. The
 * GenerationCompleter calls poll() on a schedule until the job finishes, so no web request waits.
 */
interface AsyncProviderAdapter extends ProviderAdapter
{
    /**
     * Check a queued job. Returns queued() while it is still running, success() with the output
     * when it finished, or failure() when the provider rejected it.
     *
     * @param  array<string, mixed>  $job
     */
    public function poll(array $job): GenerationResult;
}
