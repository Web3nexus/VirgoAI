# virgoAi Decision Register

This register records critical technical, product, and architecture decisions made across the virgoAi project.
Newest decisions are appended with the next sequential `DEC-XXXX` id. Each entry links to a detailed
record in [`DECISIONS/`](./DECISIONS).

---

## DEC-0001: Project Bootstrap — Agent Skill & Documentation Scaffolding

- **Status**: APPROVED
- **Date**: 2026-10-14
- **Context**: virgoAi starts as an empty repository. We need a consistent, reproducible way to
  provision agent skills across tools (Claude Code, OpenCode, Codex) and a lightweight home for
  decision records and reverse-engineered web UI work.
- **Decision**:
  - Manage agent skills with the `skills` CLI (`npx skills`). `skills-lock.json` at the repo root is
    the source of truth; installed skill folders under `.claude/skills/` and `.agents/skills/` are
    derived artifacts and git-ignored.
  - Install `frontend-design`, `web-design-guidelines`, and `data-analysis` from `bytedance/deer-flow`.
  - Record decisions in `docs/DECISION_REGISTER.md` with per-decision files in `docs/DECISIONS/`.
  - Reserve `web-clone/` for mirrored / cloned web UI work.
- **Consequences**:
  - Environment can be reproduced on another machine with `npx skills experimental_install`.
  - Skill folders must not be edited by hand; changes flow back through the CLI / lockfile.
  - Detailed rationale: [`DECISIONS/0001-initial-project-setup.md`](./DECISIONS/0001-initial-project-setup.md).

---

## DEC-0002: OpenArt Deep Research & Full-Platform Web Clone Architecture

- **Status**: APPROVED
- **Date**: 2026-10-14
- **Context**: Need a comprehensive deep research document and full reverse-engineered clone of OpenArt (both internal and external pages and all modals).
- **Decision**: Deliver exhaustive architectural blueprint in `docs/OPENART_DEEP_RESEARCH.md` and complete interactive standalone web application in `web-clone/index.html` with client-side routing across 11 internal/external views, 9 functional modals, live mock generation engine, and Deerflow attribution.
- **Consequences**: Self-contained, highly interactive replica available locally in `web-clone/`. Rationale: [`DECISIONS/0002-openart-clone-architecture.md`](./DECISIONS/0002-openart-clone-architecture.md).

---

## DEC-0003: Full-Scale Development Prompt — Stack, Architecture & Design Direction

- **Status**: APPROVED
- **Date**: 2026-10-15
- **Context**: The raw spec (`prompt.md`), reference research (`docs/OPENART_DEEP_RESEARCH.md`), and
  design clone (`web-clone/`) exist, but implementation has not started and there is no single
  authoritative build brief.
- **Decision**: Lock the stack (React + TS + Vite + Tailwind + shadcn/ui · Laravel + queues/Horizon ·
  PostgreSQL · Redis · FFmpeg · S3) and monorepo layout (`apps/api`, `apps/web`, `packages/contracts`).
  Build a first-party durable workflow engine, provider adapter layer, DB-backed model registry, and
  Model Router. Adopt the `web-clone/` dark-neon studio aesthetic as design direction (original
  virgoAi branding; no OpenArt IP). Author the master brief at
  [`docs/DEVELOPMENT_PROMPT.md`](./DEVELOPMENT_PROMPT.md), covering all modules, pipelines A–E, data
  model, API, security, testing, toolchain usage, and phases 0–6 with acceptance criteria.
- **Consequences**: One authoritative prompt drives development; Phase 0 (scaffold + design context)
  and Phase 1 (foundation) are next. Rationale: [`DECISIONS/0003-fullscale-development-prompt.md`](./DECISIONS/0003-fullscale-development-prompt.md).

---

## DEC-0004: Agent Toolchain & Skill Lock

- **Status**: APPROVED
- **Date**: 2026-10-15
- **Context**: The project needs a reproducible, locked agent toolchain spanning design, media/video,
  AI routing, engineering process, token economy, security, browser QA, docs lookup, research, and
  memory.
