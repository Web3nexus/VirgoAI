# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Stack

Locked in DEC-0003: React + TypeScript + Vite + Tailwind + shadcn/ui (apps/web) and Laravel + PostgreSQL + Redis (apps/api).

## Users

Primary: solo creators (YouTubers and short-form creators) who want one source input turned into a complete content package without copying outputs between tools.
Secondary: agencies and marketing teams producing campaigns (six-clip UGC, pipeline E). The landing page leads with creators and carries a team section lower on the page.

## Product Purpose

virgoAi is an AI Content Studio. It turns one input (YouTube URL, transcript, uploaded video, script, or idea) into a content package: story, script, characters, scenes, images, video, narration, subtitles, thumbnail, and campaign. A durable workflow engine orchestrates many AI providers.
Success for a visitor: they submit one input, pick a production profile and automation mode, and see the package assembled without manual copy-paste.

## Positioning

One input, one orchestrated package. Unlike single-model generators that produce one asset per tool, virgoAi routes every stage (story, scene, image, video, narration) to the best configured model for that capability, passes structured output between stages automatically, and keeps approval checkpoints where the creator wants control.

## Operating Context

- Visitors evaluate the product from a marketing landing page before signing up.
- Users work inside a studio workspace: a project, a workflow with checkpoints, approve / revise / regenerate actions.
- Providers are aggregators (fal.ai, Higgsfield) and direct vendors, each reached through a server-side adapter. Credentials never reach the browser.

## Capabilities and Constraints

- Shipped: auth, projects, durable workflow engine with approval checkpoints, provider registry, model router, usage records, workflow UI.
- Provider integration is one provider at a time. A provider counts as integrated only after a real API call succeeds with a configured key.
- Undecided: which real provider runs first in production; budget limits; realtime progress (Reverb); asset storage (S3-compatible).
- Paid operations must be idempotent. Advertising launch requires explicit user authorization.

## Brand Commitments

- Name and wordmark: virgoAi (the "Ai" in the wordmark is brand-accented).
- Visual direction: dark neon studio tokens from DEC-0003 (ink, brand magenta, emerald, lime, cyan). Original branding only.
- No OpenArt or Deerflow branding, copy, icons, or assets. The clone is a visual-language reference, not a product source.
- Icons: Hugeicons (user decision).

## Evidence on Hand

- docs/DEVELOPMENT_PROMPT.md (build brief) and prompt.md (raw product spec).
- Reference research: docs/OPENART_DEEP_RESEARCH.md, web-clone/ (design reference only).
- Landing-page references: openart.ai (creator-led hero, six-tab feature switcher, model card grid, tiered pricing with monthly/annual toggle) and heygen.com (announcement bars, clear hero CTA "Get started for free", use-case chips, feature cards with "Explore" links, audience toggle on pricing).
- No customer logos, testimonials, benchmarks, or pricing exist for virgoAi. Do not invent them. Placeholder proof must be labeled as placeholder.

## Product Principles

1. One input becomes a package. Never make the user copy outputs between modules.
2. Never show success before the backend confirms the output exists.
3. Keep provider and model choice visible but not overwhelming: group by capability, show cost before spending.
4. Creators keep control: approval checkpoints are visible, not buried.
5. Be honest about what is real: label generated output, pending jobs, and downloadable assets distinctly.

## Accessibility & Inclusion

WCAG 2.2 AA contrast on the dark palette, keyboard-reachable controls, visible focus rings, and reduced-motion support for all animated gradients and marquees.
