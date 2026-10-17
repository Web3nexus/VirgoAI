# DEC-0006: AI Provider Adapter Layer, Model Registry & Model Router

- **Status**: APPROVED
- **Date**: 2026-10-15
- **Author**: OpenCode Agent
- **Target Path**: `apps/api/app/Ai/`, `apps/api/app/Models/Ai{Provider,Model}.php`,
  `apps/api/app/Models/{ProviderCapability,AiUsageRecord}.php`, `apps/api/config/virgoai.php`,
  `apps/api/app/Http/Controllers/Api/RegistryController.php`

## Context

Phase 2 requires a provider-independent integration layer: separate adapters per provider and
capability, a DB-backed model registry, and a model router that selects compatible, *configured*
models with fallback. The spec forbids forcing every provider into one identical internal request
shape, forbids exposing credentials to the frontend, and requires providers to be integrated and
verified individually (never inventing endpoints, capabilities, or model IDs).

## Decision

Build the provider layer as a **skeleton first, then integrate providers one at a time**.

1. **DB-backed registry** (migrations `ai_providers`, `ai_models`, `provider_capabilities`,
   `ai_usage_records`). Providers store normalised metadata and `credentials_key` (the *name* of the
   env variable holding the secret — never the secret itself). Models carry `pricing`/`meta` JSONB and
   capabilities (`Capability` enum: text, vision, image, video, audio, music, subtitle, upscale,
   embedding) with per-capability `constraints`.

2. **Adapter contract** (`App\Ai\Contracts\ProviderAdapter`): `key()`, `capabilities()`,
   `isConfigured()`, `generate(GenerationRequest): GenerationResult`. Each provider gets its own
   adapter and normalises auth, submission, polling, results, errors, usage, and cost itself —
   providers are **not** forced into one shared request shape.

3. **Adapter registry** (`AdapterRegistry`) maps provider keys → adapter classes, populated from
   `config/virgoai.php` (`virgoai.providers`). Real providers are registered there one at a time.

4. **Model router** (`ModelRouter`) selects usable models for a capability: model enabled + provider
   `Active` + capability present, ordered by priority, and filtered to providers whose adapter is
   registered **and** whose `isConfigured()` is true. Throws `NoSuitableModel` otherwise. The adapter
   — not the DB — is authoritative for credential presence.

5. **Credentials** live only in env/config (`config/virgoai.php` `credentials`), resolved by name.
   No key is ever serialised to the API; resources expose only a boolean `configured`.

6. **Dev adapter** `EchoAdapter` (offline, deterministic, zero-cost) plus an `AiRegistrySeeder` gated
   to local/testing, so the router and workflow stages are exercisable without credentials. It is
   explicitly non-production.

7. **Catalog API**: `GET /api/v1/providers` and `GET /api/v1/models` (Sanctum) expose providers and
   models with capabilities — never secrets.

## Consequences

- Adding a provider = write its adapter, register it in `config/virgoai.php`, seed its models, set its
  env credential. No changes to the router, workflow engine, or API are required.
- `EchoAdapter` and the seeded `echo` provider exist only in local/testing; production routing
  requires a real configured provider.
- 12 new feature tests (router selection/ordering/fallback, adapter resolution, credential handling,
  catalog endpoints); **51 tests / 120 assertions total**.
- Cost/usage tables exist; budget enforcement and usage logging hooks are Phase 2 continuation.

## Alternatives Considered

- **One universal internal request shape for all providers** — rejected by the spec; providers differ
  too much in submission/polling/result semantics.
- **Credentials in the DB** — rejected; secrets stay in env/config, DB holds only the key name.
- **Routing on DB `credentials_key` alone** — rejected; the adapter owns its own configuration check.
- **Integrating a real provider now** — deferred; providers are integrated and verified one at a time.
