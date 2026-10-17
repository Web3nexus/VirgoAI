# virgoAi

AI Content Studio — turn a YouTube URL, transcript, uploaded video, script, or raw idea into a
complete content package (story → script → characters → scenes → images → video → narration →
subtitles → thumbnail → campaign) by orchestrating many AI providers through a durable workflow
engine.

> **Status:** early implementation. Auth, projects, the durable workflow/approval engine, and the
> provider adapter layer (registry + model router) are working end-to-end. The workflow engine runs
> stages through the router; real providers are added one at a time. The authoritative plan is
> [`docs/DEVELOPMENT_PROMPT.md`](./docs/DEVELOPMENT_PROMPT.md).

## Stack

| Layer        | Choice                                                            |
| ------------ | ----------------------------------------------------------------- |
| Frontend     | React 19 · TypeScript · Vite · Tailwind v4 · shadcn/ui (Base UI)  |
| Backend      | Laravel 13 · PHP 8.5 · queues (Horizon planned)                   |
| Data         | PostgreSQL · Redis                                                |
| Media        | FFmpeg · S3-compatible storage                                    |
| Realtime     | Laravel Reverb (planned)                                          |

## Layout

```
virgoAi/
├── apps/
│   ├── api/     # Laravel API (versioned /api/v1)
│   └── web/     # React + Vite single-page app
├── docs/        # build brief + decision records
├── web-clone/   # read-only design reference (never shipped)
└── CLAUDE.md    # agent guidance
```

## Local development

### Prerequisites

- PHP 8.3+ and Composer (Herd provides these)
- Node.js 18+ (uses `npm`)
- PostgreSQL 14+ and Redis (via Homebrew on macOS)
- FFmpeg

This machine runs our **Homebrew `postgresql@16` on port 5433** (an unrelated EDB PostgreSQL 18
owns 5432). Databases `virgoai` and `virgoai_test` are created locally; role is the current OS user.

### Backend (`apps/api`)

```bash
composer install
cp .env.example .env      # if missing
php artisan key:generate
php artisan migrate --seed # seeds demo@virgoai.test / password
php artisan serve          # http://127.0.0.1:8000
php artisan queue:work     # required: workflow stages run as background jobs
```

Workflow stages execute as queued jobs (`QUEUE_CONNECTION=redis`), so a queue worker must be
running for pipelines to progress. Restart the worker after backend code changes (long-running
processes do not pick up new code).

Seeding also registers a local-only **echo** provider/model (credential-free, deterministic) so the
model router, `content_pipeline` template, and usage logging are exercisable without API keys.

### Frontend (`apps/web`)

```bash
npm install
npm run dev                # http://localhost:5173 (proxies /api → :8000)
```

### Tests

```bash
# backend (Pest, uses the virgoai_test database)
cd apps/api && php artisan test

# frontend typecheck + build
cd apps/web && npx tsc -b && npm run build
```

## API (v1)

All endpoints are under `/api/v1` and authenticated with Sanctum bearer tokens.

| Method | Path             | Description                     |
| ------ | ---------------- | ------------------------------- |
| POST   | `/register`      | Create account, returns token   |
| POST   | `/login`         | Exchange credentials for token  |
| GET    | `/user`          | Current authenticated user      |
| POST   | `/logout`        | Revoke current token            |
| GET    | `/projects`      | List the caller's projects      |
| POST   | `/projects`      | Create a project                |
| GET    | `/projects/{id}` | Show an owned project           |
| PUT    | `/projects/{id}` | Update an owned project         |
| DELETE | `/projects/{id}` | Soft-delete an owned project    |
| POST   | `/projects/{id}/start`        | Start a workflow run for a project       |
| GET    | `/projects/{id}/workflow`     | Latest workflow (tasks + checkpoints)    |
| POST   | `/workflows/{id}/pause\|resume\|cancel` | Control a running workflow     |
| POST   | `/approvals/{id}/approve\|revise\|regenerate\|reject` | Decide a checkpoint |
| GET    | `/workflow-templates`                 | Available workflow templates/stage graphs    |
| GET    | `/providers`                          | Provider catalog (status + configured)       |
| GET    | `/models`                             | Model catalog (capabilities + pricing)       |

Provider/catalog endpoints never expose credentials — they report a boolean `configured`.

## Conventions

- Record non-trivial decisions in [`docs/DECISION_REGISTER.md`](./docs/DECISION_REGISTER.md).
- `web-clone/` is a design reference only — never ship its branding, copy, or assets.
- Never claim an AI provider integration works until its real API workflow is verified.
