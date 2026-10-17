import { test } from "node:test";
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
import { parse } from "yaml";
import { validateManifest } from "../src/manifest.js";

const here = dirname(fileURLToPath(import.meta.url));
const REAL = join(here, "..", "..", "..", "docs", "pipelines", "generative-video.yaml");

function loadReal() {
  return parse(readFileSync(REAL, "utf8"));
}

function stage(manifest, name) {
  return manifest.stages.find((s) => s.name === name);
}

test("the shipped generative-video manifest is valid", () => {
  assert.deepEqual(validateManifest(loadReal()), []);
});

test("a dependency cycle is reported", () => {
  const m = loadReal();
  stage(m, "intake").after = ["publish"];
  const errors = validateManifest(m);
  assert.ok(errors.some((e) => e.includes("dependency cycle")), errors.join("\n"));
});

test("a stage that depends on an unknown stage is reported", () => {
  const m = loadReal();
  stage(m, "script").after = ["nonexistent"];
  assert.ok(validateManifest(m).some((e) => e.includes('"nonexistent" is not a stage')));
});

test("removing the proposal gate is reported", () => {
  const m = loadReal();
  stage(m, "proposal").gate = false;
  assert.ok(validateManifest(m).some((e) => e.includes('"proposal" must gate')));
});

test("removing the storyboard (keyframes) gate is reported", () => {
  const m = loadReal();
  stage(m, "keyframes").gate = false;
  const errors = validateManifest(m);
  assert.ok(errors.some((e) => e.includes("keyframes")), errors.join("\n"));
});

test("motion that does not depend on keyframes is reported", () => {
  const m = loadReal();
  stage(m, "motion").after = [];
  assert.ok(validateManifest(m).some((e) => e.includes('"motion" must run after "keyframes"')));
});

test("an artifact with no schema is reported", () => {
  const m = loadReal();
  stage(m, "compose").produces = ["unknown_artifact"];
  assert.ok(validateManifest(m).some((e) => e.includes('"unknown_artifact" but no schema')));
});

test("a settings change that names a missing step is reported", () => {
  const m = loadReal();
  m.settings.audio_mode.changes_stages.narration = ["audio.generate_nothing"];
  assert.ok(validateManifest(m).some((e) => e.includes('has no step "generate_nothing"')));
});

test("a settings mode with no mapping is reported", () => {
  const m = loadReal();
  delete m.settings.audio_mode.changes_stages.both;
  assert.ok(validateManifest(m).some((e) => e.includes('no changes_stages entry for "both"')));
});

test("a stage without review focus is reported", () => {
  const m = loadReal();
  stage(m, "edit").review_focus = [];
  assert.ok(validateManifest(m).some((e) => e.includes('"edit": review_focus')));
});
