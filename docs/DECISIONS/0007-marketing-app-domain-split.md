# DEC-0007: Marketing and App Domains Are Separate Apps

- **Status**: APPROVED
- **Date**: 2026-10-15
- **Author**: Claude Code

## Context

The app root (`/`) was guarded by `RequireAuth`, so every visitor, including someone reading the
public landing page, was redirected to `/login`. The landing page had been placed at `/welcome`
inside the app, which is the wrong place for a public page. Public marketing needs its own entry
point that never requires a session, and it should behave like HeyGen and OpenArt: the root domain
is the landing page, and the product lives on a separate app domain.

## Decision

Split the frontend into two Vite apps:

1. **`apps/marketing`** (root domain, for example `virgoai.com`): public landing page at `/`. No
   auth, no API calls. Its CTAs ("Start free", "Sign in", "Start a new production") link to the app
   domain through `appUrl()` in `src/lib/config.ts`, which reads `VITE_APP_URL`. Dev port 5174.
2. **`apps/web`** (app domain, for example `app.virgoai.com`): the authenticated studio. `/` is the
   dashboard and sends unauthenticated visitors to `/login`. Dev port 5173.

Both apps share the same design tokens (`index.css`), Hugeicons, and the `Logo` and `Button`
components. Shared code is copied for now. Extract a `packages/ui` package once a third app needs it.

## Consequences

- The marketing app can ship separately and can be prerendered later for SEO without changing the app.
- The app's `/welcome` route and its `LandingPage` were removed from `apps/web`.
- Deployment must set `VITE_APP_URL` for the marketing build, and the API's Sanctum and CORS
  configuration must allow the app domain. The marketing app makes no API calls, so it needs no CORS entry.
- Local dev: run `apps/marketing` on 5174 and `apps/web` on 5173. Set `VITE_APP_URL` in
  `apps/marketing/.env.local` (see `.env.example`).