- **Decision**: Lock **131 skills from 15 sources** in `skills-lock.json` (OmniRoute 46 · caveman 22 ·
  superpowers 15 · remotion 12 · strix 9 · higgsfield 8 · ui-ux-pro-max 7 · context7 3 · deer-flow 3 ·
  impeccable · playwright-cli · skillui · deep-research · agent-memory · claude-automation-recommender).
  Install `@higgsfield/cli`, `@playwright/cli`, and `skillui` globally. Register Higgsfield, Context7,
  and Playwright MCP servers in `opencode.json` (plus `skills.paths`) and `.mcp.json`. Install
  `/security-review` into `.claude/commands/`.
- **Consequences**: `npx skills experimental_install` reproduces the toolchain; project skills are
  visible to OpenCode; provider work must consult Context7/deep-research first. Rationale:
  [`DECISIONS/0004-agent-toolchain-skill-lock.md`](./DECISIONS/0004-agent-toolchain-skill-lock.md).

---

## DEC-0005: Durable Workflow & Approval Engine (first-party)

- **Status**: APPROVED
- **Date**: 2026-10-15
- **Context**: Phase 1 needs a durable content-production orchestrator (sequential + parallel stages,
  dependency tracking, approval checkpoints, revisions, regeneration, cancellation, resumability)
  with state persisted so worker restarts and browser refreshes never lose progress. The spec
  forbids a single giant prompt/request and requires the domain state machine to be owned.
- **Decision**: Build a first-party engine on Laravel queues with all state in PostgreSQL
  (`workflows · workflow_tasks · task_dependencies · approval_requests · approval_decisions`).
  Centralize legal transitions in `WorkflowStatus`/`TaskStatus` enums and a `WorkflowStateMachine`;
  drive execution with `WorkflowEngine` + per-stage `RunWorkflowTask` jobs; gate stages via
  `ApprovalService` using per-stage `ApprovalPolicy` and workflow `AutomationMode`. Expose `/api/v1`
  endpoints for start / progress / pause-resume-cancel / approve-revise-regenerate-reject with
  ownership policies. Ship a credential-free `test_pipeline` template (handler-swappable) verified
  by 39 feature tests.
- **Consequences**: Durable, resumable orchestration owned by the app; dev needs a queue worker;
  provider adapters (Phase 2) plug into `HandlerRegistry` without engine changes. Rationale:
  [`DECISIONS/0005-workflow-approval-engine.md`](./DECISIONS/0005-workflow-approval-engine.md).

---

## DEC-0006: AI Provider Adapter Layer, Model Registry & Model Router

- **Status**: APPROVED
- **Date**: 2026-10-15
- **Context**: Phase 2 needs a provider-independent integration layer with per-provider/capability
  adapters, a DB-backed model registry, and a router that picks compatible *configured* models with
  fallback — without forcing providers into one request shape or exposing credentials.
- **Decision**: Add `ai_providers · ai_models · provider_capabilities · ai_usage_records` and a
  `ProviderAdapter` contract (`key/capabilities/isConfigured/generate`) with an `AdapterRegistry`
  (from `config/virgoai.php`) and a `ModelRouter` that selects enabled models on active, registered,
  configured providers by priority. Credentials live only in env/config (DB stores the key *name*).
  Ship an offline `EchoAdapter` + local/testing seeder and read-only `GET /providers` & `/models`.
  Providers are integrated **one at a time** thereafter.
- **Consequences**: Adding a provider requires only an adapter + config entry + seed + env key; the
  router/engine/API stay unchanged. 12 new tests (**51 total / 120 assertions**). Rationale:
  [`DECISIONS/0006-ai-provider-layer.md`](./DECISIONS/0006-ai-provider-layer.md).

---

## DEC-0007: Marketing and App Domains Are Separate Apps

- **Status**: APPROVED
- **Date**: 2026-10-15
- **Context**: The app root required login, so the public landing page was unreachable at `/`.
- **Decision**: Split the frontend. `apps/marketing` serves the public landing page at the root
  domain with no auth. `apps/web` is the authenticated app on the app domain. Marketing CTAs link to
  the app through `VITE_APP_URL`. Shared UI is copied for now.
- **Consequences**: Each app builds and deploys on its own. Deployment must set `VITE_APP_URL`
  and allow the app domain in the API. Rationale:
  [`DECISIONS/0007-marketing-app-domain-split.md`](./DECISIONS/0007-marketing-app-domain-split.md).

---

## DEC-0008: Generative Video Pipeline — Stage Manifests, Gates, and Review (clean-room)

