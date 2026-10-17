# OpenArt Web Clone (`web-clone/`)

A multi-page reverse-engineered clone of [OpenArt.ai](https://openart.ai) including the marketing engine, internal creation studio (`home.html`), specialized generation workspaces, and the 9 modal overlays.

## Architectural Structure

The project uses separate, interconnected HTML pages with shared CSS design tokens (`css/styles.css`) and modular JavaScript for modals (`js/modals.js`) and generation pipelines (`js/studio.js`).

```
web-clone/
├── index.html            # Main Marketing Landing Page (https://openart.ai/)
├── home.html             # OpenArt Suite Dashboard & Hub (https://openart.ai/home)
├── create-image.html     # AI Image Generator Studio (/suite/create-image)
├── create-video.html     # AI Video Studio with Veo 3 & Kling (/suite/create-video)
├── director.html         # Director Mode Storyboard Timeline (/suite/director)
├── character.html        # Consistent Character Builder & LoRA (/suite/character)
├── world.html            # 3D World Spatial Parallax Studio (/suite/world)
├── motion-sync.html      # Motion Sync & Video Lab (/suite/motion-sync)
├── assets.html           # Media Vault & Projects Library (/suite/director/projects)
├── pricing.html          # Subscriptions & Credit Matrix (/suite/pricing)
├── enterprise.html       # Enterprise LoRA & Infrastructure (/enterprise)
├── mcp.html              # Model Context Protocol & CLI Guide (/mcp)
├── css/
│   └── styles.css        # OpenArt Design System (Colors, Fonts, Animations, Glassmorphism)
└── js/
    ├── modals.js         # Global 9-Modal Engine (Auth, Inspector, Credits, Models, etc.)
    └── studio.js         # Generative Studio Pipeline (Prompt Enhancer, Renders, Queue)
```

---

## Page Inventory & Navigation Flow

All pages are directly linked together. You can navigate between any page using the top navigation bar, the Suite sidebar in `home.html`, or the quick page directory on `index.html`:

| Page File | OpenArt Route | Description |
|---|---|---|
| [`index.html`](./index.html) | `https://openart.ai/` | Public landing page, marquee art showcase, prompt sandbox, model showcase. |
| [`home.html`](./home.html) | `https://openart.ai/home` | **OpenArt Suite Hub**: Full internal sidebar (Agents, Tools, Assets, Video Lab), search bar, "What are we making today?" prompt launcher, "Direct Your Video" quickstarts, and model releases. |
| [`create-image.html`](./create-image.html) | `/suite/create-image` | Full Image Lab: Aspect ratios (1:1, 16:9, 9:16, 4:3, 3:4), style chips, CFG slider, "Magic Wand" prompt enhancer, and live generation canvas. |
| [`create-video.html`](./create-video.html) | `/suite/create-video` | Video Lab: Google Veo 3 engine, 3D camera trajectory modal launcher, duration, motion intensity slider, and video player preview. |
| [`director.html`](./director.html) | `/suite/director` | Director Storyboard: Multi-scene sequence editor, shot list, duration tracking, character locks, and film export. |
| [`character.html`](./character.html) | `/suite/character` | Character Identity Builder: Elena facial anchor (99.4% continuity), multi-angle pose cards, and costume variations. |
| [`world.html`](./world.html) | `/suite/world` | 3D Spatial Environment: Interactive mouse-tracked parallax canvas and depth projection. |
| [`motion-sync.html`](./motion-sync.html) | `/suite/motion-sync` | Motion Transfer Lab: Reference video upload and skeletal motion transfer. |
| [`assets.html`](./assets.html) | `/suite/director/projects`| Media Vault: Filterable grid (Images, Videos, Storyboards) with bulk ZIP export. |
| [`pricing.html`](./pricing.html) | `/suite/pricing` | Monthly/Annual pricing toggle (27% OFF savings), Free, Starter, Creator, Business plans. |
| [`enterprise.html`](./enterprise.html) | `/enterprise` | Enterprise solutions: Private VPC, dedicated H100 clusters, custom brand LoRA. |
| [`mcp.html`](./mcp.html) | `/mcp` | Model Context Protocol CLI documentation and tool definitions for AI agents. |

---

## 9 Modals (Interactive Across All Pages via `js/modals.js`)

Each modal is triggerable from the **"Test 9 Modals"** dropdown, hotkeys (`?` or `Esc`), or in-context buttons:
1. **`modal-auth`**: Sign In dialog with Google, Discord, and Email Magic Link options.
2. **`modal-image-detail`**: High-resolution inspector with prompt, seed, model metadata, and remix actions.
3. **`modal-credits`**: Credit balance tracking and package top-ups (+1,000 / +3,500 / +8,000 credits).
4. **`modal-model-selector`**: 100+ Model catalog (Seedream 5.0, Google Veo 3, FLUX.1 Dev, Kling 3.0 Pro).
5. **`modal-styles`**: Visual style preset picker (Cinematic 35mm, Anime, Cyberpunk, 3D Pixar, Oil Painting, Vaporwave).
6. **`modal-camera`**: 3D motion gimbal controller (Pan Left/Right, Zoom In, Tilt Up/Down, Orbit 360).
7. **`modal-upscale`**: Topaz Lab AI Enhancer with 2x/4x/8x multipliers and facial restoration.
8. **`modal-export`**: Container format selection (PNG, WebP, MP4, ProRes) and metadata embedding.
9. **`modal-shortcuts`**: Studio keyboard shortcuts cheatsheet (`Cmd+Enter`, `/`, `Esc`, `?`).

---

## Icon System: Hugeicons Integration

The entire interface utilizes [Hugeicons](https://hugeicons.com/) with rounded stroke and solid iconography:
- **CDN Endpoint**: `https://use.hugeicons.com/font/icons.css` included directly in all HTML `<head>` tags and `@import`ed in `css/styles.css`.
- **Stroke Rounded Style**: `<i class="hgi-stroke hgi-[name]"></i>` for clean, modern 1.5px stroke vector glyphs with rounded terminals (e.g. `hgi-home-01`, `hgi-ai-image-01`, `hgi-ai-magic`, `hgi-sparkles`, `hgi-clapperboard`).
- **Solid Rounded Style**: `<i class="hgi-solid hgi-[name]"></i>` and `.hgi-solid-icon` wrappers for filled solid rounded emblems.
- **Icon Coverage**: 450+ verified Hugeicons across all 12 interconnected studio files and 9 interactive modals.

---

## How to Run

Open any page directly in your browser:
```bash
# Open the main marketing landing page
open web-clone/index.html

# Or open the internal creation studio directly
open web-clone/home.html
```
