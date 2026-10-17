# DEC-0014: Original Rewrites, Transcription on Our Engine, and the Usage Ledger

- **Status**: PROPOSED
- **Date**: 2026-10-16

## Context

A source video is read, not copied. Its story is rewritten as an original, and that story drives the script and
the characters. Every step's tokens are recorded, so a finished production shows its total.

## Decision

- **Transcription runs on our own engine** (whisper.cpp, open source, local). No API key and no per-minute
  charge. A transcript from captions (SRT/VTT) is also accepted, which is how a YouTube video gets a transcript
  without a download.
- **Original rewrite**: the model reads the transcript and writes a new story with the same shape in new words.
  A copy guard rejects any draft that shares an eight-word run with the source. One stricter retry is made.
  A second copy is refused, nothing is kept, and the charge is refunded.
- **Usage ledger**: every step (transcription, rewrite, clip analysis, each generation) writes a `usage_events`
  row with input and output tokens, units, provider cost, and the user's price. Users see tokens and credits per
  step and in total. Cost and the margin kept are returned only to the owner.
- **Price before action**: every action's price is quoted from the same pricing the wallet charges. A run the
  balance cannot cover is not started.

## Consequences

- The owner's per-production view is an API (`/admin/usage/{type}/{id}`). A page for it is still to build.
- Transcription on the local engine is free to users, so its usage shows zero credits while its tokens and
  audio seconds are recorded.
- The rewrite price is quoted from the transcript; the cost shown beside the rewrite button reuses the clip-analysis quote.
