# virgoAi — Master Full-Scale Development Prompt

> **This is the authoritative build brief.** It consolidates the raw product spec
> ([`../prompt.md`](../prompt.md)), the reference-product research
> ([`OPENART_DEEP_RESEARCH.md`](./OPENART_DEEP_RESEARCH.md)), the visual language captured in
> [`../web-clone/`](../web-clone), and the locked toolchain (DEC-0004) into a single execution
> prompt.
>
> **How to use it:** hand this document to a coding agent (Claude Code / OpenCode / Codex) as the
> prompt. It is written as direct instructions to that agent. Do **not** start writing product code
> before the "Execution Protocol" (Section 17) and the "Non-Negotiable Rules" (Section 15) are
> understood.

---

## 0. Preconditions & Current State

- virgoAi is **design-and-toolchain-locked, implementation not started**. There is no application
  code yet; the repository contains the spec, decision records, an agent-skill lock, and a design
  reference clone.
- `web-clone/` is a **design reference only** (a reverse-engineered OpenArt UI). It is **never**
  shipped. Do not import its code into the product, do not copy OpenArt branding, copy, icons, or
  assets, and do not carry over its "Deerflow" signature. Take the *visual language*, not the
  product.
- The stack, repository layout, and toolchain below are **decided**. Do not re-litigate them unless
  a genuine blocker forces it; if it does, record a new decision (Section 15).

---

## 1. Your Role

Act as a **principal software architect, senior full-stack engineer, AI integration engineer,
workflow-automation engineer, and senior product designer**, working as one. You are responsible for
designing, building, testing, and delivering a production-ready, browser-based **AI Content Studio**.

This is a complete software product — not a landing page, chatbot, mock dashboard, or a bag of
disconnected AI utilities. Implement real functionality. Never simulate an AI provider response and
present it as genuine output.

---

## 2. Product Vision

Build an intelligent AI production workspace that transforms **one input** — a YouTube URL,
transcript, uploaded video, existing script, or original idea — into a **complete content package**:

```
source → story → script → characters → scenes → images → video → narration → subtitles → thumbnail → campaign
```

- The user submits **one input** and selects what to produce; the system orchestrates multiple AI
  models by capability, quality, cost, speed, and availability.
- The user **never copies outputs between modules** — every module reads/writes the central
  production orchestrator.
- Support three automation modes: **Guided**, **Assisted**, **Full Handoff** (Section 6.2).
- Provide an **admin console** to manage providers, models, capabilities, costs, routing, and
  workflow execution.
- New AI providers/models must be addable **without rewriting the application**.

---

## 3. Locked Technology Decisions

### 3.1 Stack

| Layer | Choice |
| ----- | ------ |
| Frontend | React + TypeScript + Vite + Tailwind CSS + shadcn/ui + React Router + TanStack Query + React Hook Form + Zod |
| Realtime | Laravel Reverb (WebSockets) with SSE/polling fallback |
| Backend | Laravel (latest stable, PHP 8.3+) — API routes, queues, events/listeners, notifications, policies, scheduler |
| Database | PostgreSQL 14+ (Laravel migrations are authoritative; PostgreSQL-only syntax; JSONB for genuinely variable data) |
| Cache/Queue | Redis (+ Laravel Horizon) |
| Media | FFmpeg (cut/concat/mux/subtitles/resize/aspect-ratio/audio-mix/transitions/render) |
| Storage | S3-compatible interface (MinIO locally; S3/R2/Spaces in prod). Never store media blobs in Postgres. |

### 3.2 Repository Layout (monorepo)

```
virgoAi/
├── apps/
│   ├── api/          # Laravel application (API, orchestrator, jobs, admin domain)
│   └── web/          # React + Vite application (user studio + admin UI)
├── packages/
│   └── contracts/    # Optional: shared TS types / generated API client contract
├── docs/             # DECISION_REGISTER.md, DECISIONS/, DEVELOPMENT_PROMPT.md, OPENART_DEEP_RESEARCH.md
├── web-clone/        # READ-ONLY design reference (never imported by the app)
├── docker-compose.yml# Local Postgres, Redis, MinIO, Mailpit
└── ...
```

- Shared API contract: generate an OpenAPI spec from Laravel and a typed client for `apps/web`.
- Local dev must be reproducible: `docker compose up` brings up Postgres, Redis, MinIO, Mailpit.

### 3.3 Workflow engine

