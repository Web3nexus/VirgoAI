#!/usr/bin/env node
// CLI: validates one or more pipeline manifests.
//   node src/validate-manifest.js ../../docs/pipelines/generative-video.yaml
// Exits 1 if any manifest has errors, so CI can gate on it.

import { readFileSync } from "node:fs";
import { basename } from "node:path";
import { parse } from "yaml";
import { validateManifest } from "./manifest.js";

export function checkManifestFile(path) {
  const manifest = parse(readFileSync(path, "utf8"));
  return validateManifest(manifest);
}

if (import.meta.url === `file://${process.argv[1]}`) {
  const files = process.argv.slice(2);
  if (files.length === 0) {
    console.error("usage: validate-manifest.js <manifest.yaml> [...]");
    process.exit(2);
  }
  let failed = false;
  for (const file of files) {
    const errors = checkManifestFile(file);
    if (errors.length === 0) {
      console.log(`ok   ${basename(file)}`);
    } else {
      failed = true;
      console.log(`FAIL ${basename(file)}`);
      for (const e of errors) console.log(`  - ${e}`);
    }
  }
  process.exit(failed ? 1 : 0);
}