- **Status**: PROPOSED
- **Date**: 2026-10-16
- **Context**: Output must be a generated video, not a voiceover over stock or Pixabay images.
  The pipeline writes character and environment prompts, generates those references, generates
  scene keyframes from them, turns approved keyframes into motion, renders the video, and generates
  a thumbnail. OpenMontage's method is useful, but its default tool mix does not meet this need.
- **Decision**: Adopt the method as a clean-room design on DEC-0005 and DEC-0006. Stage manifests are
  data (draft at [`docs/pipelines/generative-video.yaml`](./pipelines/generative-video.yaml)). Gates
  are enforced by the engine, and the storyboard keyframe gate sits before any motion render.
  Artifacts are JSON-schema validated. Claude handles reasoning, prompts, and review. Image,
  image-to-video, TTS, music, and render are provider capabilities. Reference-image consistency is a
  routing constraint. No provider is chosen by this decision. OpenMontage code, schemas, and
  manifests are not copied (AGPL-3.0).
- **Consequences**: Engine gains manifest-driven stages. Provider layer gains `image`,
  `image_to_video`, `tts`, and `music` adapters. Media cost rises, so gates and budget checks
  matter most. Audio is a user setting: `narration`, `dialogue`, or `both`. Dialogue assigns one
  open-source voice per character and requires a multi-speaker voice adapter. Lip-sync is in v1 for
  dialogue modes, as a stage after audio and motion. Open questions (first provider, thumbnail timing,
  reviewer model, voice model, lip-sync model) are listed in the record. Foundation is built and tested
  in `packages/contracts` (15 artifact schemas, cross-artifact rules, manifest checks; 39 tests in CI).
  Rationale: [`DECISIONS/0008-generative-video-pipeline-method.md`](./DECISIONS/0008-generative-video-pipeline-method.md).

---

## DEC-0009: Provider Selection for Text, Image, and Video

- **Status**: PROPOSED
- **Date**: 2026-10-16
- **Context**: DEC-0008 needs concrete providers. Owner wants Claude for direction, GPT Image for images, and Seedance for video (which also covers lip-sync). Higgsfield is excluded.
- **Decision**: Text is Anthropic direct (Claude Sonnet 5.5 for direction, Opus 5.5 for final review). Images are GPT Image 1.5 via fal (`gpt-image-1` retires October 2026). Video is Seedance 2.0 via fal (audio and lip-sync included). Higgsfield is rejected by owner instruction. Narration keeps an open-source TTS option; dialogue uses Seedance native audio first.
- **Consequences**: Two keys needed (`ANTHROPIC_API_KEY`, `FAL_KEY`). Each adapter is unverified until a live run passes. Pricing is third-party and must be confirmed. Per-character voice consistency across clips is unverified.
- **Implementation**: `AnthropicAdapter`, `FalAdapter` (queued), `AnthropicModelSync`, `FalModelSync`, `php artisan ai:sync`. 140 API tests pass with recorded responses. Live verification pending the owner's keys.
- **Rationale**: [`DECISIONS/0009-provider-selection.md`](./DECISIONS/0009-provider-selection.md).

---

## DEC-0010: Wallet, Pricing, and Owner Console

- **Status**: PROPOSED
- **Date**: 2026-10-16
- **Context**: Users run paid models. The owner needs pricing, a margin, credit management, user management, and a private console.
- **Decision**: Micro-dollar integers. Prepaid wallet with a ledger, reserved on run start and refunded or settled after. Prices from fal's pricing API and Anthropic's published page. Sell price = cost × (100 + margin)%, default 40. Roles `user` and `super_admin`. Console at `/securegate`. Owner created with `php artisan admin:create`.
- **Consequences**: No payment processor yet. Workflow model stages are not billed. Per-quality GPT Image pricing is not separated. Seedance refuses to bill until a live pricing sync confirms its unit.
- **Rationale**: [`DECISIONS/0010-billing-pricing-owner-console.md`](./DECISIONS/0010-billing-pricing-owner-console.md).

---

## DEC-0011: Subscriptions, Credit Buckets, and Top-ups

