# DEC-0019: Per-Run Stage Instructions

- **Status**: ACCEPTED
- **Date**: 2026-10-20

## Context

DEC-0018 added a read-only graph. Users want to change a stage before it runs, but changing a
template's order or wiring would mean moving templates out of code, which is a larger change.

## Decision

- A user can edit the **instructions** (`prompt`) of one stage in one production run.
- Only a stage that is still `pending` can be edited. Once a stage is queued or has started, its
  instructions are locked.
- Only stages that run a model (`handler = model`) take instructions. Placeholder stages do not, so
  the server refuses edits to them.
- Stage order, dependencies and templates are unchanged.
- An empty prompt restores the template's own instructions for that stage, not the stage name.
- `PATCH /api/v1/workflows/{workflow}/tasks/{task}` takes `prompt` (up to 4,000 characters). The
  task must be pending: the update is a conditional `UPDATE`, so it cannot race with the engine
  queuing the stage.
- The task response includes `prompt` and `prompt_editable`. The server decides `prompt_editable`,
  so the page does not guess.

## Consequences

- Editing stays within a run. A new run starts from the templates again.
- The list view has no editor yet. Only the graph's detail panel does.
- Rewiring stages and re-running from a node remain future work and need their own decision.

## Verification

- `WorkflowApiTest` covers: saving on a pending model stage, restoring the template instructions
  when cleared, refusing a stage that has started (409), refusing a stage without a model (422), and
  refusing another user (403). The full backend suite passes (332 tests).
- `apps/next` type-checks and builds.
- In a browser, a pending script stage saved new instructions, and the API returned them.

## Alternatives Considered

- **Editable templates in the database.** Right for rewiring, but too large for this step.
- **Let a cleared prompt fall back to the stage name.** The handler's fallback. Rejected because it
  would silently drop the template's instructions.