Build a **first-party durable workflow engine** on top of Laravel queues/events, with workflow state
persisted in PostgreSQL. Evaluate the maintained `laravel-workflow` package as an accelerator, but
own the domain state machine. Do **not** implement a production pipeline as one giant prompt or one
long-running HTTP request — every stage is its own job/task.

---

## 4. Design System & Art Direction

The reference look is a **dark, neon, "creative studio OS"** aesthetic captured in `web-clone/`.
Productionize it for **virgoAi's own brand** — original name, wordmark, and assets — while keeping
the layout language and interaction model.

### 4.1 Design tokens (adopt as the starting point)

```css
:root {
  --color-ink-0: #090a0a;   /* app background            */
  --color-ink-1: #121314;   /* panel / card surface      */
  --color-ink-2: #16181a;   /* input & dropdown surface  */
  --color-ink-3: #1b1d1f;   /* subtle hover surface      */
  --color-ink-4: #242729;   /* active / elevated surface */

  --color-brand: #fb23c2;        /* primary accent (remap to virgoAi brand) */
  --color-brand-hover: #ed00b5;
  --color-brand-glow: rgba(251, 35, 194, 0.25);

  --color-emerald: #0ae448;  /* success     */
  --color-lime: #ccff3e;     /* badges/highlights */
  --color-cyan: #22d3ee;     /* info/model tags   */

  --color-slate-12: #eeedf0; /* primary text */
  --color-slate-11: #b5b2bc; /* secondary text */
  --color-slate-9: #6f6d78;  /* tertiary */
  --color-slate-8: #625f69;  /* subtle borders */

  --border-subtle: rgba(255, 255, 255, 0.08);
  --border-highlight: rgba(214, 225, 255, 0.16);
  --glass-blur: blur(14px);
}
```

- **Typography:** headings `Bricolage Grotesque` (600–800, tight tracking); UI/body `Inter`; code,
  prompts, seeds, technical metadata `JetBrains Mono`.
- **Iconography:** rounded-stroke icon set (the clone uses Hugeicons). Pick one licensed set and
  centralize it; never scatter raw SVGs.
- **Surfaces:** glass panels (`backdrop-filter: blur(14px)`) for nav, sidebars, modals.
- **Motion:** subtle hover scale (`1.02`), border-glow on focus, animated gradient border on
  active generation placeholders. Motion must communicate state, never decorate idly.
- **Layout model:** left **studio sidebar** suite + top bar + main workspace canvas, ported from
  `web-clone/home.html` and the specialized workspaces.

### 4.2 Design quality gates (mandatory)

- **Run `impeccable`** (skill + CLI) to remove AI-generic defaults: no overused default fonts, no
  purple-to-blue gradient clichés, no nested-card soup, no pure black/gray without tint. Establish
  `PRODUCT.md` / `DESIGN.md` context once, then keep every screen consistent.
- **Use `ui-ux-pro-max`** for palettes, font pairings, component patterns, and stack-specific
  (React/Tailwind/shadcn) implementation guidance.
- **Use `skillui`** to re-extract tokens from `web-clone/` locally (`skillui --dir web-clone`) or
  from a live reference, and to keep `DESIGN.md` authoritative.
- **Use `frontend-design` + `web-design-guidelines`** as the build-time and review-time quality
  bar (a11y, responsive, empty/loading/error states).
- Port all tokens into a Tailwind preset + shadcn theme (CSS variables), not ad-hoc classes.

### 4.3 UX requirements (from the spec)

- The dashboard's primary action must be immediately obvious: **"Start a New Production"**.
- The primary input supports: paste YouTube URL · paste transcript · enter an idea · upload
  transcript file · upload a supported video · select an existing project. Drag-and-drop required.
- Visually distinguish **AI-generated prompt** vs **actual generated image/video** vs **pending
  job** vs **completed downloadable asset**. Never show a success state before the backend confirms
  the output exists.
- Consistent modals/drawers, skeletons, progress indicators, meaningful error messages, and
  accessible controls throughout.

---

## 5. Application Structure

### 5.1 User navigation

Dashboard · New Project · Story Idea Generator · Story Lab · Content Mastermind · Thumbnail Maker ·
Video Clipper · UGC Content Studio · Character Forge · Outfit Extractor · Voice Image Editor ·
YouTube Promotion · My Projects · Asset Library · Workflow Activity · Settings

