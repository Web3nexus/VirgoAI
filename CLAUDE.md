# CLAUDE.md

Agent guidance for **virgoAi**. Shared across coding agents (Claude Code, OpenCode, Codex).

## Project Overview

- **Name:** virgoAi
- **Purpose:** Production-grade **AI Content Studio** — a multi-model creative AI and automated
  production platform. It turns a YouTube URL, transcript, uploaded video, script, or raw idea into
  a complete content package (story → script → characters → scenes → images → video → narration →
  subtitles → thumbnail → campaign) by orchestrating many AI providers through a durable Laravel
  workflow engine.
- **Status:** Design & toolchain locked — implementation **not started**. The authoritative build
  brief is [`docs/DEVELOPMENT_PROMPT.md`](./docs/DEVELOPMENT_PROMPT.md); the raw product spec is
  [`prompt.md`](./prompt.md).
- **Stack (locked):** React + TypeScript + Vite + Tailwind + shadcn/ui · Laravel (PHP) API + queues ·
  PostgreSQL · Redis · FFmpeg · S3-compatible storage. See DEC-0003.

## Repository Layout

```
virgoAi/
├── CLAUDE.md                    # Agent guidance (this file)
├── prompt.md                    # Raw product spec (source material)
├── skills-lock.json             # Installed agent skills (source of truth)
├── opencode.json                # OpenCode config: project skills path + MCP servers
├── .mcp.json                    # Claude Code project MCP servers (Higgsfield, Context7, Playwright)
├── .claude/skills/              # Skills for Claude Code (symlinks → .agents/skills)
├── .agents/skills/              # Canonical installed skills (OpenCode & other agents)
├── .claude/commands/            # Project slash commands (e.g. /security-review)
├── docs/
│   ├── DEVELOPMENT_PROMPT.md    # Master full-scale development prompt (build brief)
│   ├── OPENART_DEEP_RESEARCH.md # Deep research on the reference product
│   ├── DECISION_REGISTER.md     # Index of key decisions
│   └── DECISIONS/               # One file per recorded decision
└── web-clone/                   # Design reference: reverse-engineered OpenArt UI (read-only)
```

## Installed Skills (131 locked)

Managed via the `skills` CLI (`npx skills`). `skills-lock.json` is the source of truth; installed
folders are derived artifacts and git-ignored.

```bash
npx skills experimental_install   # restore skills from skills-lock.json
npx skills update                 # update all installed skills
```

| Group | Skills | Source | Purpose |
| ----- | ------ | ------ | ------- |
| Design intelligence | `impeccable` | pbakaus/impeccable | Removes AI-generic styling; design audit/refine commands |
| Design intelligence | `ui-ux-pro-max`, `design`, `design-system`, `ui-styling`, `brand`, `banner-design`, `slides` | nextlevelbuilder/ui-ux-pro-max-skill | Styles, palettes, font pairings, charts, stack UI |
| Design intelligence | `skillui` | Roentek/Claude_Code_Boilerplate_Framework | Reverse-engineer any site/repo → design tokens |
| Design intelligence | `frontend-design`, `web-design-guidelines`, `data-analysis` | bytedance/deer-flow | Frontend quality, a11y/UX review, data analysis |
| Media & video | `higgsfield-*` (8) | higgsfield-ai/skills | Animated images/video, Soul characters, product shots |
| Media & video | `remotion-*` (12) | remotion-dev/skills | Programmatic video creation/editing/rendering |
| AI routing | `omni-*`, `cli-*`, `ponytail` (46) | diegosouzapw/OmniRoute | Route/track multiple AI providers, budgets, health |
| Engineering process | `superpowers` set (15) | Web3nexus/superpowers | Planning, TDD, review, worktrees, debugging |
| Token economy | `caveman` set (22) | JuliusBrussee/caveman | Compressed context / low-token workflows |
| Security | `strix` set (9) | Web3nexus/strix | Offensive pentest + remediation (OWASP) |
| Browser QA | `playwright-cli` | microsoft/playwright-cli | Drive a real browser, screenshots, UI testing |
| Docs & deps | `find-docs`, `context7-cli`, `context7-mcp` | Web3nexus/context7 | Latest library/API docs and versions |
| Research | `deep-research` | ramit-mitra/deep-research-skill | Multi-agent, source-backed research reports |
| Memory | `agent-memory` | t4sh/skills4sh | Reviewable `.agent-memory/` project memory |
| Setup | `claude-automation-recommender` | anthropics/claude-plugins-official | Recommend hooks/skills/MCP for the repo |

Additional tooling installed globally: `@higgsfield/cli`, `@playwright/cli` (`playwright-cli`),
`skillui`. MCP servers (Higgsfield, Context7, Playwright) are registered in `opencode.json` and
`.mcp.json`. Full runbook: DEC-0004.

## Conventions

- **Skills:** install through the CLI so `skills-lock.json` stays in sync. Do not hand-edit installed skill folders.
- **Decisions:** record non-trivial technical/product decisions in `docs/DECISION_REGISTER.md` and a file under `docs/DECISIONS/`.
- **Web clones:** keep any reverse-engineered / mirrored UI work inside `web-clone/`. `web-clone/` is a **design reference**, never the shipped app.
- **Build brief:** before writing product code, read `docs/DEVELOPMENT_PROMPT.md` and follow its execution protocol.
- **Never** claim an AI provider integration works until its real API workflow has been implemented and verified.

## Commands

```bash
# Skills
npx skills list                   # list installed skills
npx skills check                  # check for updates
npx skills update                 # update installed skills

# Tooling (installed globally)
higgsfield auth login             # authenticate the Higgsfield CLI (OAuth, opens browser)
playwright-cli open               # open a browser session for UI work
npm i -g skillui && skillui --url <url> --mode ultra   # extract a design system

# Project
claude /security-review           # branch-diff security review (also: .claude/commands/security-review.md)
```

## Build Requirements

- **PHP** (Laravel-compatible), **Composer**, **Node.js 18+**, **PostgreSQL 14+**, **Redis**, **FFmpeg**.
- App source will live in `apps/api` (Laravel) and `apps/web` (React/Vite) — see DEC-0003.
