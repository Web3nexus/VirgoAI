// Semantic checks for a pipeline manifest (docs/pipelines/*.yaml, DEC-0008).
// JSON Schema is not used here: the manifest's rules are about the stage graph,
// gates, and settings, which need to be checked together.

import { hasArtifact } from "./schemas.js";

// Stages that must gate. These are the approval points DEC-0008 requires.
export const REQUIRED_GATES = ["proposal", "script", "keyframes", "final_review", "publish"];

export function validateManifest(manifest) {
  const errors = [];
  if (!manifest || typeof manifest !== "object") return ["manifest must be an object"];
  if (!manifest.name) errors.push("manifest.name is required");
  if (!Array.isArray(manifest.stages) || manifest.stages.length === 0) {
    return [...errors, "manifest.stages must be a non-empty list"];
  }

  const stages = new Map();
  for (const stage of manifest.stages) {
    const label = stage.name ?? "(unnamed)";
    if (!stage.name || !/^[a-z][a-z0-9_]*$/.test(stage.name)) {
      errors.push(`stage "${label}": name must match ^[a-z][a-z0-9_]*$`);
      continue;
    }
    if (stages.has(stage.name)) errors.push(`stage "${stage.name}": duplicate name`);
    stages.set(stage.name, stage);

    if (typeof stage.gate !== "boolean") errors.push(`stage "${stage.name}": gate must be true or false`);
    for (const artifact of stage.produces ?? []) {
      if (!hasArtifact(artifact)) errors.push(`stage "${stage.name}": produces "${artifact}" but no schema exists`);
    }
    if (!Array.isArray(stage.success_criteria) || stage.success_criteria.length === 0) {
      errors.push(`stage "${stage.name}": success_criteria must be a non-empty list`);
    }
    if (!Array.isArray(stage.review_focus) || stage.review_focus.length === 0) {
      errors.push(`stage "${stage.name}": review_focus must be a non-empty list`);
    }
  }

  // Stage dependencies must name real stages, and the graph must have no cycles.
  for (const stage of stages.values()) {
    for (const dep of [...(stage.after ?? []), ...(stage.after_optional ?? [])]) {
      if (!stages.has(dep)) errors.push(`stage "${stage.name}": after "${dep}" is not a stage`);
    }
  }
  errors.push(...findCycles(stages));

  // Gates the policy requires.
  for (const name of REQUIRED_GATES) {
    const stage = stages.get(name);
    if (!stage) errors.push(`required stage "${name}" is missing`);
    else if (stage.gate !== true) errors.push(`stage "${name}" must gate (DEC-0008)`);
  }

  // Motion must not start before the storyboard gate has been approved.
  const motion = stages.get("motion");
  if (motion) {
    const keyframes = stages.get("keyframes");
    if (!keyframes || keyframes.gate !== true) {
      errors.push('stage "motion" requires the "keyframes" storyboard gate (DEC-0008)');
    } else if (!reachable(stages, "motion", "keyframes")) {
      errors.push('stage "motion" must run after "keyframes"');
    }
  }

  errors.push(...settingsErrors(manifest.settings, stages));
  return errors;
}

function settingsErrors(settings, stages) {
  const errors = [];
  const audio = settings?.audio_mode;
  if (!audio) return errors;

  const values = audio.values ?? [];
  const changes = audio.changes_stages ?? {};
  for (const value of values) {
    if (!(value in changes)) errors.push(`settings.audio_mode: no changes_stages entry for "${value}"`);
  }
  for (const [mode, targets] of Object.entries(changes)) {
    for (const target of targets) {
      const [stageName, step] = target.split(".");
      const stage = stages.get(stageName);
      if (!stage) {
        errors.push(`settings.audio_mode.changes_stages.${mode}: "${stageName}" is not a stage`);
      } else if (step && !(stage.steps ?? []).includes(step)) {
        errors.push(`settings.audio_mode.changes_stages.${mode}: "${stageName}" has no step "${step}"`);
      }
    }
  }
  if (audio.default && !values.includes(audio.default)) {
    errors.push(`settings.audio_mode.default "${audio.default}" is not one of its values`);
  }
  return errors;
}

// Returns error strings for any dependency cycle.
function findCycles(stages) {
  const errors = [];
  const state = new Map(); // 0 = unvisited, 1 = visiting, 2 = done
  const visit = (name, path) => {
    if (state.get(name) === 2) return;
    if (state.get(name) === 1) {
      errors.push(`dependency cycle: ${[...path, name].join(" -> ")}`);
      return;
    }
    state.set(name, 1);
    const stage = stages.get(name);
    for (const dep of [...(stage?.after ?? []), ...(stage?.after_optional ?? [])]) {
      if (stages.has(dep)) visit(dep, [...path, name]);
    }
    state.set(name, 2);
  };
  for (const name of stages.keys()) visit(name, []);
  return errors;
}

// True when `to` is an ancestor of `from` in the dependency graph.
function reachable(stages, from, to) {
  const seen = new Set();
  const stack = [...(stages.get(from)?.after ?? [])];
  while (stack.length) {
    const name = stack.pop();
    if (name === to) return true;
    if (seen.has(name)) continue;
    seen.add(name);
    stack.push(...(stages.get(name)?.after ?? []));
  }
  return false;
}
