# Design

<!-- impeccable:design-schema 1 -->

## Visual world: Studio glass

A dark creative-studio surface: near-black ink, glass panels that blur what sits behind them, one magenta brand accent, and neon status colors used only for state. Calm and product-grade, not a neon poster.

Chosen 2026-10-15 from three options (Studio glass, Cinematic reel, Minimal editorial). Studio glass was picked because it fits the Operate screens in the app shell and keeps the landing page light on heavy media.

## Tokens

Source of truth: `apps/web/src/index.css` (`:root` and `@theme inline`). Values come from DEC-0003 section 4.1.

| Role | Token | Value |
|---|---|---|
| App background | `--ink-1000` | `#090a0a` |
| Panel / card | `--ink-900` | `#121314` |
| Input / popover | `--ink-800` | `#16181a` |
| Hover surface | `--ink-700` | `#1b1d1f` |
| Brand accent | `--brand` | `#fb23c2` |
| Brand hover | `--brand-hover` | `#ed00b5` |
| Brand glow | `--brand-glow` | `rgba(251, 35, 194, 0.25)` |
| Success | `--neon-emerald` | `#0ae448` |
| Highlight | `--neon-lime` | `#ccff3e` |
| Info / model tags | `--neon-cyan` | `#22d3ee` |
| Primary text | `--foreground` | `#eeedf0` |
| Secondary text | (slate-11) | `#b5b2bc` |
| Tertiary text | (slate-9) | `#6f6d78` |
| Subtle border | — | `rgba(255, 255, 255, 0.08)` |
| Highlight border | — | `rgba(214, 225, 255, 0.16)` |

Glass surfaces use `backdrop-filter: blur(14px)`.

## Typography

- Headings: Bricolage Grotesque, weight 600–800, tight tracking.
- UI and body: Inter.
- Prompts, seeds, IDs, and technical metadata: JetBrains Mono.

Fonts are self-hosted through `@fontsource-variable/*`, as already configured.

## Iconography

Hugeicons only, through `@hugeicons/react` (`HugeiconsIcon`) with icons from `@hugeicons/core-free-icons`. Stroke style, rounded terminals. Icons are imported from one place and not scattered as raw SVG.

## Motion

- Hover: scale to 1.02 on cards and tiles.
- Focus: border glow using `--brand-glow`.
- Generating placeholders: animated gradient border. This is the only decorative motion, and it marks a pending job.
- `prefers-reduced-motion`: disable scale, glow pulse, and marquees.

## Status vocabulary

Four distinct states, never blended into one badge:

1. **AI prompt**: generated text, not media. Shown in mono type.
2. **Pending job**: animated gradient border and a queued label.
3. **Generated output**: media shown with a "generated" label and provider/model tag (cyan).
4. **Downloadable asset**: shown only after the backend confirms the file exists (emerald).

Never show success before the backend confirms the output exists.

## Layout

- Landing page: single column of sections, max width 1200px, 16px side gutter on phones, no horizontal scroll.
- App shell: left studio sidebar, top bar, main canvas (existing `AppShell`).

## Copy rules

- No invented proof. No customer logos, testimonials, user counts, or ratings until real ones exist. Placeholder areas are labeled as placeholders.
- No pricing numbers. Pricing is undecided.
- Provider names appear only as configured, real integrations. A provider not yet integrated is labeled "coming soon", not shown as available.

## Accessibility

WCAG 2.2 AA contrast on every text color above. Visible focus rings. All icon-only buttons have labels. Respect `prefers-reduced-motion`.

## Anti-patterns to avoid

- Purple-to-blue gradients, nested card soup, pure black or gray with no tint.
- Copying OpenArt or Deerflow branding, copy, or assets.
- Neon everywhere: the accent appears in at most one element per section.