### 5.2 Administrative navigation

Overview · AI Providers · Model Registry · Model Router · Workflow Templates · Job Queue ·
Usage & Costs · Provider Health · Failed Jobs · Users & Permissions · System Configuration ·
Audit Logs

Implement **role-based access control**. Regular users must never modify global provider
credentials, system-wide routing rules, or another user's projects.

---

## 6. Core Architecture

### 6.1 Central Content Production Orchestrator (the heart)

A durable workflow orchestration system in Laravel that manages dependencies, execution,
approvals, retries, failures, costs, and final packaging. Capabilities:

- Sequential + parallel task execution, conditional branches, dependency tracking.
- Approval checkpoints, user revisions, task cancellation, retries, timeouts.
- Provider fallback, cost controls, workflow resumption, partial regeneration.
- Progress tracking and final package generation.

**States (validated, centralized):** `Draft · Queued · Running · Awaiting Approval · Awaiting
Revision · Paused · Completed · Failed · Cancelled`. Legal transitions live in a dedicated domain
service — controllers must never set status arbitrarily.

**Durability:** state persists in PostgreSQL. A worker restart must not destroy a project's state; a
browser refresh must not interrupt a workflow; the user can leave and return later.

**Idempotency:** stable task identifiers, unique constraints, idempotency keys, duplicate-job
protection. Distinguish *retrying a failed HTTP request* from *submitting a new paid generation*.
Never blindly resubmit a paid operation whose previous result is uncertain.

### 6.2 Automation modes

| Mode | Behavior |
| ---- | -------- |
| **Guided** | Pause at configured checkpoints. Controls: Approve & Continue · Edit Before Continuing · Regenerate · Skip Stage · Pause · Cancel. Next stage cannot run until approval is satisfied. |
| **Assisted** | Auto-run routine steps; pause only on decision points, missing outputs, unsafe provider failure, budget breach, or required external confirmation. |
| **Full Handoff** | Run all eligible stages automatically; user can paste input, pick a saved production profile, start, leave, and return to finished assets. Still respects budgets, provider permissions, source-access restrictions, mandatory approvals, and irreversible external actions. |

Each project has a mode; users can set a default mode in Settings.

### 6.3 Approval & revision engine

- Per-stage approval policy: `Always required` · `Guided only` · `User-configured` · `Not required`.
  Rules centralized and applied consistently.
- On stage completion show: stage, status, output preview, input dependencies, next stage, estimated
  extra cost, warnings, available actions.
- **Edit Before Continuing:** open the right editor, load current version, preserve previous version,
  validate, save a new revision, recalculate affected dependencies, continue per policy.
- **Regeneration granularity:** whole stage · a scene · a character · one image · a narration
  segment · one video clip · a thumbnail variant. Do **not** regenerate unaffected assets. When an
  upstream asset changes, mark dependents as *potentially outdated* and surface what needs regen
  (dependency graph).

### 6.4 AI Provider Adapter Architecture

A provider-independent integration layer. Separate adapters per provider and capability; normalize
model selection, input validation, request construction, auth, task submission, async processing,
status polling, result retrieval, error handling, usage info, and cost metadata — **without forcing
every provider into one identical internal request shape**. Providers must be individually
enableable, disableable, testable, and replaceable.

**Capability families and integration targets** (these are *targets*, not assumed-working
integrations):

- **Language models:** OpenAI · Google Gemini · Anthropic · compatible others. Used for story,
  script, transcript analysis, creative planning, prompt generation, structured output, routing
  decisions.
- **Image models:** OpenAI GPT Image · Google image gen/edit · FLUX · Ideogram · others.
- **Video models:** ByteDance Seedance · Google Veo · Runway · Kling · others.
- **Voice/audio:** STT · TTS · narration · dubbing · voiceover · SFX · music where available.

**Rule:** before implementing any adapter, **verify the current official API docs** — model IDs,
credentials, regions, capabilities, pricing. Use the `context7` skills (`find-docs`, `context7-mcp`)
and/or `deep-research` for this. Do not invent endpoints or model identifiers. Do not assume a
consumer-app model is available via public API. If credentials are missing, implement the structure
and report the missing config **accurately**.

### 6.5 Intelligent AI Model Router

Every creative operation requests a compatible model through the router unless the user picks one.
Consider: task type · input/output formats · required reference assets · resolution · duration ·
audio needs · quality bar · estimated cost · expected latency · availability · historical success ·
user preference · project budget.

