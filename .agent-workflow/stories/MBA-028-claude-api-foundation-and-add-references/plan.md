# Plan — MBA-028-claude-api-foundation-and-add-references

> Design, don't implement. Every helper named below is consumed by a task; nothing ships speculative.

## Approach

Introduce a new `App\Domain\AI` domain that owns a thin `ClaudeClient` (HTTP wrapper around Anthropic's REST endpoint, no third-party SDK — minimises dependency footprint and keeps caching/retry behaviour explicit), a versioned in-code prompt registry, and an `ai_calls` audit log. On top of that foundation, ship the first concrete service `AddReferencesAction` plus the sync admin endpoint and the async batch scaffold. Add a sibling `App\Domain\LanguageSettings` domain owning the new `language_settings` table, super-admin CRUD, and a public read endpoint; widen the existing `Language` enum to seven ISO-2 codes so the rest of the app converges on one source of truth.

## Domain

| Item | Role |
|---|---|
| `App\Domain\Shared\Enums\Language` (extend) | Add `Es`, `Fr`, `De`, `It` cases so the enum matches the seven seeded languages. |
| `App\Domain\AI\Models\AiCall` | Eloquent model for the `ai_calls` audit row; polymorphic `subject` via `subject_type` / `subject_id`; cast `status` to `AiCallStatus` enum; `$guarded = []`. |
| `App\Domain\AI\Enums\AiCallStatus` | `Ok`, `Error`, `Timeout`. |
| `App\Domain\LanguageSettings\Models\LanguageSetting` | One row per ISO-2 language; route key `language`; `belongsTo` `BibleVersion`, `Commentary`, `DevotionalType` (each nullable). |

## Migrations

| File | Role |
|---|---|
| `create_ai_calls_table` | Columns and indexes per AC §3 (`prompt_version`, `prompt_name`, `model`, token counters, `latency_ms`, `status`, `error_message`, polymorphic `subject_*`, `triggered_by_user_id`, `created_at`); composite indexes `(prompt_name, created_at)`, `(subject_type, subject_id)`, `(triggered_by_user_id, created_at)`. No `updated_at`. |
| `create_language_settings_table` | `id`, `language CHAR(2) UNIQUE`, three nullable FKs (`default_bible_version_id` → `bible_versions`, `default_commentary_id` → `commentaries`, `default_devotional_type_id` → `devotional_types`) all `ON DELETE SET NULL`, timestamps. |
| `seed_language_settings_for_iso2_codes` | Data-only migration inserting one row per `ro, en, hu, es, fr, de, it` with default_* NULL. Idempotent (`upsert` on `language`). |

## Actions / DTOs

| Item | Role |
|---|---|
| `App\Domain\AI\DataTransferObjects\AddReferencesInput` | `readonly`: `string $html`, `string $language`, `?string $bibleVersionAbbreviation`, `?string $subjectType`, `?int $subjectId`. |
| `App\Domain\AI\DataTransferObjects\AddReferencesOutput` | `readonly`: `string $html`, `int $referencesAdded`, `string $promptVersion`, `int $aiCallId`. |
| `App\Domain\AI\DataTransferObjects\ClaudeRequest` | `readonly` envelope passed into `ClaudeClient`: `string $promptName`, `string $promptVersion`, `string $model`, `string $systemPrompt` (cached), `string $userMessage`, `?string $subjectType`, `?int $subjectId`. |
| `App\Domain\AI\DataTransferObjects\ClaudeResponse` | `readonly`: `string $content`, `int $inputTokens`, `int $outputTokens`, `int $cacheCreationInputTokens`, `int $cacheReadInputTokens`, `int $latencyMs`, `AiCallStatus $status`, `?string $errorMessage`, `int $aiCallId`. |
| `App\Domain\AI\Clients\ClaudeClient` | Sends a `ClaudeRequest` to Anthropic's `/v1/messages` endpoint via Laravel's `Http` facade with `anthropic-version` header, marks the system block with `cache_control: { type: 'ephemeral' }`, retries `429` + `5xx` up to `config('ai.retry.max_attempts')` with exponential backoff sourced from config, persists an `AiCall` row on every outcome (success, error, timeout), and returns a `ClaudeResponse`. No business logic. |
| `App\Domain\AI\Prompts\Prompt` | Abstract base: `public const VERSION` and `public const NAME`, plus methods `systemPrompt(): string` and `userMessage(array $payload): string`. Subclasses are stateless — heredoc strings only. |
| `App\Domain\AI\Prompts\PromptRegistry` | `get(string $name, string $version): Prompt`. Throws `App\Domain\AI\Exceptions\UnknownPromptException` when the pair is not registered. Pin-by-version only — no implicit "latest". |
| `App\Domain\AI\Prompts\AddReferences\V1` | First concrete prompt; `NAME = 'add_references'`, `VERSION = '1.0.0'`. System prompt embeds the USFM book list (reuse `BibleBookCatalog::BOOKS` keys) and the canonical reference format spec. User message renders the target language and Bible version slug, then the input HTML. |
| `App\Domain\AI\Actions\AddReferencesAction` | Orchestrates one synchronous call. Resolves the Bible version (input → `LanguageSetting::default_bible_version` → fallback constant `VDC`); fetches the V1 prompt via the registry; calls `ClaudeClient`; pipes the response HTML through `AddedReferencesValidator` to strip invalid links; returns `AddReferencesOutput`. On `502`-equivalent client failure, throws `App\Domain\AI\Exceptions\ClaudeUnavailableException`. |
| `App\Domain\AI\Support\AddedReferencesValidator` | Walks the response HTML (DOM-based, not regex), accepts only `<a class="reference" href="…">` whose href parses through `App\Domain\Reference\Parser\ReferenceParser`; strips the tag (keeping inner text) and writes one `security_events` row per stripped link. Returns `{ html, referencesAdded }`. |
| `App\Domain\AI\Support\AddReferencesVersionResolver` | Pure helper consumed only by `AddReferencesAction`: input abbreviation → settings default → fallback `VDC`. Returns the resolved abbreviation; throws when the resolved abbreviation does not match any `BibleVersion` row. |
| `App\Domain\LanguageSettings\Actions\ListLanguageSettingsAction` | Returns all rows with `default_*` relations eager-loaded for the admin index. |
| `App\Domain\LanguageSettings\Actions\UpdateLanguageSettingAction` | Accepts `UpdateLanguageSettingInput` DTO; persists the three nullable FKs for one language; returns the refreshed model. |
| `App\Domain\LanguageSettings\Actions\ShowPublicLanguageSettingAction` | Returns the row for one language with only the safe-to-publish fields hydrated (used by the public endpoint resource). |
| `App\Domain\LanguageSettings\DataTransferObjects\UpdateLanguageSettingInput` | `readonly`: `string $language`, `?string $defaultBibleVersionAbbreviation`, `?int $defaultCommentaryId`, `?int $defaultDevotionalTypeId`. |
| `App\Application\Jobs\AddReferencesBatchJob` | Async job dispatched from the batch endpoint. Receives a target collection descriptor (subject type, slug/id, optional book filter) plus the originating `import_jobs.id`; iterates rows, calls `AddReferencesAction` per row inside a transaction, updates the `import_jobs` progress and final status. Uses the existing `database` queue (Horizon arrives in MBA-031). |

## HTTP / Endpoints

| Method | Path | Controller | Form Request | Resource | Auth |
|---|---|---|---|---|---|
| POST | `/api/v1/admin/ai/add-references` | `Admin\Ai\AddReferencesController` (invokable) | `AddReferencesRequest` | `AddReferencesResource` | `auth:sanctum` + `super-admin` |
| POST | `/api/v1/admin/ai/add-references/batch` | `Admin\Ai\AddReferencesBatchController` (invokable) | `AddReferencesBatchRequest` | `ImportJobResource` (existing) | `auth:sanctum` + `super-admin` |
| GET | `/api/v1/admin/language-settings` | `Admin\LanguageSettings\ListLanguageSettingsController` (invokable) | — | `LanguageSettingResource` | `auth:sanctum` + `super-admin` |
| PATCH | `/api/v1/admin/language-settings/{language}` | `Admin\LanguageSettings\UpdateLanguageSettingController` (invokable) | `UpdateLanguageSettingRequest` | `LanguageSettingResource` | `auth:sanctum` + `super-admin` |
| GET | `/api/v1/language-settings/{language}` | `LanguageSettings\ShowLanguageSettingController` (invokable) | — | `PublicLanguageSettingResource` | none (public) |

Route-model binding: `{language}` is a CHAR(2) string, not a numeric id — bind via `Route::pattern('language', '[a-z]{2}')` and resolve inside the controller with `LanguageSetting::query()->where('language', $language)->firstOrFail()` to keep the route key explicit and avoid silent 404s on uppercase input. The `LanguageSetting` model overrides `getRouteKeyName()` to `language` so implicit binding works for the PATCH route; the public GET uses the same.

Sync endpoint timeout follows the 60s requirement via `set_time_limit(60)` inside the action plus `Http::timeout(config('ai.request.timeout_seconds'))` on the client. Failure mapping: `ClaudeUnavailableException` → 502 with `Retry-After` header derived from the last upstream response.

## Resources

| Item | Role |
|---|---|
| `Http\Resources\AddReferencesResource` | Wraps `AddReferencesOutput`; exposes `html`, `references_added`, `prompt_version`, `ai_call_id`. |
| `Http\Resources\LanguageSettingResource` | Admin shape: `language`, full default_* objects (BibleVersion / Commentary / DevotionalType resources, nullable). |
| `Http\Resources\PublicLanguageSettingResource` | Public shape: `language`, `default_bible_version` (slug only), `default_commentary` (slug only). Devotional type omitted from public surface. |

## Form Requests

| Item | Role |
|---|---|
| `Http\Requests\Admin\Ai\AddReferencesRequest` | `html: required|string|max:200000`, `language: required|string|size:2|in:<7 codes>`, `bible_version_abbreviation: nullable|string|exists:bible_versions,abbreviation`, `subject_type: nullable|string|max:64`, `subject_id: nullable|integer|min:1`. Authorize via super-admin middleware (no body authz). |
| `Http\Requests\Admin\Ai\AddReferencesBatchRequest` | `subject_type: required|string|in:<allowed list>`, `subject_id: required|integer`, `language: required|string|size:2`, `filters: nullable|array`. |
| `Http\Requests\Admin\LanguageSettings\UpdateLanguageSettingRequest` | `default_bible_version_abbreviation: nullable|string|exists:…`, `default_commentary_id: nullable|integer|exists:commentaries,id`, `default_devotional_type_id: nullable|integer|exists:devotional_types,id`. |

## Config

| Key | Source | Notes |
|---|---|---|
| `config/ai.php` | New | `model.default = 'claude-sonnet-4-6'`; `request.timeout_seconds = 60`; `retry.max_attempts = 3`; `retry.backoff_ms = [500, 2000, 5000]`; `default_bible_version_fallback = 'VDC'`; `prompts` registry array `[name => [version => class]]`. |
| `config/services.php` | Extend | `anthropic.api_key = env('ANTHROPIC_API_KEY')`, `anthropic.api_url = env('ANTHROPIC_API_URL', 'https://api.anthropic.com')`, `anthropic.version = '2023-06-01'`. |
| `.env.example` | Extend | Document `ANTHROPIC_API_KEY`. |

## Events

None this story — the audit log row is written by `ClaudeClient` directly, no listener fan-out is justified yet.

## Risks

- **Language enum widening**: tests across the suite may assert only the three current cases. Run `make test-api` after extending and fix call sites that use `Language::cases()` exhaustively.
- **DOM-based validator**: PHP's `DOMDocument` mangles HTML5 content — load with `LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD` and reserialise carefully. Prefer keeping inner-text fallback on stripped links so the user content survives.
- **Async path runs without Horizon**: until MBA-031 ships, the batch job runs on the existing `database` queue worker. Confirm `config/queue.php` retries allow the per-row latency.

## Deferred Extractions Tripwire impact

No counts incremented this story. The owner-`authorize()` block (4/5) is untouched: this story has no body-level ownership checks — middleware handles auth.

## Tasks

- [x] 1. Extend `App\Domain\Shared\Enums\Language` to add `Es`, `Fr`, `De`, `It` cases and update any exhaustive `match`/`switch` consumers the test suite surfaces.
- [x] 2. Create migration `create_ai_calls_table` with the columns and indexes specified above.
- [x] 3. Create migration `create_language_settings_table` with three nullable FKs (`ON DELETE SET NULL`) and a unique `language` constraint.
- [x] 4. Create data migration `seed_language_settings_for_iso2_codes` inserting one row per `ro, en, hu, es, fr, de, it`, idempotent.
- [x] 5. Add `AiCallStatus` enum and `AiCall` Eloquent model with polymorphic `subject` and the required casts.
- [x] 6. Add `LanguageSetting` Eloquent model with `getRouteKeyName='language'` and three `belongsTo` relations.
- [x] 7. Add `config/ai.php` with the keys listed above and extend `config/services.php` with `anthropic.*`; document `ANTHROPIC_API_KEY` in `.env.example`.
- [x] 8. Implement `ClaudeRequest`, `ClaudeResponse` DTOs and the `ClaudeClient` HTTP wrapper (cache-control on system prompt, retry-with-backoff, per-call `AiCall` row write).
- [x] 9. Implement `Prompt` abstract base, `PromptRegistry`, and `UnknownPromptException`; wire the registry from `config('ai.prompts')`.
- [x] 10. Implement `Prompts\AddReferences\V1` (USFM book list + reference format spec in system prompt; HTML + language + version in user message).
- [x] 11. Implement `AddReferencesVersionResolver`, `AddedReferencesValidator`, the `AddReferencesInput`/`AddReferencesOutput` DTOs, and `AddReferencesAction`; throw `ClaudeUnavailableException` on upstream failure.
- [x] 12. Add `ListLanguageSettingsAction`, `UpdateLanguageSettingAction`, `ShowPublicLanguageSettingAction`, and the `UpdateLanguageSettingInput` DTO.
- [x] 13. Add Form Requests `AddReferencesRequest`, `AddReferencesBatchRequest`, `UpdateLanguageSettingRequest` (validation rules per the table above).
- [x] 14. Add API Resources `AddReferencesResource`, `LanguageSettingResource`, `PublicLanguageSettingResource`.
- [x] 15. Add invokable controllers `Admin\Ai\AddReferencesController`, `Admin\Ai\AddReferencesBatchController`, `Admin\LanguageSettings\ListLanguageSettingsController`, `Admin\LanguageSettings\UpdateLanguageSettingController`, `LanguageSettings\ShowLanguageSettingController`; controllers delegate to actions only.
- [x] 16. Implement `App\Application\Jobs\AddReferencesBatchJob` running on the `database` queue, iterating target rows, calling the action, and updating the `import_jobs` progress/status.
- [x] 17. Register routes in `routes/api.php` under `/api/v1` with `auth:sanctum` + `super-admin` (admin) and the public read; configure `Route::pattern('language', '[a-z]{2}')`.
- [x] 18. Map `ClaudeUnavailableException` to a 502 JSON envelope with `Retry-After` in `bootstrap/app.php`.
- [x] 19. Add factories: `AiCallFactory`, `LanguageSettingFactory`.
- [x] 20. Unit tests for `ClaudeClient`: cache-control payload shape on system block, retry+backoff on 429, `AiCall` row populated with cache-hit and cache-miss token fields on every call (Http::fake).
- [x] 21. Unit tests for `AddReferencesVersionResolver` (input > settings > VDC fallback) and `AddedReferencesValidator` (strips links missing `class="reference"`; strips links whose href fails to parse; counts surviving references).
- [x] 22. Unit tests for `AddReferencesAction` with a fake `ClaudeClient`: end-to-end orchestration covering happy path, version-resolution chain, invalid-link stripping, and `references_added` accuracy.
- [x] 23. Unit tests for `PromptRegistry` (pinned lookup, missing pair throws).
- [x] 24. Feature tests for `POST /api/v1/admin/ai/add-references`: 401 (no token), 403 (admin without `is_super`), 422 (missing `html` / `language`), happy path with `Http::fake`, 502 on Claude failure.
- [x] 25. Feature test for `POST /api/v1/admin/ai/add-references/batch` asserting 202 + `import_jobs.id` shape, and that `AddReferencesBatchJob` processes target rows when run synchronously under `Bus::fake()->dispatchAfterResponse()` semantics (or `$job->handle()` directly with action mocked).
- [x] 26. Feature tests for the language-settings endpoints: admin list (super-admin), admin patch (validation + persistence), public read (returns slug-only payload), 403 for non-super admin on admin routes.
- [x] 27. Migration test asserting `language_settings` is seeded with all 7 ISO-2 codes after `migrate --seed`.
- [x] 28. Update story status `draft` → `planned`.
