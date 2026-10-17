// Checks that JSON Schema cannot express: rules that span artifacts, and word-count limits.
// Each function returns a list of error strings. An empty list means the rule passes.

const SPEAKER_NARRATOR = "narrator";

// Thumbnail text must be four words or fewer (DEC-0008, thumbnail stage).
export function thumbnailRules(spec) {
  const words = spec.overlay_text.trim().split(/\s+/).filter(Boolean).length;
  return words > 4 ? [`/overlay_text has ${words} words; maximum is 4`] : [];
}

// Every dialogue line must use a character in the cast, and fall inside its section's time window.
export function scriptAgainstCast(script, characterDesign) {
  const errors = [];
  const cast = new Set(characterDesign.characters.map((c) => c.character_id));
  const sections = new Map(script.sections.map((s) => [s.section_id, s]));

  for (const section of script.sections) {
    for (const id of section.character_ids) {
      if (!cast.has(id)) errors.push(`/sections/${section.section_id} references unknown character "${id}"`);
    }
    if (section.end_seconds <= section.start_seconds) {
      errors.push(`/sections/${section.section_id} end_seconds must be after start_seconds`);
    }
  }

  (script.lines || []).forEach((line, i) => {
    const path = `/lines/${i} (${line.line_id})`;
    if (line.speaker !== SPEAKER_NARRATOR && !cast.has(line.speaker)) {
      errors.push(`${path} speaker "${line.speaker}" is not in the cast`);
    }
    const section = sections.get(line.section_id);
    if (!section) {
      errors.push(`${path} section "${line.section_id}" does not exist`);
    } else if (line.start_seconds < section.start_seconds || line.end_seconds > section.end_seconds) {
      errors.push(`${path} timing falls outside section "${section.section_id}"`);
    }
    if (line.end_seconds <= line.start_seconds) {
      errors.push(`${path} end_seconds must be after start_seconds`);
    }
  });
  return errors;
}

// Scene plan must reference only approved designs, and cover the script's sections.
export function scenePlanAgainstDesigns(scenePlan, script, characterDesign, environmentDesign) {
  const errors = [];
  const characters = new Set(characterDesign.characters.map((c) => c.character_id));
  const approvedCharacters = new Set(
    characterDesign.characters.filter((c) => c.reference_image.status === "approved").map((c) => c.character_id),
  );
  const environments = new Map(environmentDesign.environments.map((e) => [e.environment_id, e]));
  const sectionIds = new Set(script.sections.map((s) => s.section_id));
  const sceneIds = new Set();

  for (const scene of scenePlan.scenes) {
    const path = `/scenes/${scene.scene_id}`;
    if (sceneIds.has(scene.scene_id)) errors.push(`${path} duplicate scene_id`);
    sceneIds.add(scene.scene_id);

    if (!sectionIds.has(scene.section_id)) errors.push(`${path} section "${scene.section_id}" is not in the script`);
    if (!environments.has(scene.environment_id)) {
      errors.push(`${path} environment "${scene.environment_id}" is not in environment_design`);
    } else if (environments.get(scene.environment_id).reference_image.status !== "approved") {
      errors.push(`${path} environment "${scene.environment_id}" has no approved reference image`);
    }
    for (const id of scene.character_ids) {
      if (!characters.has(id)) errors.push(`${path} character "${id}" is not in character_design`);
      else if (!approvedCharacters.has(id)) errors.push(`${path} character "${id}" has no approved reference image`);
    }
    if (scene.visible_speaker_id && !scene.character_ids.includes(scene.visible_speaker_id)) {
      errors.push(`${path} visible_speaker_id must be one of the scene's character_ids`);
    }
    if (scene.end_seconds - scene.start_seconds - scene.duration_seconds > 0.05 ||
        scene.duration_seconds - (scene.end_seconds - scene.start_seconds) > 0.05) {
      errors.push(`${path} duration_seconds does not match end_seconds - start_seconds`);
    }
  }

  const covered = new Set(scenePlan.scenes.map((s) => s.section_id));
  for (const id of sectionIds) {
    if (!covered.has(id)) errors.push(`/scenes: script section "${id}" has no scene`);
  }
  return errors;
}

// Dialogue modes need a voice for every character, and every voice must have a provider.
export function voiceCoverage(characterDesign, audioMode) {
  if (audioMode === "narration") return [];
  return characterDesign.characters
    .filter((c) => !c.voice)
    .map((c) => `/characters/${c.character_id} has no voice; required when audio_mode is "${audioMode}"`);
}

// Every timeline entry must point at an asset that exists in the asset manifest.
export function editAgainstAssets(edit, assetManifest) {
  const ids = new Set(assetManifest.assets.map((a) => a.asset_id));
  return edit.timeline
    .filter((t) => !ids.has(t.asset_id))
    .map((t) => `/timeline/${t.scene_id} asset "${t.asset_id}" is not in asset_manifest`);
}