**Strategies:** `Quality First` · `Cost Optimized` · `Balanced`. Never claim one model is
universally superior — decide from capability metadata, verified docs, and evaluation results.

**Model registry (DB-backed):** provider, model id, display name, supported tasks/inputs/outputs,
reference-image/video support, audio support, resolutions, duration limits, pricing metadata,
availability, last-verified date, admin enablement.

**Fallback:** on failure — classify recoverability → retry only if safe → respect rate limits →
check idempotency → find compatible alternatives → check budget → select fallback per policy →
record the switch reason. Never silently change important output requirements. If no compatible
fallback exists, pause/fail with a clear explanation.

> Prior art / dev tooling: `OmniRoute` (46 skills installed) demonstrates multi-provider routing,
> budgets, and provider health — use it as a **reference** for the router's design and as a dev-time
> relay across providers. Do not couple the product to it.

### 6.6 Real-time progress

Dashboard shows live workflow progress: current stage, overall status, completed/running/queued
tasks, approval checkpoints, failed tasks, retries, generated assets, estimated vs actual cost.
Use Reverb/WebSocket or SSE; **never** hold a long HTTP request open while a model generates a
video. Progress comes from persisted state + notifications.

### 6.7 Cost management

Per-project budgets, per-user limits, configurable thresholds, provider pricing metadata, estimated
costs, actual cost records, usage reports, warnings, and job cancellation. Check remaining budget
before expensive operations; if insufficient, pause and request a decision. Never present estimates
as guaranteed charges.

### 6.8 Project & asset management

Each project stores: original input, source metadata, transcript, analysis, story concepts, script
revisions, character records, scene records, image prompts, generated images, narration, subtitles,
generated videos, clips, thumbnails, approval history, workflow history, provider usage, costs,
errors/retries. Provide search, filtering, previews, download, version history, duplication,
archive, and a dependency graph.

---

## 7. Feature Modules

Implement all of the following (detailed behavior from [`../prompt.md`](../prompt.md) §12–21):

1. **Story Idea Generator** — YouTube URL/transcript/upload/idea → summary, themes, conflicts,
   multiple concepts, adapted concept, title, logline, synopsis, outline, full script. Cultural
   settings: Nigeria (Lagos, Abuja, …), other African settings, US, International, Custom — **never
   treat "Africa" as one culture**. Support language/genre/tone/audience/duration. Distinguish
   fictional adaptation from factual summary; never reproduce a protected script or imitate a living
   creator's style.
2. **Story Lab** — story → production plan: character descriptions + reference prompts, environments,
   scene breakdowns, cinematic image prompts, camera/composition/lighting/color/continuity, aspect
   ratios, negative prompts. Persistent IDs for projects/characters/locations/scenes/assets. Track
   prompt generation and image generation as separate operations.
3. **Content Mastermind** — short-form kit (default 60s, configurable): concept, hook, script, scene
   breakdown, dialogue, narration, image prompts, images, video instructions, voiceover, subtitles,
   SFX cues, music suggestions, title, description, thumbnail concept, CTA. Estimate narration
   duration from the real script. Component-level regeneration.
4. **Thumbnail Maker** — YouTube/TikTok/Facebook formats: artwork, headlines, text overlays,
   typography, cropping, positioning, brand colors, variations, export presets. Use **deterministic
   text rendering** — never rely on an image model to spell headlines. Save original artwork and
   composed thumbnails separately.
5. **AI Video Clipper** — authorized uploads: metadata → audio extract → transcribe → analyze →
   highlight candidates → timestamped suggestions → previews → subtitles → vertical reframe → user
   adjustment → render → titles/descriptions → thumbnail concepts → package. Preserve source
   timestamps. When visual analysis is needed, extract frames or use a video-analysis capability —
   a transcript alone is not enough. Never claim finished clips when you produced only timestamps.
6. **UGC Content Studio** — 6-clip advertising package: concept, audience, 6 distinct concepts, 6
   scripts, 6 hooks, scene instructions, voiceover, CTAs, captions, cover concepts; generate real
   assets when requested/available. Maintain campaign consistency; track scripts/prompts/media
   independently.
7. **Character Forge** — concepts, descriptions, clothing/accessories, front/side/back/three-quarter
   views, expression refs, multiple outfits, continuity notes, reference images. Canonical character
   records reusable by Story Lab. Never guarantee exact consistency across independent generations.
   Regenerate one view without recreating the project.
