# DEC-0018: Read-Only Workflow Graph

- **Status**: ACCEPTED
- **Date**: 2026-10-20

## Context

The production page showed a workflow as a flat list of stages and a timeline of events. The stages
form a graph: scenes fan out to per-scene images, and several stages wait on more than one earlier
stage. The list hides that structure. The API returned tasks but not the edges between them, even
though `task_dependencies` stores them.

## Decision

- The production page gets a **Graph** view of the workflow, with **List** kept as the other option.
  Graph is the default.
- Stages are nodes and dependencies are edges. Node status and approval state use the same colours
  as the list.
- Clicking a node shows its output and error below the graph.
- Nodes cannot be dragged or connected. The graph is a view of the run, not an editor.
- The workflow response gains `dependencies`: a list of `{ task_id, depends_on_task_id }`. It is
  returned by every endpoint that returns a workflow.
- Layout is computed on the client. Each stage sits in the column given by its longest chain of
  dependencies, and stages in the same column keep their pipeline order.
- The graph uses `@xyflow/react` (React Flow).

## Consequences

- No engine or template change. The graph shows the existing task graph.
- Editing the graph (adding stages, rewiring, re-running from a node) needs DB-backed templates and
  a new decision. It is not covered here.
- `@xyflow/react` is a new front-end dependency, and its styles are loaded on the production page.
- The list stays for phones and screen readers.

## Verification

- `WorkflowApiTest` (including a new check on `dependencies`), `ActivityApiTest`, and
  `WorkflowEngineTest` pass: 31 tests.
- `apps/next` type-checks and builds.
- Checked in a browser against a real workflow: four nodes and three edges render, the awaiting-approval
  and placeholder states show, and clicking a node shows its output. The check was at desktop and
  phone widths.

## Alternatives Considered

- **Editable canvas now.** Needs DB-backed templates, cycle validation and partial re-runs. That is a
  separate, larger decision.
- **Graph in the Activity page.** The Activity page lists work across all productions. A graph is per
  production, so it belongs on the production page.
- **Hand-drawn SVG instead of a library.** Pan, zoom and minimap would all need to be built by hand.
