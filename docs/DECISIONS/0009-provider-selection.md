# DEC-0009: Provider Selection for Text, Image, and Video

- **Status**: PROPOSED
- **Date**: 2026-10-16
- **Author**: Claude Code

## Context

DEC-0008 defines the pipeline but names no providers. Claude alone cannot make images or video.
The owner's direction: Claude (or GPT) for story and direction, GPT Image for images, Seedance for
video, and an aggregator if it simplifies integration. Higgsfield is excluded by the owner's
earlier instruction (no Higgsfield integration). Later the owner asked for fal only, for all three.

## Decision

| Capability | Provider | Route | Model (verify ID before registering) |
| --- | --- | --- | --- |
| Text: director, story, script, prompts, review | Anthropic direct | Claude Messages API | Claude Sonnet 5.5 (`claude-sonnet-5-5`) for director work; Opus 5.5 (`claude-opus-5-5`) for final review |
| Image: character and environment refs, keyframes, thumbnail | fal | `fal-ai/gpt-image-1.5` (text-to-image; edit endpoint for reference images) | GPT Image 1.5 (not `gpt-image-1`, which retires in October 2026) |
| Video: image-to-video with native audio and lip-sync | fal | `bytedance/seedance-2.0/image-to-video` | Seedance 2.0, 720p or 1080p, 4–15 s |

**Two providers.** Text goes to Anthropic direct, so the director runs on the model's own API with
its newest model IDs. Images and video go through fal.ai, which hosts GPT Image 1.5 and Seedance 2.0
behind one key. Using fal for images and video keeps the media side to one client.

**Higgsfield: rejected** under the owner's instruction, even though its API now lists Seedance.

**Voice:** Seedance 2.0 generates synchronized speech and lip-sync natively, so the open-source TTS
shortlist from DEC-0008 is needed only for narration with no on-screen speaker. Dialogue uses
Seedance audio first. Per-character voice consistency across separately generated clips is
unverified and is the main risk to test.

## Consequences

- Two keys are needed: `ANTHROPIC_API_KEY` and `FAL_KEY`.
- Estimated costs, to confirm on fal's model pages before release: GPT Image 1.5 about $0.034 per
  1024 square image (medium); Seedance 2.0 about $0.30 per second at 720p (audio included), so a
  10 s clip is about $3. Claude text pricing on fal is not yet confirmed.
- Each adapter is unverified until its live workflow runs (CLAUDE.md rule). Lip-sync is not a
  separate stage; it comes from the video provider.
- DEC-0008 open questions for first image and video provider are answered by this record. Voice
  engine and reviewer model stay open.

## Implementation status (2026-10-16)

- Built: `AnthropicAdapter` (text, `POST /v1/messages`), `FalAdapter` (image and video, queued),
  `AnthropicModelSync` (reads `GET /v1/models` with the owner's key, active models only),
  `FalModelSync` (checks each endpoint against fal's live OpenAPI schema before registering it), and
  `php artisan ai:sync`, which runs both syncs and exits non-zero when a check fails.
- Queued renders: `PollGeneration` re-checks each job every `FAL_POLL_INTERVAL_SECONDS` (default 10),
  saves the finished file to the public disk, and fails a job after `GENERATION_MAX_WAIT_SECONDS`.
- Keys are read from `ANTHROPIC_API_KEY` and `FAL_KEY` only. Nothing is stored in the database.
- Tested: 140 API tests with recorded request and response shapes. Not yet run against the live
  APIs. Each provider stays unverified until a live run passes.

## Sources consulted

- Anthropic Messages API: https://docs.anthropic.com/en/api/messages
- Anthropic Models list: https://docs.anthropic.com/en/api/models-list
- fal Seedance 2.0 image-to-video model page: https://fal.ai/models/bytedance/seedance-2.0/image-to-video/llms.txt
- fal GPT Image model page: https://fal.ai/models/fal-ai/gpt-image-1/text-to-image/llms.txt (1.5 pricing from third-party listings)
- fal Seedance 2.0 pricing and availability: https://fal.ai/learn/tools/best-image-to-video-apis-2026
- BytePlus ModelArk docs: https://docs.byteplus.com/docs/ModelArk/1544106
- OpenAI image model pricing (third-party summaries; confirm on OpenAI pricing page): https://pricepertoken.com/gpt-image-pricing
- Higgsfield API status (excluded regardless): https://docs.higgsfield.ai/docs/changelog.md
