# Generation pipeline hardening

Four gaps between virgoAi's generation path and a production node-based pipeline (Fal.ai backed).
Each item lists what the code does today, what changes, and how it is verified.

## Findings (checked in code)

- `App\Ai\GenerationService::generate()` routes the capability, writes an `AiUsageRecord`, and calls the adapter. Queued jobs are closed later by `GenerationCompleter`.
- `ai_usage_records.idempotency_key` exists with a **unique** index, but no caller sets it (`GenerationRequest::$idempotencyKey` defaults to null) and nothing reads it. Reuse is not implemented.
- Fal results are **polled**: `App\Ai\Fal\FalQueue` submits and checks, and `App\Jobs\PollGeneration` drives the polling. There is no Fal webhook route.
- `App\Ai\Adapters\FalAdapter` already retries.
- Character and location sheets exist per film (`App\Films\FilmRunner`). They are not a shared, global lock across pipelines.
- Two pipelines exist: `App\Workflows` (DAG with `TaskDependency`) and `App\Films` / `App\Productions` (their own runners). A change-propagation feature has to pick one.

## 1. Prompt validator per Fal endpoint — done

**Status:** implemented. `App\Ai\Fal\FalInputValidator` runs in `FalAdapter::generate()` before the payload is built, so a refused request makes no HTTP call and records no charge.

What it refuses, because `FalEndpoints::payload()` would otherwise change the value silently:
- Veo duration other than 4, 6 or 8. Hailuo duration other than 6 or 10.
- Seedance duration outside `auto` or 4–15. Kling outside 3–15.
- Veo resolution other than 720p or 1080p. Hailuo resolution other than 512p or 720p.
- A frame size that is not a `Project::FORMATS` key.
- `generate_audio` that is not a real boolean (`'false'` used to become `true`).

Not covered: image quality values, and Seedance resolution. The allowed sets for those are not encoded in the builder, so the validator does not guess them. Adding them needs the values from fal's schema.

Verified: `FalInputValidatorTest` (boundary cases per endpoint) and an updated `FalAdapterTest` case that asserts a refusal with `Http::assertNothingSent()`. Full API suite: 399 passing.

## 2. Input-hash reuse (cache)

**Blocked on a decision (see below).** Shape:

- Hash the normalised input: capability, model key, prompt, options, reference URLs.
- Store the hash on the usage record and make it non-unique per attempt. The current unique index blocks a second run of the same input.
- Add `generation_id` (or output path) to the usage record, so a hit can return the prior output without a provider call and without a charge.
- Verify: Pest test that a second identical request returns the stored output and records no cost; a test that a changed prompt misses.

## 3. Fal webhook callbacks

**Needs an environment:** a public HTTPS URL for the webhook and a Fal key.

- Add a route that verifies Fal's webhook signature, then dispatches the same completion path `PollGeneration` uses.
- Keep polling as the fallback, so a missed webhook still completes.
- Verify: signature-check unit tests and a replayed webhook payload test. **End-to-end is unverified until a live Fal call completes through the webhook**, per CLAUDE.md.

## 4. Stale-node propagation

**Needs a pipeline choice.** Shape, for `App\Workflows`:

- Add a `stale` state on `WorkflowTask`. Editing an upstream output marks its downstream tasks stale.
- `WorkflowEngine::advance()` re-runs only stale tasks and leaves settled, unaffected nodes alone.
- Verify: Pest test on a three-node chain where changing the middle node leaves the first untouched and re-runs the last two.

## Decisions needed

1. **Cache semantics:** should an identical request reuse the earlier output for free (faster, cheaper, but a user who wants a fresh render must opt out), or always pay? Recommend reuse with an explicit "regenerate" action.
2. **Which pipeline** gets stale propagation: `Workflows` (DAG) or `Films`/`Productions`?
3. **Webhook URL:** what public HTTPS host will receive Fal callbacks in production and in local testing?

## Order

1 (validator) → 2 (cache, after decision 1) → 3 (webhook, after decision 3) → 4 (stale, after decision 2).

## Output quality (blur)

Checked against fal's public OpenAPI schemas (Seedance 2.0 standard, Seedance fast, Seedance reference, GPT Image 1.5 and its edit endpoint, Kling v3, Veo 3.1 fast, Hailuo 02, Nano Banana 2).

Causes found:
- Productions, films, the tool page and quick start defaulted to 720p. Seedance's standard model supports 1080p and 4k; the fast tier stops at 720p. The 720p output was then stretched to the 1080 frame.
- Keyframes were sent at GPT Image `medium`. The schema default is `high`. A medium keyframe is the image that gets stretched into the video.
- The `bitrate_mode: "high"` option exists on Seedance but the app never sends it. Its price is not in the rate table, so it is not enabled.

Changes:
- Keyframe quality defaults to `high` (`FalEndpoints::DEFAULT_IMAGE_QUALITY`) in both the payload and the rate lookup, so the price matches what is sent.
- Productions, films, the tool page and quick start default to 1080p.
- `FalInputValidator` enforces the schema sets: image quality low/medium/high, Seedance 480p/720p/1080p/4k, Seedance fast 480p/720p.
- The ffmpeg frame fit uses Lanczos scaling.

Cost effect (from `config/pricing.php`): Seedance 1080p is $0.682/s against $0.3034/s at 720p. A high-quality keyframe is $0.200 against $0.051 at medium (vertical or landscape). Quotes show the new price before a run starts.

Not verified: a live render. The changes are supported by the schemas and the rate table, but blur is judged by looking at a real output. Hailuo now refuses 1080p, where it used to send 512P quietly.

## Resolution default (revised)

- Default is **720p**, with **1080p** selectable. Nothing below 720p is offered or accepted (the tool endpoint used to accept 480p).
- Buzzy's default: its Seedance 2.5 settings panel shows 720p selected, and its text-to-video guide says resolution is set before generating. Its 1080p maximum is cited in its guide. The 4K claims conflict across sources and come from promotional posts, so 4K is not offered.
- This replaces the 1080p default set in the previous section.
