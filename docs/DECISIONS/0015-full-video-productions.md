# DEC-0015: Full Video Productions and Exact Output Size

- **Status**: PROPOSED
- **Date**: 2026-10-16

## Context

The paste box must say what to make: a full video, an image, or audio. A chosen video type has to produce that size.

## Decision

- **Full video** is a production: the director writes a script, each scene gets a keyframe (GPT Image) and a motion
  clip (Seedance) from it, and the clips are fitted to the chosen format and joined in order (ffmpeg).
- **Reserve, then settle.** The estimate for the largest allowed scene count is reserved before any step runs.
  When the video finishes, the charge is settled to what the steps actually cost, and the rest is returned. Any
  failed step refunds everything.
- **Exact size.** Every video is fitted with ffmpeg to the format's width and height (scale to cover, centre-crop),
  so a 1080 × 1920 choice yields 1080 × 1920 files. Quality (480p/720p/1080p) is the provider's render resolution.
- **Image** opens the image tool at the chosen format. GPT Image sizes are fixed by the provider, so the closest one
  is used; the file is not resized to the exact pixel count.
- **Audio** opens the narration tool. Audio generation is not connected yet, so that tool states it is not ready.

## Consequences

- A full video is at most `max_scenes` (6) × `scene_seconds` (5) = 30 seconds today.
- Live runs need Anthropic, fal, and public media URLs; they have been tested with recorded provider responses only.
- Keyframes are fitted to the format by the provider, not resized here.
