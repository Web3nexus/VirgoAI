# DEC-0003: Full-Scale Development Prompt — Stack, Architecture & Design Direction

- **Status**: APPROVED
- **Date**: 2026-10-15
- **Author**: OpenCode Agent
- **Target Path**: `docs/DEVELOPMENT_PROMPT.md`

## Context

virgoAi has a raw product spec ([`prompt.md`](../../prompt.md)) describing a multi-model AI Content
Studio, a deep-research report on the reference product
([`OPENART_DEEP_RESEARCH.md`](../OPENART_DEEP_RESEARCH.md)), and a reverse-engineered design
reference ([`web-clone/`](../../web-clone)). Implementation has not started. We need one authoritative,
self-contained prompt that a coding agent can execute to build the full product, consolidating the
product spec, the architectural decisions, and the visual direction.

## Decision

1. **Adopt the stack as locked** (already aligned with the spec): React + TypeScript + Vite +
   Tailwind + shadcn/ui + TanStack Query + React Hook Form + Zod on the frontend; Laravel (PHP 8.3+)
   API + queues + Horizon on the backend; PostgreSQL; Redis; FFmpeg; S3-compatible storage; Laravel
   Reverb for realtime.
2. **Adopt a monorepo layout**: `apps/api` (Laravel), `apps/web` (React), `packages/contracts`
   (shared API types / OpenAPI-derived client), plus `docker-compose.yml` for local
   Postgres/Redis/MinIO/Mailpit.
3. **Build a first-party durable workflow engine** in Laravel (DB-persisted state machine over
   queues/events), with centralized workflow states and legal transitions in a domain service.
   Evaluate `laravel-workflow` only as an accelerator.
4. **Provider-independent adapter layer + DB-backed model registry + Model Router** with Quality
   First / Cost Optimized / Balanced strategies and safe fallback. Providers are verified against
   current official docs (Context7 / deep-research) before implementation.
5. **Design direction** = the dark neon studio aesthetic captured in `web-clone/`, productionized
   for virgoAi's own original brand. Adopt its design tokens (ink surfaces, `#fb23c2` brand remapped
   to virgoAi, glassmorphism, Bricolage Grotesque / Inter / JetBrains Mono, suite sidebar layout) and
   its interaction model, **without** shipping OpenArt branding, copy, assets, or the Deerflow
   signature. Design quality is gated by `impeccable`, `ui-ux-pro-max`, `skillui`,
   `frontend-design`, and `web-design-guidelines`.
6. **Author the master build brief** at [`docs/DEVELOPMENT_PROMPT.md`](../DEVELOPMENT_PROMPT.md),
   covering vision, locked stack/layout, design system, orchestrator, approval engine, provider
   architecture, model router, all 15 user modules + 12 admin surfaces, pipelines A–E, data model,
   API, security, testing, toolchain usage, phases 0–6 with acceptance criteria, non-negotiable
   rules, a credentials checklist, execution protocol, and final acceptance criteria.

## Consequences

- A single document is now the source of truth for building virgoAi; agents read it before writing
  product code (also referenced from `CLAUDE.md`).
- Phase 0 (scaffold + design context) and Phase 1 (foundation: auth/RBAC, projects, assets,
  workflow + approval engines, queue, progress) are the immediate next steps when implementation
  begins.
- `web-clone/` remains a read-only reference; no OpenArt IP ships in the product.
- Detailed rationale and the full prompt live in the linked file.

## Alternatives Considered

- **Single-file app / minimal prototype** — rejected; the spec demands a production orchestration
  platform with durable workflows.
- **Adopting a third-party workflow SaaS** — rejected; the orchestrator is core IP and must be
  Laravel-native and durable.
- **Shipping `web-clone/` directly as the frontend** — rejected; it is reverse-engineered reference
  material, not maintainable product source, and carries third-party branding.
