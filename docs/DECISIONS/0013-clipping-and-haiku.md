# DEC-0013: Long-Video Clipping and Claude Haiku 5.5

- **Status**: PROPOSED
- **Date**: 2026-10-16

## Context

Videos run from minutes to hours. Users want clips from them on request. Haiku 5.5 is the cheapest Claude
model that can do the selection, and it has tiered pricing that must be charged correctly.

## Decision

- **Haiku 5.5 is enabled** at its published prices. Up to 100,000 input tokens: $0.10 in, $0.50 out per
  million. Above that: $0.50 in, $2.50 out. Prices are seeded from config and refreshed by `ai:sync`.
- **Default clip model is Haiku 5.5.** The user can pick Sonnet 5.5 or Opus 5.5 per request.
- **Pipeline for uploads**: probe with ffprobe, extract mono 16 kHz audio with ffmpeg, transcribe with
  fal Whisper (`fal-ai/whisper`, `chunk_level: segment`), ask Claude for clips from the timestamped
  transcript, validate every clip, and cut each one with ffmpeg (re-encoded, so cuts land on exact frames).
- **Billing**: transcription is charged per second of audio at fal's price. Analysis is quoted from the
  real transcript size and settled to tokens used. A request that finds no clip is refunded.
- **YouTube** links are played in full through YouTube's embed player. They are never downloaded, because
  downloading breaks YouTube's terms. A YouTube source cannot be clipped; upload a copy to clip it.

## Consequences

- The public audio file for Whisper must be reachable by fal over HTTPS. A local server cannot be
  transcribed by fal until it is publicly reachable. Production needs a public APP_URL.
- Whisper's fal unit is not yet confirmed. Transcription refuses to run until fal's price for it uses a unit
  this app can bill (a per-second unit). The first live sync will show it.
- Large uploads need `upload_max_filesize` and `post_max_size` raised in php.ini.
- Clip analysis sends the full transcript to Claude. Prompts over 100,000 tokens use the higher tier, and
  the quote shows it before the run starts.
