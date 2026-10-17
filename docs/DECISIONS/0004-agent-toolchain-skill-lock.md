# DEC-0004: Agent Toolchain & Skill Lock

- **Status**: APPROVED
- **Date**: 2026-10-15
- **Author**: OpenCode Agent
- **Target Path**: `skills-lock.json`, `opencode.json`, `.mcp.json`, `.claude/commands/`

## Context

The project needs a reproducible agent toolchain covering design intelligence, media/video
generation, AI routing, engineering process, token economy, security, browser QA, documentation
lookups, research, and persistent memory. Skills must be locked so any machine can restore them, and
the design/media/MCP tools requested by the product owner must be installed and referenced.

## Decision

1. **Lock 131 skills (15 sources) via the `skills` CLI.** `skills-lock.json` is the source of truth;
   installs are symlinked into canonical `.agents/skills/` with `.claude/skills/` symlinking to it.
   Restore with `npx skills experimental_install`.

   | Source | # | Purpose |
   | ------ | - | ------- |
   | `diegosouzapw/OmniRoute` | 46 | Multi-provider AI routing, budgets, health, CLI/omni tools |
   | `JuliusBrussee/caveman` | 22 | Token-economy / compressed-context workflows |
   | `Web3nexus/superpowers` | 15 | Planning, TDD, code review, worktrees, debugging |
   | `remotion-dev/skills` | 12 | Programmatic video creation/editing/rendering |
   | `Web3nexus/strix` | 9 | Offensive pentesting + remediation (OWASP) |
   | `higgsfield-ai/skills` | 8 | Animated image/video, Soul characters, product shots, websites |
   | `nextlevelbuilder/ui-ux-pro-max-skill` | 7 | UI/UX palettes, fonts, component patterns |
   | `Web3nexus/context7` | 3 | Current library/API docs (`find-docs`, `context7-cli`, `context7-mcp`) |
   | `bytedance/deer-flow` | 3 | `frontend-design`, `web-design-guidelines`, `data-analysis` |
   | `pbakaus/impeccable` | 1 | Removes AI-generic styling; design audit/refine |
   | `microsoft/playwright-cli` | 1 | Drive a real browser, screenshots, UI testing |
   | `Roentek/Claude_Code_Boilerplate_Framework` | 1 | `skillui` — reverse-engineer any design system |
   | `ramit-mitra/deep-research-skill` | 1 | Multi-agent, source-backed research reports |
   | `t4sh/skills4sh` | 1 | `agent-memory` — reviewable `.agent-memory/` project memory |
   | `anthropics/claude-plugins-official` | 1 | `claude-automation-recommender` |

2. **Install tooling globally**: `@higgsfield/cli`, `@playwright/cli` (`playwright-cli`), `skillui`.

3. **Register MCP servers** for both agent families:
   - OpenCode: `opencode.json` → `skills.paths: [".agents/skills"]` (project skills are not
     auto-scanned) and `mcp`: `higgsfield` (remote), `context7` (remote), `playwright` (local).
   - Claude Code: `.mcp.json` with the same servers (Higgsfield, Context7, Playwright).

4. **Install the security-review slash command**: copy
   `anthropics/claude-code-security-review` → `.claude/commands/security-review.md` (tracked; the
   `.claude/skills/` folder remains git-ignored). The upstream GitHub Action remains available for
   CI when the repo is hosted.

5. **Document** the toolchain in `CLAUDE.md` and its usage map in
   [`docs/DEVELOPMENT_PROMPT.md`](../DEVELOPMENT_PROMPT.md) §13.

## Consequences

- `npx skills experimental_install` fully reproduces the skill set from the lockfile.
- Project-level skills are exposed to OpenCode through `opencode.json` (`skills.paths`); global
  `~/.claude/skills` and `~/.agents/skills` continue to auto-load.
- Higgsfield CLI/MCP require `higgsfield auth login` (OAuth) before first use; Context7 MCP may be
  used anonymously. Both are non-secret, config-only registrations.
- New provider integrations **must** consult `context7`/`deep-research` before being written.
- Any change to the skill set must go through the CLI so the lockfile stays authoritative.

## Alternatives Considered

- **Global-only skill installs** — rejected; the project must be self-contained and reproducible.
- **Hand-copying skills into the repo** — rejected; breaks the lockfile contract (DEC-0001).
- **`.mcp.json` only (Claude Code) without `opencode.json`** — rejected; OpenCode would not see the
  project skills (`.agents/skills` is not auto-scanned at project scope) or the MCP servers.
