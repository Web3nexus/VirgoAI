// Loads the artifact JSON Schemas once and validates artifacts against them.
// Schema files live in ../schemas and are named <artifact>.schema.json.

import { readdirSync, readFileSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
import Ajv2020 from "ajv/dist/2020.js";
import addFormats from "ajv-formats";

const SCHEMA_DIR = join(dirname(fileURLToPath(import.meta.url)), "..", "schemas");

const ajv = new Ajv2020({ allErrors: true, strict: true });
addFormats(ajv);
const validators = new Map();

for (const file of readdirSync(SCHEMA_DIR)) {
  if (!file.endsWith(".schema.json")) continue;
  const name = file.replace(/\.schema\.json$/, "");
  const schema = JSON.parse(readFileSync(join(SCHEMA_DIR, file), "utf8"));
  validators.set(name, ajv.compile(schema));
}

export function artifactNames() {
  return [...validators.keys()].sort();
}

export function hasArtifact(name) {
  return validators.has(name);
}

// Returns { valid, errors } where errors is a list of "path: message" strings.
export function validateArtifact(name, data) {
  const validate = validators.get(name);
  if (!validate) {
    return { valid: false, errors: [`no schema for artifact "${name}"`] };
  }
  const valid = validate(data);
  const errors = valid
    ? []
    : validate.errors.map((e) => `${e.instancePath || "/"} ${e.message}`);
  return { valid: Boolean(valid), errors };
}
