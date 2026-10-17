# DEC-0008: Generative Video Pipeline — Stage Manifests, Gates, and Review (clean-room)

- **Status**: PROPOSED
- **Date**: 2026-10-16
- **Author**: Claude Code

## Context

virgoAi must turn one input (URL, transcript, upload, script, or idea) into a finished video and a
thumbnail. Producing that video is **not** a background voice track over stock or Pixabay images.
The pipeline has to:

1. Write prompts for each **character** and generate the character reference images from them.
2. Write prompts for each **environment** and generate the environment reference images.
3. Write a **scene plan** that places those characters and environments into shots, and generate
   each scene's keyframes from the prompts, using the references so the characters stay consistent.
4. Turn the approved keyframes into **motion clips** with an image-to-video model, then narrate,
   score, edit, and render them into a **ready-to-publish video**.
5. Generate a **thumbnail** for the finished video.

We studied [OpenMontage](https://github.com/Web3nexus/OpenMontage) (AGPL-3.0, see
`docs/DECISIONS/0008` Licensing below). Its method is useful: an agent-driven control plane, stage
manifests, schema-validated checkpoints that enforce approval gates, a critique-driven reviewer,
capability-based tool selection, and budget governance. Its default path, however, leans on stock
footage and code-rendered motion graphics. That does not meet the generative requirement above, so
we take the method and not the default tool mix.

## Decision

Adopt the method as a **first-party, clean-room design** and run it on the Phase 1 workflow engine
(DEC-0005) and the Phase 2 provider layer (DEC-0006).

1. **Stage manifests are data.** Each pipeline is a versioned manifest (draft:
   [`docs/pipelines/generative-video.yaml`](../pipelines/generative-video.yaml)). A manifest declares
   stages, the artifacts each stage consumes and produces, the capabilities it needs, its approval
   gate, its review focus, and its success criteria. The workflow engine reads the manifest. Stage
   behaviour is not hard-coded in controllers.
2. **Gates are binding and enforced by the engine.** A stage with `gate: true` cannot move to
   `completed` without a recorded approval (`ApprovalService`, per DEC-0005). The manifest is the
   single source of truth, and no step may skip a gate. Gates sit at the points where spend or
   creative direction is locked in: proposal, script, character sheets, environments, scene plan,
   **storyboard keyframes (before any motion render)**, final cut, and publish.
3. **Artifacts are validated.** Every canonical artifact (brief, proposal_packet, script,
   character_design, environment_design, scene_plan, asset_manifest, edit_decisions, render_report,
   thumbnail_spec, publish_log, review, cost_log) has a JSON Schema under `schemas/artifacts/`. A
   stage that writes an invalid artifact fails and does not advance.
4. **Claude does the reasoning, providers do the media.** Claude writes the brief, script, character
   and environment prompts, scene prompts, edit decisions, composition code, and reviews. Claude does
   **not** generate pixels. Media comes from provider adapters behind capabilities:
   - `text` (Claude) — planning, prompts, review
   - `image` — character sheets, environment plates, keyframes, thumbnails
   - `image_to_video` — motion per scene from an approved keyframe
   - `tts` and `music` — narration and score
   - `render` — Remotion (frame-accurate React scenes) and FFmpeg (mux, concat, audio mix, final encode)
5. **Identity consistency is a routing constraint.** A keyframe request that carries a character or
   environment reference must go to a model that accepts reference images. If none is configured,
   the router must fail with `NoSuitableModel` rather than silently generate off-model characters.
6. **Routing and fallback follow DEC-0006.** The model router picks enabled, configured adapters by
   capability and priority. Each provider is integrated and verified one at a time. **No provider is
   chosen by this decision.** Higgsfield is out of scope per the project owner.
7. **Budget is enforced before spend.** Every paid call goes through estimate → reserve → reconcile
   (cost log). Keyframe and motion stages show projected cost at their gate, and the cap is checked
   before each batch.
8. **Review is a stage, not an afterthought.** After each stage, a reviewer pass (Claude) checks the
   artifact against the manifest's `review_focus` and `success_criteria`. Each critical finding must
   carry a concrete proposed fix, or it is downgraded to `investigation`.
9. **Thumbnail is a stage.** Claude writes a `thumbnail_spec` (concept, text, composition, prompt)
   from the script and final cut. An `image` model generates the base art, and Remotion or FFmpeg adds
   the text and branding layer. It is reviewed and gated before publish.

## Audio mode and voices

The user picks an **audio mode** at intake. It can be changed at the proposal gate:

| Mode | What is spoken | Voices needed |
| --- | --- | --- |
| `narration` | One narrator reads the whole script | 1 |
| `dialogue` | Characters speak their own lines | 1 per character |
| `both` | Narrator plus character dialogue | 1 narrator + 1 per character |

Rules:

1. **Dialogue needs a cast.** In `dialogue` and `both`, `character_design` includes `assign_voices`.
   Each character gets a `voice_id` from the voice registry and a short voice description (age,
   timbre, pace, accent). The voice is locked for the whole video, like the character's look.
