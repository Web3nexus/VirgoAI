# 0020 — Director Lab: Long-Form Video on Verified fal Limits

- **Status:** PROPOSED
- **Date:** 2026-10-30
- **Decision record:** DEC-0020 in [`../DECISION_REGISTER.md`](../DECISION_REGISTER.md)

## Context

Users want finished videos of one to three minutes without editing. The current full-video pipeline
(`ProductionRunner`) is capped at six scenes of five seconds each, which is 30 seconds at most. That
cap comes from our own config (`virgoai.productions.max_scenes`, `scene_seconds`), not from fal.

The fal limits, checked against each model's published input schema on 2026-10-30:

| Model (fal endpoint) | Per-call duration | Character / reference inputs | Audio | Price (per second of video) |
|---|---|---|---|---|
| `bytedance/seedance-2.0/reference-to-video` | 4–15 s, or `auto` | `image_urls` up to 9 images, `video_urls` up to 3, `audio_urls` up to 3; 12 files total; referenced in the prompt as `@Image1`… | Native, with lip-synced speech (`generate_audio`) | Billed per token: about $0.014 per 1k tokens at 720p, roughly $0.30/s |
| `bytedance/seedance-2.0/image-to-video` | 4–15 s, or `auto` | One start frame, plus optional `end_image_url` | Native (`generate_audio`) | $0.3034/s at 720p; $0.682/s at 1080p |
| `fal-ai/kling-video/v3/standard/image-to-video` | 3–15 s, 1 s steps | `elements` (a frontal image plus reference images per character, referenced as `@Element1`); `multi_prompt` for several shots in one call | Native (`generate_audio`) | $0.084/s without audio; $0.126/s with audio |
| `fal-ai/kling-video/v3/pro/image-to-video` | 3–15 s, 1 s steps | Same as standard | Native | $0.112/s without audio; $0.168/s with audio |
| `fal-ai/veo3.1/fast/image-to-video` | 4, 6 or 8 s only | One start image | Native | $0.10/s without audio; $0.15/s with audio |
| `fal-ai/minimax/hailuo-02/standard/image-to-video` | 6 or 10 s | One start image | No | $0.045/s at 768P |

Notes:

- **The largest single call is 15 seconds** (Seedance and Kling v3). A minute of video is at least four
  calls, and three minutes is at least twelve. Any product that promises minutes must chain calls.
- Seedance 2.0 reference-to-video is the only endpoint in the catalogue that takes several cast
  reference images in one call. That makes it the right base for character consistency.
- Kling v3 is the cheaper option with a real character mechanism (`elements`) and in-call multi-shot
  (`multi_prompt`). It has no documented resolution options on its page, so 1080p is not assumed.
- Veo 3.1 cannot make a clip longer than eight seconds, so it is excluded from long-form. Hailuo caps at
  ten seconds and has no audio, so it is excluded too.

## Decision

1. **Default engine for long-form: Seedance 2.0 reference-to-video.** Each cast member's reference
   sheet (front, three-quarter and full body) is passed as `image_urls` in the same order for every
   take. Its voice and dialogue come from `generate_audio`.
2. **Cheaper engine: Kling v3 standard** with `elements` for cast and `multi_prompt` for shots. Offered
   as a selectable engine in the Director Lab, not the default.
3. **Take = one call, 4–15 seconds.** A shot list is grouped into takes that each fit one call. A film of
   N seconds is planned as ceil(N / 15) or more takes.
4. **Continuity:** within one scene, each take starts from the last frame of the take before it. That
   frame is extracted with ffmpeg. Independent scenes render in parallel.
5. **Veo 3 and Hailuo are not used for long-form.** Veo 3 stays selectable for short clips only, pending
   the owner's decision on whether to remove it from the picker.
6. **The user sets a length, not a scene count.** Targets: 15, 30, 60, 120 and 180 seconds in v1. The
   Director Lab writes the story, cast, locations and shot list, then renders. Review checkpoints are
   optional. Auto mode runs straight through to a finished file.
7. **Quote before start.** The quote is built from the take list at the chosen engine and quality, then
   reserved in the wallet and settled to actual cost. Nothing renders before the user has seen the price.

## Build plan (not started)

- **Data:** `cast_members` (reference images, locked flag), `locations`, `shots` (duration, camera, cast,
  location, action, dialogue line and speaker), `takes` (shot ids, status, job, output, last-frame path).
  Productions reference these; the existing `productions` table keeps billing.
- **Runner:** generalise `ProductionRunner` from fixed scenes to takes. Keep the tick pattern and the
  reservation. Raise the wait limit to fit a three-minute film.
- **Lab UI:** brief → story → cast (sheets) → locations → shot list (duration per shot, a running total)
  → render with progress per take → finished film. Regenerate one take, edit one shot, lock one cast
  member.
- **Checks before paid calls:** take durations within 4–15 s; cast references present; total length within
  the chosen target; quote within the user's balance.

## Consequences

- Minute-long output costs real money. At 720p, one minute on Seedance is about $18 and on Kling v3
  standard with audio about $7.60, before images and LLM steps. Those figures go in front of the user
  before any render.
- Seedance reference-to-video billing is per token and its page gives no per-second rate. The token
  formula must be confirmed by a live call before pricing is shown as final (DEC-0009 rule: no provider
  claim before a verified run).
- Continuity and character consistency are unproven until a live multi-take test is run. The build
  must include that test before the lab is called working.
- Speech language and voice matching across takes is limited by each model's native audio. Kling notes
  Chinese and English speech only; Seedance's language coverage is not listed on the page checked.