8. **Outfit Extractor** — authorized reference image → structured garment/color/pattern/layering/
   accessory/footwear/silhouette/styling descriptions; cautious about uncertain fabrics; reusable
   outfit prompts transferable to Character Forge / Story Lab.
9. **Voice Image Editor** — image + voice instruction: upload → record/upload → transcribe → show
   interpreted instruction → edit → submit to an image-editing provider → show result → revise →
   save. Also typed instructions. Preserve originals; store revisions separately.
10. **YouTube Promotion** — campaign objective, audience, ad copy, creative recs, budget scenarios,
    dates, tracking, setup instructions; configurable minimum video duration (incl. 8 min). **Never
    launch paid advertising without explicit authorization**; require confirmation of details and
    spend. If Google Ads is integrated, use official auth/APIs. Never guarantee results.
11. **Dashboard / New Project** — see Section 4.3.
12. **My Projects / Asset Library / Workflow Activity / Settings** — see Section 6.8.
13. **Admin console** — all Section 5.2 surfaces, RBAC-gated, with audit logs.

---

## 8. Automatic Production Pipelines (templates)

| Pipeline | Input | Output |
| -------- | ----- | ------ |
| **A. Transcript → Story** | YouTube URL / transcript | analysis, story ideas, selected concept, script, characters, scenes, image prompts, thumbnail concepts |
| **B. Story → Cinematic Video** | approved story/script | characters, scene breakdown, reference + generated images, generated shots, narration, subtitles, edited video, thumbnail, description |
| **C. Long Video → Social Clips** | authorized video | transcript, clip candidates, timestamped highlights, vertical clips, captions, titles, thumbnail concepts |
| **D. Complete Short-Form** | idea / transcript | hook, script, scene plan, images or shots, narration, subtitles, finished short, thumbnail, social captions |
| **E. Six-Clip UGC Campaign** | product/service brief | 6 concepts, 6 scripts, 6 production plans, requested visual assets, captions, CTAs |

The orchestrator transfers structured data between stages automatically. The user never copy-pastes.

---

## 9. PostgreSQL Data Model

Create normalized tables via Laravel migrations (adjust to reuse any existing tables):

`users · projects · project_sources · source_transcripts · story_concepts · scripts ·
script_versions · characters · scenes · assets · asset_versions · ai_providers · ai_models ·
provider_capabilities · workflow_templates · workflows · workflow_tasks · task_dependencies ·
approval_requests · approval_decisions · ai_usage_records · project_budgets · notifications ·
audit_logs`

Use JSONB only for genuinely variable structured data (provider metadata, workflow config) — never as
a substitute for relational modeling. Use foreign keys, indexes, unique constraints, transactions,
PostgreSQL-only syntax. No media blobs in the DB. Every project/asset query enforces ownership.

---

## 10. API Design