- **Status**: PROPOSED
- **Date**: 2026-10-16
- **Context**: Users pay for monthly or yearly plans and top up. The owner needs a public pricing table and to see what each plan keeps.
- **Decision**: Three plans with monthly and yearly prices. One credit = $1 of usage at sell price. Monthly credit expires each cycle and is spent first. Top-up credit does not expire. Subscribers get a discount on top-ups. Plan state moves active → past_due → ended by the rules in the decision file, applied hourly by `billing:renew`. Payments are recorded as pending purchases, granted once when paid, via a signed webhook or by an admin.
- **Consequences**: No payment processor yet. Refunds return to purchased credit. Admin cannot start a plan from the UI yet.
- **Rationale**: [`DECISIONS/0011-subscriptions-and-credit.md`](./DECISIONS/0011-subscriptions-and-credit.md).

---

## DEC-0012: Flutterwave and Paddle Payments

- **Status**: PROPOSED
- **Date**: 2026-10-16
- **Context**: Customers buy plans and top-ups. Duplicate webhooks, retries, and double clicks must never grant credit twice.
- **Decision**: Paddle is the merchant of record for international cards. Flutterwave collects card and local payments and runs recurring payment plans. Keys are encrypted in the key store. Checkout links are created once per purchase. Each webhook event is recorded under a unique key in the same transaction as its effect. Renewals are purchases with unique references. Amounts are checked against the verified transaction before credit is granted. Signatures are checked before any work.
- **Consequences**: Not yet tested live (no keys). Flutterwave auto-renew cancellation needs a verified lookup. Paddle prices are pasted from the dashboard.
- **Rationale**: [`DECISIONS/0012-payment-providers.md`](./DECISIONS/0012-payment-providers.md).

---

## DEC-0013: Long-Video Clipping and Claude Haiku 5.5

- **Status**: PROPOSED
- **Date**: 2026-10-16
- **Context**: Long videos need clips on request. Haiku 5.5 is the cheapest model that can select them, with tiered prices.
- **Decision**: Haiku 5.5 enabled at its tiered price and used by default for clip analysis. Uploads are probed, transcribed with fal Whisper, clipped by Claude from the timestamped transcript, and cut with ffmpeg. Transcription and analysis are billed through the wallet. YouTube links play in full and are never downloaded.
- **Consequences**: fal must reach the audio over public HTTPS. Whisper's billing unit is unconfirmed until a live sync.
- **Rationale**: [`DECISIONS/0013-clipping-and-haiku.md`](./DECISIONS/0013-clipping-and-haiku.md).

---

## DEC-0014: Original Rewrites, Transcription on Our Engine, and the Usage Ledger

- **Status**: PROPOSED
- **Date**: 2026-10-16
- **Context**: Source videos are read and rewritten as original stories; every step's tokens must be recorded and totalled.
- **Decision**: Local whisper.cpp transcription, with no API charge. Copy-guarded original rewrites that are refused and refunded if they reuse eight-word runs. A usage ledger per step; users see tokens and credits, the owner sees cost and margin.
- **Consequences**: Owner usage page not yet built. Local transcription is free to users.
- **Rationale**: [`DECISIONS/0014-originality-and-usage-ledger.md`](./DECISIONS/0014-originality-and-usage-ledger.md).

---

## DEC-0015: Full Video Productions and Exact Output Size

- **Status**: PROPOSED
- **Date**: 2026-10-16
- **Context**: The paste box chooses full video, image, or audio. Video output must match the chosen size.
- **Decision**: Full video is a staged production (script, keyframes, motion, join) with the estimate reserved and settled. Videos are fitted to the exact format size with ffmpeg. Image opens the image tool; audio opens narration, which is not connected yet.
- **Consequences**: At most 30 seconds per full video today. Images use the provider's fixed sizes.
- **Rationale**: [`DECISIONS/0015-full-video-productions.md`](./DECISIONS/0015-full-video-productions.md).

---

## DEC-0016: Skill Library

- **Status**: PROPOSED
- **Date**: 2026-10-16
- **Context**: Users need to choose how the AI works, not only which output to make.
- **Decision**: Skills are defined in code and served at `GET /api/v1/skills`. A selected skill's instructions are given to the model for text, image, and full video runs. Audio runs are not steered.
- **Consequences**: Skill instructions add prompt cost, included in the full-video estimate. Users cannot yet write their own skills.
- **Rationale**: [`DECISIONS/0016-skill-library.md`](./DECISIONS/0016-skill-library.md).

