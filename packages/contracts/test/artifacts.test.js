import { test } from "node:test";
import assert from "node:assert/strict";
import { artifactNames, validateArtifact } from "../src/schemas.js";
import {
  thumbnailRules,
  scriptAgainstCast,
  scenePlanAgainstDesigns,
  voiceCoverage,
  editAgainstAssets,
} from "../src/rules.js";

const IDENTITY = "Mia, 30, short red hair, green raincoat, freckles, carries a brass compass";
const PROMPT = "A rain-soaked kitchen at dusk with a single warm lamp over a wooden table";

const fixtures = {
  brief: {
    version: "1.0",
    title: "Compass",
    input: { type: "idea", value: "A woman follows a compass home" },
    audio_mode: "narration",
    target_duration_seconds: 60,
    platform: "youtube",
  },
  research_brief: {
    version: "1.0",
    summary: "Context on navigation",
    no_research_needed: true,
    data_points: [],
    sources: [],
  },
  proposal_packet: {
    version: "1.0",
    concepts: [1, 2, 3].map((n) => ({
      concept_id: `c${n}`,
      title: `Concept ${n}`,
      hook: "hook",
      structure: "structure",
      audience: "audience",
      duration_seconds: 60,
    })),
    selected_concept_id: "c1",
    audio_mode: "narration",
    cost_estimate: {
      currency: "USD",
      total_usd: 3,
      line_items: [
        { stage: "keyframes", capability: "image", unit_count: 6, unit_cost_usd: 0.5, subtotal_usd: 3 },
      ],
    },
    approval: { status: "pending" },
  },
  script: {
    version: "1.0",
    title: "Compass",
    audio_mode: "narration",
    sections: [
      {
        section_id: "hook",
        purpose: "hook",
        environment_id: "kitchen",
        character_ids: ["mia"],
        start_seconds: 0,
        end_seconds: 10,
      },
    ],
  },
  character_design: {
    version: "1.0",
    characters: [
      {
        character_id: "mia",
        name: "Mia",
        identity_block: IDENTITY,
        reference_image: { asset_id: "ref_mia", status: "approved" },
        voice: { provider_key: "kokoro", voice_id: "af_bella", description: "warm, mid pitch" },
      },
    ],
  },
  environment_design: {
    version: "1.0",
    environments: [
      {
        environment_id: "kitchen",
        name: "Kitchen",
        prompt: PROMPT,
        lighting: "warm lamp",
        time_of_day: "dusk",
        palette: ["#A1B2C3"],
        reference_image: { asset_id: "ref_kitchen", status: "approved" },
      },
    ],
  },
  scene_plan: {
    version: "1.0",
    scenes: [
      {
        scene_id: "s1",
        section_id: "hook",
        start_seconds: 0,
        end_seconds: 10,
        duration_seconds: 10,
        character_ids: ["mia"],
        visible_speaker_id: "mia",
        environment_id: "kitchen",
        shot_type: "medium",
        keyframe_prompt: "Mia stands in the kitchen doorway holding a brass compass",
        motion_prompt: "Slow push in toward Mia",
      },
    ],
  },
  asset_manifest: {
    version: "1.0",
    assets: [
      {
        asset_id: "k1",
        kind: "keyframe",
        scene_id: "s1",
        path: "assets/k1.png",
        provider_key: "image_provider",
        model_id: "model-a",
        cost_usd: 0.5,
        status: "generated",
      },
    ],
  },
  edit_decisions: {
    version: "1.0",
    render_runtime: "remotion",
    output: { width: 1920, height: 1080, fps: 30 },
    timeline: [{ scene_id: "s1", asset_id: "k1", track: "video", in_seconds: 0, out_seconds: 10 }],
  },
  render_report: {
    version: "1.0",
    output_path: "renders/final.mp4",
    format: "mp4",
    width: 1920,
    height: 1080,
    fps: 30,
    duration_seconds: 60,
    runtime: "remotion",
  },
  thumbnail_spec: {
    version: "1.0",
    concept: "Mia holding the compass in the rain",
    overlay_text: "Follow The Compass",
    prompt: "Close up of Mia with a glowing brass compass, dramatic rim light",
    character_ids: ["mia"],
    size: { width: 1280, height: 720 },
  },
  review: {
    version: "1.0",
    stage: "final_review",
    decision: "revise",
    findings: [
      { severity: "critical", target: "/scenes/s1", summary: "Mia's face changes", proposed_fix: "Regenerate s1 keyframe" },
    ],
  },
  cost_log: {
    version: "1.0",
    budget_usd: 20,
    entries: [
      { entry_id: "e1", stage: "keyframes", tool: "image_provider", capability: "image", status: "estimated", estimated_usd: 3 },
    ],
  },
  publish_log: {
    version: "1.0",
    entries: [{ platform: "youtube", status: "scheduled" }],
  },
};

