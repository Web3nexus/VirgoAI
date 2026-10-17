# @virgoai/contracts

Artifact JSON Schemas and pipeline manifest checks for the generative video pipeline
([DEC-0008](../../docs/DECISIONS/0008-generative-video-pipeline-method.md)).

- `schemas/` — one JSON Schema per artifact (`<name>.schema.json`, draft 2020-12).
- `src/schemas.js` — compiles the schemas; `validateArtifact(name, data)`.
- `src/rules.js` — cross-artifact rules that schemas cannot express (cast membership, section timing,
  approved references, voice coverage, thumbnail word limit, timeline assets).
- `src/manifest.js` — manifest checks: stage graph has no cycles, required gates are present, the
  storyboard gate precedes motion, audio-mode settings name real steps.
- `src/validate-manifest.js` — CLI used by CI.

```bash
npm ci
npm test                    # schema, rule, and manifest tests
npm run validate:manifests  # checks docs/pipelines/generative-video.yaml
```

Manifests use `after` for stage dependencies and `produces` for artifacts. Every produced artifact must
have a schema here.
