# DEC-0001: Project Bootstrap — Agent Skill & Documentation Scaffolding

- **Status**: APPROVED
- **Date**: 2026-10-14
- **Owner**: (unassigned)

## Context

virgoAi is being bootstrapped from an empty directory. Before any product work begins we want:

1. A reproducible set of agent skills shared across Claude Code, OpenCode, and Codex.
2. A single source of truth for which skills are installed.
3. A lightweight, greppable record of important technical/product decisions.
4. A clearly bounded area for reverse-engineered / mirrored web UI experiments.

## Decision

- **Skill management:** use the `skills` CLI (`npx skills`). Add skills from a source repo/subpath
  and let the CLI write agent directories and the lockfile. Do not hand-edit installed skill folders.
- **Installed skills (from `bytedance/deer-flow`):**
  | Skill | Path in source | Purpose |
  | ----- | -------------- | ------- |
  | `frontend-design` | `skills/public/frontend-design` | Distinctive, production-grade frontend UI |
  | `web-design-guidelines` | `skills/public/web-design-guidelines` | Web Interface Guidelines / a11y / UX review |
  | `data-analysis` | `skills/public/data-analysis` | Excel/CSV analysis, stats, pivots, SQL |
- **Placement:**
  - Global (user-level): `~/.agents/skills/<name>` with agent dirs under `~/.claude/skills` etc.
  - Project-level: `.claude/skills/<name>` (Claude Code) and `.agents/skills/<name>` (OpenCode/other).
- **Lockfile:** `skills-lock.json` at the repo root. Tracks source, ref, skill path, and content hash.
- **Decisions:** `docs/DECISION_REGISTER.md` is the index; this folder holds one file per decision.
- **Web cloning:** all mirrored UI work lives under `web-clone/`.

## Consequences

- Reproduce the skill environment anywhere with `npx skills experimental_install`.
- Update skills with `npx skills update`; the lockfile hash changes when a skill's content changes.
- `.claude/skills/` and `.agents/skills/` are ignored by git — they are derived from the lockfile.
- New decisions get the next `DEC-XXXX` id and a matching file in this directory.

## Alternatives Considered

- **Vendoring skill folders directly into the repo** — rejected: duplicates content, drifts from source,
  and defeats the skills CLI's update/hash tracking.
- **No lockfile** — rejected: not reproducible across machines or agents.