Consistent, versioned Laravel API (`/api/v1/...`). Suggested contracts (adapt, don't duplicate):

```
GET/POST   /api/v1/projects
GET        /api/v1/projects/{project}
POST       /api/v1/projects/{project}/start
GET        /api/v1/projects/{project}/workflow
POST       /api/v1/workflows/{workflow}/pause|resume|cancel
POST       /api/v1/approvals/{approval}/approve|revise|regenerate
GET        /api/v1/assets/{asset}
POST       /api/v1/assets/{asset}/regenerate
GET        /api/v1/providers   |   GET /api/v1/models
POST       /api/v1/admin/models/{model}/test
```

Use Form Requests for validation, Policies for authorization, API Resources for responses, and a
consistent error envelope. **Never trust a project id from the frontend** without an ownership +
authorization check.

---

## 11. Security (mandatory)

Secure auth · RBAC · server-side authorization · rate limiting · request validation · file/upload
validation & limits · secure credential management · safe external-URL handling · provider usage
limits · audit logs for admin actions · signed download URLs · temporary-file cleanup.

Never expose provider API keys to React. Never let a user reach another user's transcripts, media, or
workflow history. Do not download arbitrary URLs without validation. Use permitted source-access
mechanisms for YouTube and other services. Respect copyright, privacy, and platform terms.

Validate the built app with the installed security tooling: **`strix`** skills for white-box /
OWASP pentesting of the running app, and the **`/security-review`** command for branch-diff reviews.

---

## 12. Testing

**Backend:** auth · project ownership · API validation · workflow transitions · approval policies ·
revisions · task dependencies · retries · idempotency · cancellation · budget enforcement · provider
fallback · authorization · asset ownership · failure recovery.

**Frontend:** project creation · input validation · mode selection · approval controls · editing ·
workflow progress · error states · asset previews · navigation.

**Provider integrations:** mocked tests always; separate real integration tests when credentials
exist. Do not run expensive production generations in ordinary test runs. Document the test commands.

Use **`playwright-cli`** to drive the real UI for end-to-end/visual checks.

---

## 13. Agent Toolchain — What to Use, When

All skills are locked in [`../skills-lock.json`](../skills-lock.json) (131 skills, 15 sources) and
documented in [`../CLAUDE.md`](../CLAUDE.md). Use them deliberately:

| When you need to… | Use |
| ----------------- | --- |
| Remove AI-generic styling, audit/refine UI, live browser iteration | `impeccable` |
| Pick palettes, font pairings, component/chart patterns for React+Tailwind+shadcn | `ui-ux-pro-max` (+ `design`, `design-system`, `ui-styling`, `brand`, `banner-design`, `slides`) |
| Extract design tokens from `web-clone/` or any site/repo | `skillui` (`skillui --dir web-clone`) |
| Build / review frontend quality and accessibility | `frontend-design`, `web-design-guidelines` |
| Generate animated images/video, Soul characters, product shots for the site | `higgsfield-*` skills (+ `higgsfield` CLI/MCP) |
| Programmatic video create/edit/render (in-app export, marketing video) | `remotion-*` |
| Route/track multiple AI providers, budgets, health (dev-time + prior art) | `omni-*` / `cli-*` / `ponytail` (OmniRoute) |
| Plan, TDD, review, worktrees, debug, verify before claiming done | `superpowers` set |
| Compress context / work in low-token mode on large tasks | `caveman` set |
| Attack the built app before attackers do (OWASP, white-box) | `strix` set + `/security-review` |
| Drive a real browser, screenshots, UI testing | `playwright-cli` |
| Get current library/API docs and versions (mandatory before each provider adapter) | `context7` skills (`find-docs`, `context7-mcp`) + Context7 MCP |
| Deep, source-backed research (provider APIs, competitor features) | `deep-research` |
| Keep durable project memory across sessions | `agent-memory` (`.agent-memory/`) |
| Audit the repo and recommend hooks/skills/MCP | `claude-automation-recommender` |
| Analyze usage/cost CSVs | `data-analysis` |

CLI/tooling already installed globally: `@higgsfield/cli`, `@playwright/cli`, `skillui`.
MCP servers registered in `../opencode.json` and `../.mcp.json`: Higgsfield, Context7, Playwright.
(First Higgsfield use requires `higgsfield auth login` — OAuth; Context7 may be used anonymously.)

---

## 14. Implementation Phases & Acceptance

Implement in working increments. **Do not stop after a plan** — proceed to Phase 1 unless a genuine
blocker or missing credential prevents it.

**Phase 0 — Foundation setup.** Monorepo scaffold (`apps/api`, `apps/web`), Docker Compose
(Postgres/Redis/MinIO/Mailpit), Laravel + Vite bootstrapped, Tailwind/shadcn theme seeded with
Section 4 tokens, `impeccable` context initialized (`PRODUCT.md`/`DESIGN.md`).

**Phase 1 — Foundation.** Auth + RBAC, project management, asset management, provider configuration,
workflow engine, approval engine, job queue, progress tracking.
*Acceptance:* a user creates a project, starts a test workflow, reviews a checkpoint, edits an
output, approves it, and resumes.

**Phase 2 — AI orchestration.** Provider adapters, model registry, model router, capability
validation, cost estimation, fallback policies, provider health, usage logging.
*Acceptance:* the app submits a supported task to a configured provider, receives a result, saves it,
and routes it into the next stage.

**Phase 3 — Story & image studio.** Story Idea Generator, transcript processing, Story Lab, Content
Mastermind, Character Forge, Thumbnail Maker, Outfit Extractor, Voice Image Editor.
*Acceptance:* users complete these workflows with real configured providers.

**Phase 4 — Video production.** Uploads, transcription, Video Clipper, scene generation, video-model
integration, narration, subtitles, FFmpeg rendering, final export.
*Acceptance:* the system produces a real video from supported source material + generated assets.

**Phase 5 — Automated campaigns.** UGC Content Studio, YouTube promotion planning, optional ads
integration, batch generation, automated packaging, completion notifications.
*Acceptance:* the system executes configured multi-stage campaigns and saves outputs.

**Phase 6 — Hardening.** Integration testing, security review, failure recovery, cost controls,
performance testing, queue monitoring, documentation, deployment config. Do not call the platform
production-ready until critical workflows pass end-to-end testing.

---

## 15. Non-Negotiable Rules

1. Inspect before changing; reuse working components; never delete functioning code to simplify.
2. Implement real functionality. No fake data in production workflows.
3. Never invent provider capabilities/endpoints/model IDs. Verify with Context7/deep-research first.
4. Never expose API credentials to the frontend.
5. PostgreSQL-only migrations and queries.
6. Keep business logic out of React components; keep controllers thin; use dedicated orchestration
   and routing services.
7. Expensive work runs in background jobs; workflow state is persisted.
8. Make paid operations safe against duplicate submission.
9. Provide meaningful error messages; add tests for critical behavior.
10. Preserve previous asset versions; respect approval requirements; respect spending limits.
11. **Do not claim a feature is complete, a provider is integrated, or a video is generated until the
    real workflow has been implemented and verified.** Mocked tests alone do not count.
12. Record any new non-trivial decision in `docs/DECISION_REGISTER.md` + `docs/DECISIONS/`.

---

## 16. Credentials & External Dependencies Checklist

Before/while implementing, collect and document (never commit secrets — use `.env` +
`.env.example`):

- [ ] LLM: OpenAI, Google Gemini, Anthropic (as applicable)
- [ ] Image: OpenAI (GPT Image), Google, FLUX (e.g. fal/Replicate), Ideogram
- [ ] Video: ByteDance Seedance, Google Veo, Runway, Kling (whichever is offered via API)
- [ ] Audio: an STT provider, a TTS/voice-dubbing provider, music/SFX provider
- [ ] YouTube transcript/source access mechanism (permitted)
- [ ] Google Ads (only if advertising automation is implemented)
- [ ] S3-compatible storage credentials (MinIO local defaults are fine for dev)
- [ ] Higgsfield account (CLI/MCP OAuth) for site media generation

For anything missing, implement the adapter structure and report the missing configuration exactly.

---

## 17. Execution Protocol (do this, in order)

1. **Read** this document, [`../prompt.md`](../prompt.md), `docs/DECISION_REGISTER.md`, and the
   design reference in `web-clone/`.
2. **Initialize design context**: run `impeccable` to create `PRODUCT.md` / `DESIGN.md`; run
   `skillui --dir web-clone` to extract tokens; fold them into the Tailwind/shadcn theme.
3. **Plan** with `superpowers` (`brainstorming` → `writing-plans`): produce a concise increment plan
   aligned to Phase 0–1, then start building. Use `caveman` if context pressure is high.
4. **Verify docs before integrating**: for each provider/model, fetch current API documentation via
   Context7 / `deep-research`; record provider metadata in the model registry.
5. **Build** incrementally with TDD where practical (`superpowers` + `test-driven-development`).
6. **Check the UI** in a real browser (`playwright-cli`) and against `impeccable` /
   `web-design-guidelines`.
7. **Security-review** changes (`/security-review`) and, at milestones, run `strix`.
8. **Persist context** with `agent-memory` so the next session resumes cleanly.
9. **Report** architecture, blockers, and missing credentials honestly; then continue.

---

## 18. Final Acceptance Criteria

An authorized user can: open the React app → create a project → paste a supported YouTube URL or
transcript → select a production profile → choose Guided/Assisted/Full Handoff → start the
workflow → have Laravel coordinate stages → have the Model Router select compatible configured
models → watch structured outputs pass between stages automatically → review/edit/approve/regenerate
where required → resume interrupted workflows → track real progress → control spend → download
assets → view full project history.

Full Handoff executes all eligible configured stages without routine manual intervention. The result
is a functioning AI production platform with a **durable workflow engine**, a **modular provider
architecture**, **intelligent model routing**, and **end-to-end automation** — built on React,
Laravel, and PostgreSQL.

> Begin by inspecting the repository and reporting the current architecture and blockers, then
> implement Phase 0 and Phase 1.
