# DEC-0002: OpenArt Deep Research & Full-Platform Web Clone Architecture

- **Status**: APPROVED
- **Date**: 2026-10-14
- **Author**: Antigravity Agent
- **Target Path**: `web-clone/`

## Context

The user requested a deep research audit and a complete, functional clone of OpenArt (`https://openart.ai`), including all external marketing surfaces, internal suite creation workspaces, and the entire modal dialog ecosystem.

OpenArt is a dual-layer platform:
1. Public Marketing and Discovery engine (Astro + SSR).
2. Internal Creator Studio ("OpenArt Suite") powered by Next.js, Clerk authentication, and multiple specialized generation workspaces (Image, Video, Director, Character, World, Audio).
3. Nine critical modal dialogs supporting authentication, media inspection, plan upgrade, model selection, style presets, camera motion, Topaz upscale, export, and keyboard shortcuts.

## Decision

1. **Conduct & Store Deep Research**:
   - Save the comprehensive system analysis in `docs/OPENART_DEEP_RESEARCH.md`, capturing both external marketing pages and the internal creator studio (`/suite/home`, `/suite/create-image`, `/suite/create-video`, `/suite/director`, `/suite/character`, `/suite/world`, `/suite/motion-sync`, etc.).
2. **Implement Multi-Page Connected Architecture in `web-clone/`**:
   - Instead of a single monolithic file, split the application into separate, interconnected HTML pages:
     - `index.html`: Marketing Landing page (`/`)
     - `home.html`: OpenArt Suite Hub (`/suite/home`) with the full internal sidebar, search bar, "What are we making today?" prompt launcher, "Direct Your Video" quickstarts, and model releases.
     - `create-image.html`: AI Image Generator Studio (`/suite/create-image`)
     - `create-video.html`: AI Video Studio with Veo 3 & Kling (`/suite/create-video`)
     - `director.html`: Multi-scene Storyboard Timeline (`/suite/director`)
     - `character.html`: Consistent Character Builder (`/suite/character`)
     - `world.html`: 3D World Spatial Parallax Studio (`/suite/world`)
     - `motion-sync.html`: Video Motion Transfer Lab (`/suite/motion-sync`)
     - `assets.html`: Media Vault & Projects Library (`/suite/director/projects`)
     - `pricing.html`: Subscriptions & Credit Matrix (`/suite/pricing`)
     - `enterprise.html`: Enterprise LoRA & Infrastructure (`/enterprise`)
     - `mcp.html`: Model Context Protocol & CLI Guide (`/mcp`)
   - Provide shared styling in `css/styles.css` matching OpenArt's exact tokens (`--color-brand: #fb23c2`, dark ink background `#090a0a`).
   - Provide a global modal manager in `js/modals.js` injecting and handling all 9 modals across every page.
   - Provide generative mock simulation and prompt enhancement in `js/studio.js`.
   - Include the subtle "Created By Deerflow" signature linking to `https://deerflow.tech` target `_blank` across all pages.

## Consequences

- Users can open, inspect, and navigate between individual HTML files natively in any web browser (`open web-clone/home.html` or `open web-clone/index.html`).
- Modals, credits, and navigation are shared and synchronized across the entire site.
- Reverse-engineered layouts remain strictly quarantined inside `web-clone/`.