test("every manifest-referenced artifact has a schema", () => {
  for (const name of Object.keys(fixtures)) {
    assert.ok(artifactNames().includes(name), `schema missing for ${name}`);
  }
});

for (const [name, data] of Object.entries(fixtures)) {
  test(`valid ${name} passes its schema`, () => {
    const result = validateArtifact(name, data);
    assert.equal(result.valid, true, result.errors.join("; "));
  });
}

test("dialogue script without lines is rejected", () => {
  const script = { ...fixtures.script, audio_mode: "dialogue" };
  assert.equal(validateArtifact("script", script).valid, false);
});

test("dialogue script with lines passes", () => {
  const script = {
    ...fixtures.script,
    audio_mode: "dialogue",
    lines: [
      { line_id: "l1", section_id: "hook", speaker: "mia", text: "Hello", start_seconds: 1, end_seconds: 3 },
    ],
  };
  assert.equal(validateArtifact("script", script).valid, true);
});

test("critical review finding without proposed_fix is rejected (CHAI rule)", () => {
  const review = structuredClone(fixtures.review);
  delete review.findings[0].proposed_fix;
  assert.equal(validateArtifact("review", review).valid, false);
});

test("accepted review cannot carry a critical finding", () => {
  const review = { ...structuredClone(fixtures.review), decision: "accept" };
  assert.equal(validateArtifact("review", review).valid, false);
});

test("research brief with research needed must have 3 sourced data points", () => {
  const brief = {
    ...fixtures.research_brief,
    no_research_needed: false,
    data_points: [{ claim: "a", source_url: "https://example.com/a" }],
    sources: [{ url: "https://example.com/a", title: "A" }],
  };
  assert.equal(validateArtifact("research_brief", brief).valid, false);
});

test("thumbnail overlay text longer than 40 characters is rejected by the schema", () => {
  const spec = { ...fixtures.thumbnail_spec, overlay_text: "x".repeat(41) };
  assert.equal(validateArtifact("thumbnail_spec", spec).valid, false);
});

test("unknown artifact name fails clearly", () => {
  assert.equal(validateArtifact("does_not_exist", {}).valid, false);
});

test("thumbnail text of more than four words fails the rule", () => {
  assert.equal(thumbnailRules({ overlay_text: "One Two Three Four Five" }).length, 1);
  assert.deepEqual(thumbnailRules({ overlay_text: "Four Words Right Here" }), []);
});

test("dialogue line with a speaker outside the cast fails", () => {
  const script = {
    ...fixtures.script,
    audio_mode: "dialogue",
    lines: [{ line_id: "l1", section_id: "hook", speaker: "ghost", text: "Boo", start_seconds: 1, end_seconds: 2 }],
  };
  const errors = scriptAgainstCast(script, fixtures.character_design);
  assert.ok(errors.some((e) => e.includes("ghost")));
});

test("dialogue line outside its section window fails", () => {
  const script = {
    ...fixtures.script,
    lines: [{ line_id: "l1", section_id: "hook", speaker: "mia", text: "Late", start_seconds: 9, end_seconds: 12 }],
  };
  const errors = scriptAgainstCast(script, fixtures.character_design);
  assert.ok(errors.some((e) => e.includes("outside section")));
});

test("scene plan using an unapproved character fails", () => {
  const cd = structuredClone(fixtures.character_design);
  cd.characters[0].reference_image.status = "pending";
  const errors = scenePlanAgainstDesigns(fixtures.scene_plan, fixtures.script, cd, fixtures.environment_design);
  assert.ok(errors.some((e) => e.includes("no approved reference")));
});

test("scene plan must cover every script section", () => {
  const script = {
    ...fixtures.script,
    sections: [...fixtures.script.sections, { ...fixtures.script.sections[0], section_id: "build", purpose: "build", start_seconds: 10, end_seconds: 20 }],
  };
  const errors = scenePlanAgainstDesigns(fixtures.scene_plan, script, fixtures.character_design, fixtures.environment_design);
  assert.ok(errors.some((e) => e.includes('"build" has no scene')));
});

test("dialogue mode requires a voice for every character", () => {
  const cd = structuredClone(fixtures.character_design);
  delete cd.characters[0].voice;
  assert.equal(voiceCoverage(cd, "dialogue").length, 1);
  assert.deepEqual(voiceCoverage(cd, "narration"), []);
});

test("edit timeline pointing at a missing asset fails", () => {
  const edit = structuredClone(fixtures.edit_decisions);
  edit.timeline[0].asset_id = "missing";
  assert.equal(editAgainstAssets(edit, fixtures.asset_manifest).length, 1);
});