2. **Lines, not paragraphs.** In dialogue modes the script is stored as speaker-tagged lines
   (`speaker`, `text`, `emotion`, `start`, `end`). Each line is synthesized separately, so timing is
   known for subtitles, lip-sync, and ducking. Narration keeps paragraph-level timing.
3. **Multi-speaker routing.** Dialogue calls carry the `multi_speaker` constraint. The router picks
   only voice models that hold several distinct voices in one session or support per-call voice
   selection.
4. **Lip-sync is in v1 for dialogue modes.** A `lip_sync` stage runs after audio and motion, only when
   `audio_mode` is `dialogue` or `both`. It takes each speaking shot's motion clip and that speaker's
   own dialogue track. It uses the character's approved reference, so identity stays the same. Shots
   with no visible face are skipped with a reason, not forced through the model. The capability is
   `lip_sync`, routed through the same adapter layer.
5. **Mixing.** The edit stage balances narrator, dialogue, and score. Dialogue stays on top, and
   score ducks while a line plays.
5. **Voice cloning needs consent.** A voice that imitates a real person is not allowed unless the
   person has given written consent, and that consent is stored with the voice. Default voices come
   from the registry.

### Open-source voice candidates (shortlist, not yet chosen)

Each candidate must be checked for licence, language coverage, and speaker count before it is
integrated. Model weights often carry different licences from the code, and some forbid commercial
use.

| Model | Strength for this pipeline | Watch for |
| --- | --- | --- |
| **Kokoro-82M** | Small and fast, several preset voices, good for many characters on modest hardware | Fewer languages than the larger models |
| **Piper** | Runs on CPU, many voices, predictable timing | Less expressive, so emotion control is limited |
| **Parler-TTS** | Voice set by a text description ("a calm older man, slow, warm"), so cast design stays in prompts | Slower; description quality drives voice quality |
| **Chatterbox** | Expressive and supports cloning, with consent rules above | Check cloning terms before use |
| **XTTS-v2** | Multilingual cloning and expressive speech | Coqui's licence restricts commercial use. Check before any paid product |
| **Bark** | Non-speech sounds and laughter for dialogue | Slow, and timing is hard to control |

The voice registry is a provider-capability adapter (DEC-0006) with its own `voice_id` list. Only
verified adapters appear in it.

## Licensing (clean-room rule)

OpenMontage is licensed under **AGPL-3.0**. Our rule:

- We may use its published method as inspiration. We do **not** copy its code, schemas, YAML
  manifests, skill Markdown, or prompts into virgoAi.
- Every file in virgoAi is written from scratch against our own artifact model and our own stage
  list. If a future decision considers copying any of its files, that decision must first be checked
  with license counsel.

## Consequences

- Phase 1 workflow engine gains manifest-driven stages and per-stage gate policies. Phase 2 provider
  layer gains `image`, `image_to_video`, `tts`, and `music` adapters, each with a
  `reference_images` constraint where needed.
- Production cost rises (image and video generation). Gates and budget checks are the main guard.
- Until a provider is integrated and verified against its real API, no feature may claim to generate
  media. The `EchoAdapter` (DEC-0006) keeps the pipeline testable offline.

## Implementation status

Built and verified (`packages/contracts`, 39 tests passing; CI in `.github/workflows/contracts.yml`):

- JSON Schemas for all 15 artifacts the manifest produces, including `research_brief`.
  Each one enforces its own rules: dialogue scripts need lines, a critical review finding needs a
  proposed fix, an accepted review has no critical findings, and voice and reference fields are typed.
- Cross-artifact rules: every dialogue speaker is in the cast, dialogue timing falls inside its
  section, scenes use only approved characters and environments, every script section has a scene,
  dialogue modes have a voice for each character, timeline entries point at real assets, and thumbnail
  text is four words or fewer.
- Manifest checks: stage dependencies form an acyclic graph, required gates are present, motion runs
  only after the storyboard gate, and each audio-mode setting names real steps.
- The manifest now uses `after` for stage dependencies. Earlier drafts mixed artifact and stage names
  in `requires`.

Not yet built: the Laravel stage handlers, the provider adapters (none chosen), the Remotion composition,
and the approval UI. These depend on the Phase 0–2 work in the build brief.

## Open questions

1. Which image-to-video and reference-image provider is integrated first? Pick after a shortlist
   against: reference-image support, per-clip cost, duration limits, and API maturity.
2. Are thumbnails generated per approved final cut only, or as variants at the proposal stage?
3. Does the Claude review pass use the same model as the writer, or a separate reviewer model?
4. Which open-source voice model is integrated first, and does its licence allow the intended use?
5. Which open-source lip-sync model is integrated first? Shortlist to check for licence and quality:
   LatentSync, MuseTalk, SadTalker, Wav2Lip. Weights often carry stricter licences than the code.
