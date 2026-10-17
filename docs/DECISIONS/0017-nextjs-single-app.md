# DEC-0017: One Next.js App for the Marketing Site and the Studio

- **Status**: ACCEPTED
- **Date**: 2026-10-17

## Context

The marketing site (`apps/marketing`) and the studio (`apps/web`) were two Vite single-page apps. The landing page had no content in its HTML until JavaScript ran, which hurt search and link previews. The two apps also had different origins, so the landing page could not share sign-in with the studio.

## Decision

- One Next.js app in `apps/next` (App Router, React 19, Tailwind v4) serves both.
- `/` is the landing page, pre-rendered at build time.
- The studio is under `/app`. Its layout is client-only, and it sends signed-out visitors to `/login`.
- `/login`, `/register`, `/pricing`, the legal pages, and the error pages stay at the root.
- `/securegate` is the owner console, as before.
- The studio's screens were moved over unchanged. A small compatibility module (`src/lib/router.tsx`) gives them the routing names they already used.
- Browser requests to `/api/v1` are rewritten to the Laravel API, so the app and the API share one origin.
- The dev server runs on port 5173, the port the studio used before.

## Verification

- Every studio screen and public page was compared with the old Vite app at desktop and phone widths. The screenshots matched, within a mean pixel difference of 0.03 out of 255.
- Sign-in, the profile, notifications and Follow us menus, and a Quick Start form all work in the production build and the dev server.

## Consequences

- `apps/web` and `apps/marketing` are no longer run. They remain in the repository until the team agrees to delete them.
- Dev-only: `allowedDevOrigins` must list every host the browser uses, or hot reload and hydration stall.
- Pages that read browser storage render only after mount (`StudioShell`), so server rendering never sees a signed-in state.
