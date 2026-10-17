# DEC-0005: Durable Workflow & Approval Engine (first-party)

- **Status**: APPROVED
- **Date**: 2026-10-15
- **Author**: OpenCode Agent
- **Target Path**: `apps/api/app/Workflows/`, `apps/api/app/Enums/`, `apps/api/app/Jobs/`,
  `apps/api/database/migrations/*_create_workflows_*.php`, `apps/api/app/Http/Controllers/Api/`

## Context

Phase 1 of [`docs/DEVELOPMENT_PROMPT.md`](../DEVELOPMENT_PROMPT.md) requires a durable content
production orchestrator: sequential + parallel stages, dependency tracking, approval checkpoints,
revisions, regeneration, cancellation, and resumability — with all state persisted so a worker
restart or browser refresh never loses progress. The spec explicitly forbids implementing a pipeline
as one giant prompt or one long-running HTTP request, and requires the domain state machine to be
owned by the application (not a third-party service).

## Decision

Build a **first-party durable workflow + approval engine** on Laravel queues, with all state in
PostgreSQL.

1. **Data model** (migrations): `workflows`, `workflow_tasks`, `task_dependencies`,
   `approval_requests`, `approval_decisions`. ULIDs for primary keys; foreign-key constraints and
   cascade deletes; JSONB only for `config` / `input` / `output` / `payload` / `revision`.

2. **Centralized state machine** (`app/Enums/*` + `WorkflowStateMachine`). Legal transitions for
   `WorkflowStatus` (`draft · queued · running · awaiting_approval · awaiting_revision · paused ·
   completed · failed · cancelled`) and `TaskStatus` live in the enums; the state machine is the
   **only** place status changes. Illegal transitions throw `InvalidWorkflowTransition`. Controllers
   never set status directly.

3. **Engine** (`WorkflowEngine`): `start()` builds the task graph from a template inside a
   transaction, then `advance()` marks every *eligible* task (all dependencies settled) as `queued`
   and dispatches one `RunWorkflowTask` job per stage. Completion re-enters `advance()`; when no
   pending approval and no outstanding tasks remain, the workflow is `completed`. `pause()`,
   `resume()`, `cancel()` route through the state machine.

4. **Approval & revision engine** (`ApprovalService`): when a task's `ApprovalPolicy`
   (`always · guided_only · user_configured · not_required`) requires approval for the workflow's
   `AutomationMode`, execution stops at that stage and an `approval_requests` row is raised; the
   workflow becomes `awaiting_approval` and downstream stages cannot start. Decisions (`approve`,
   `revise`, `regenerate`, `reject`) are recorded in `approval_decisions` with full history
   (actor, notes, revision) and routed back through the engine.

5. **Templates & handlers**: `WorkflowTemplates` defines stage graphs; `HandlerRegistry` maps a
   handler key to a `StageHandler`. The built-in `test_pipeline` uses a `TestStageHandler` so the
   engine can be exercised end-to-end without provider credentials. Real stages replace this with
   provider adapters (Phase 2); the test handler is clearly non-production.

6. **Idempotency**: `RunWorkflowTask` only executes tasks in `queued` state, so at-least-once
   delivery cannot double-run a stage.

7. **API** (`/api/v1`, Sanctum): `POST /projects/{project}/start`, `GET /projects/{project}/workflow`,
   `POST /workflows/{workflow}/pause|resume|cancel`,
   `POST /approvals/{approval}/approve|revise|regenerate|reject`. Ownership enforced by
   `WorkflowPolicy` / `ApprovalRequestPolicy`.

## Consequences

- All state is durable in PostgreSQL; the frontend polls the workflow endpoint while active and the
  engine resumes naturally after a worker restart.
- Dev requires a queue worker (`php artisan queue:work`) because stages run as background jobs;
  tests run with `QUEUE_CONNECTION=sync`, making the engine deterministic.
- **39 backend feature tests** (98 assertions) cover transitions, approval policy gating,
  revision/regeneration, cancellation, idempotency, ownership, and the HTTP contracts.
- Provider work (Phase 2) plugs into `HandlerRegistry` without touching the engine.

## Alternatives Considered

- **`laravel-workflow` package as the orchestrator** — rejected as the domain owner; evaluated only
  as a possible accelerator. The orchestrator is core IP and must be owned (DEC-0003).
- **Long-running HTTP request per generation** — rejected; breaks durability and resumability.
- **Status changes scattered across controllers/jobs** — rejected; all transitions are centralized
  in `WorkflowStateMachine`.
- **A single giant job per project** — rejected; each stage is its own task/job for retries,
  regeneration granularity, and partial-failure isolation.