---

## DEC-0017: One Next.js App for the Marketing Site and the Studio

- **Status**: ACCEPTED
- **Date**: 2026-10-17
- **Context**: The landing page was client-rendered only, and the marketing and studio apps had different origins.
- **Decision**: One Next.js app (`apps/next`) serves `/` (pre-rendered landing), `/app` (studio), and the public and owner pages. Screens moved unchanged.
- **Consequences**: `apps/web` and `apps/marketing` are retired but kept until the team deletes them.
- **Rationale**: [`DECISIONS/0017-nextjs-single-app.md`](./DECISIONS/0017-nextjs-single-app.md).

---

## DEC-0018: Read-Only Workflow Graph

- **Status**: ACCEPTED
- **Date**: 2026-10-20
- **Context**: The production page lists stages in a flat list, which hides how stages depend on each other. The API did not return the dependency edges.
- **Decision**: Add a Graph view of the workflow on the production page, built with React Flow. The workflow response now includes `dependencies`. The graph is read-only.
- **Consequences**: No engine change. Editing the graph needs DB-backed templates and a new decision.
- **Rationale**: [`DECISIONS/0018-read-only-workflow-graph.md`](./DECISIONS/0018-read-only-workflow-graph.md).

---

## DEC-0019: Per-Run Stage Instructions

- **Status**: ACCEPTED
- **Date**: 2026-10-20
- **Context**: Users want to change a stage before it runs, without moving templates out of code.
- **Decision**: A user can edit the instructions of a pending model stage in one run. Stage order and wiring stay fixed. The server decides which stages are editable.
- **Consequences**: Edits last only for that run. Rewiring and re-running from a node need a separate decision.
- **Rationale**: [`DECISIONS/0019-per-run-stage-instructions.md`](./DECISIONS/0019-per-run-stage-instructions.md).

---

## DEC-0020: Director Lab — Long-Form Video on Verified fal Limits

- **Status**: PROPOSED
- **Date**: 2026-10-30
- **Context**: The full-video pipeline is capped at six 5-second scenes (30 seconds). Users want one to three
  minutes without editing. fal's largest single call is 15 seconds (Seedance 2.0, Kling v3), so longer films
  must chain calls. Only Seedance reference-to-video and Kling v3 (`elements`, `multi_prompt`) take character
  references in a way that supports consistency.
- **Decision**: Default long-form engine is Seedance 2.0 reference-to-video (cast reference sheets in
  `image_urls`, native audio). Kling v3 standard is the cheaper selectable engine. Takes are 4–15 seconds, chained
  by last frame within a scene, with scenes in parallel. The user sets a length (15 to 180 seconds), not a scene
  count. Quotes are shown and reserved before rendering. Veo 3 and Hailuo are not used for long-form.
- **Consequences**: About $18 per minute at 720p on Seedance, before images and LLM steps. Seedance per-token
  billing must be confirmed by a live call before pricing is final. Continuity and consistency are unproven until a
  live multi-take test passes. Build plan and model table: [`DECISIONS/0020-director-lab-long-form.md`](./DECISIONS/0020-director-lab-long-form.md).

---

## DEC-0021: Credit Rate Card and Margin Floor

- **Status**: ACCEPTED
- **Date**: 2026-10-30
- **Context**: The stored fal price is one number per model, so it under-priced GPT Image at landscape and vertical
  sizes (about 32% under cost), Seedance at 1080p (about 55% under cost), and Veo and Hailuo, which bill rounded-up
  seconds. The margin could be set to zero.
- **Decision**: Price each fal call from a rate table in `config/pricing.php` that depends on the size, quality,
  resolution and billed seconds sent, and refuse any call the table does not cover. Sell price is cost plus margin,
  rounded up, with a 40% margin floor. One credit is $0.001 of sell price. A film is priced as the sum of its director,
  board and take parts. Failed or stopped work is charged only if it was sent to a provider.
- **Consequences**: Charges now match provider costs. Several rates are unverified and listed in `docs/PRICING.md`. The
  one-hour film at 720p ($1,531) exceeds the default $500 per-film cap, so it is refused until the owner raises the cap.
  Rationale: [`DECISIONS/0021-credit-rate-card.md`](./DECISIONS/0021-credit-rate-card.md).
