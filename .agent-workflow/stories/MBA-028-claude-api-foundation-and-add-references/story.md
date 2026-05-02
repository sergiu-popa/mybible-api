# Story: MBA-028-claude-api-foundation-and-add-references

## Title

Establish the Anthropic Claude API integration foundation in the API
project, with prompt caching, versioned prompts, language settings (default
Bible version per language, super-admin gated), and the first concrete AI
service: HTML-in / HTML-out reference linker.

## Status

`draft`

## Description

Several upcoming features (commentary AI correction, devotional reference
linking, SS content blocks reference linking) all need to call Claude with
the same shape of system prompt: a static list of USFM book abbreviations,
the canonical reference format spec
(`BOOK.CH:V[-CH:V][.VER]`), and the per-language default Bible version.
Centralising the integration in the API (rather than in admin or per
feature) gives us:

- A single Anthropic API key (`apps/api/.env`).
- Shared prompt caching (the system prompt is large and identical across
  features — high cache-hit ratio).
- One audit log of AI calls (cost accounting, rate limits).
- Reusability from CLI batch commands, queued jobs, and admin button
  clicks alike.

This story builds:

1. The Claude integration foundation: SDK install, config, request
   wrapper with prompt caching, versioned prompt registry, audit log,
   admin-only API gating.
2. The `language_settings` table (super-admin) — currently the only
   setting is `default_bible_version_id` per language, but the table is
   designed to accommodate future settings (default commentary, default
   devotional type, etc.).
3. The first concrete AI service: **AddReferences** — takes HTML in,
   returns HTML with `<a class="reference" href="BOOK.CH:V.VER">…</a>`
   wrapping detected Bible references. Synchronous endpoint for
   per-row use; async batch via Horizon (MBA-031 introduces Horizon).

The commentary-specific AI workflow (correction, translation, error
reports, SQLite export) is in MBA-029. This story is the bedrock under it.

## Acceptance Criteria

### Foundation

1. `anthropic-ai/sdk` (or the official PHP equivalent) installed via
   composer; `ANTHROPIC_API_KEY` env var documented in `.env.example`.
   Default model: **`claude-sonnet-4-6`** for cost-balanced per-row
   calls; the prompt registry can override per use case
   (e.g. translation may pin `claude-opus-4-7`).
2. `App\Domain\AI\Clients\ClaudeClient` — thin wrapper around the SDK
   that handles:
   - Bearer auth from config.
   - Prompt caching: the system prompt and the static USFM book list are
     marked `cache_control: { type: 'ephemeral' }` on every call. The
     per-call user message is cache-bypass.
   - Retry with exponential backoff on `429` / `5xx` (max 3 attempts).
   - Token usage logging (cache hit / miss / input / output tokens).
3. `ai_calls` table — audit log:
   - `id`, `prompt_version VARCHAR(20)`, `prompt_name VARCHAR(64)`,
     `model VARCHAR(64)`, `input_tokens INT`, `output_tokens INT`,
     `cache_creation_input_tokens INT`, `cache_read_input_tokens INT`,
     `latency_ms INT`, `status VARCHAR(16)` (`ok`, `error`, `timeout`),
     `error_message TEXT NULL`, `subject_type VARCHAR(64) NULL`,
     `subject_id BIGINT UNSIGNED NULL` (polymorphic to the row that
     triggered the call — `commentary_text`, `devotional`, etc.),
     `triggered_by_user_id INT UNSIGNED NULL`, `created_at TIMESTAMP`.
   - Indexes: `(prompt_name, created_at)`, `(subject_type, subject_id)`,
     `(triggered_by_user_id, created_at)`.

### Versioned prompt registry

4. `App\Domain\AI\Prompts\Prompt` — abstract base with
   `public const VERSION` (string, semver-ish) and methods to render the
   system + user messages. Subclasses live in
   `App\Domain\AI\Prompts\<Name>\V<n>.php`.
5. First concrete prompts shipped:
   - `App\Domain\AI\Prompts\AddReferences\V1` — produces HTML with
     reference links from input HTML, given a target language and
     default Bible version.
6. Prompts are dispatched via a registry keyed by `(name, version)`.
   Calling code pins the version explicitly (no implicit "latest"); a
   deprecated prompt remains callable until manually removed. Each row
   that consumed a prompt records the version used (e.g.
   `commentary_text.ai_referenced_prompt_version`).
7. Prompt content is stored as PHP code (templated via heredoc), not as
   external files. This keeps prompts under version control with the
   code that depends on them and prevents drift between dev and prod.

### Language settings (super-admin)

8. `language_settings` table:
   - `id`, `language CHAR(2) UNIQUE`, `default_bible_version_id BIGINT
     UNSIGNED NULL` FK (`ON DELETE SET NULL`),
     `default_commentary_id BIGINT UNSIGNED NULL` FK (added now,
     consumed by MBA-029),
     `default_devotional_type_id BIGINT UNSIGNED NULL` FK (added now,
     consumed by frontend), timestamps.
9. Seed rows for `ro, en, hu, es, fr, de, it` with `default_*` NULL —
   super-admin sets them via UI.
10. Endpoints:
    - `GET /api/v1/admin/language-settings` — list (super-admin only).
    - `PATCH /api/v1/admin/language-settings/{language}` — update one
      language's settings.
    - Public `GET /api/v1/language-settings/{language}` — exposes only
      the safe-to-publish settings (`default_bible_version` slug,
      `default_commentary` slug). Used by frontend post-login modal and
      mobile cold-start.

### AI Add-References service

11. `App\Domain\AI\Actions\AddReferencesAction` — accepts a
    `AddReferencesInput { html: string, language: string,
    bible_version_abbreviation: ?string, subject_type: ?string,
    subject_id: ?int }` DTO and returns
    `AddReferencesOutput { html: string, references_added: int,
    prompt_version: string, ai_call_id: int }`.
12. The action:
    - Resolves the target Bible version: explicit input >
      `language_settings.default_bible_version` > fallback `VDC` (RO).
    - Calls `ClaudeClient` with the `AddReferences\V1` prompt, passing
      the HTML and the resolved version.
    - Validates the returned HTML — every `<a>` link added must have a
      `class="reference"` attribute and an `href` that parses through
      the cross-chapter parser (MBA-023). Invalid links are stripped
      (logged as `security_events`).
    - Records the audit row in `ai_calls`.
    - Returns the cleaned HTML.
13. Synchronous endpoint:
    - `POST /api/v1/admin/ai/add-references` (super-admin gated)
    - Body: `{ html, language, bible_version_abbreviation?,
      subject_type?, subject_id? }`
    - Response: 200 with `AddReferencesOutput` shape; 422 on invalid
      input; 502 on Claude failure with retry-after.
    - Timeout: 60 seconds (admin UI shows a spinner; longer than that
      and the user backs off — async path is the alternative).
14. Async endpoint (Horizon-backed, lands once MBA-031 ships Horizon):
    - `POST /api/v1/admin/ai/add-references/batch` — body specifies a
      target collection (e.g. `commentary` slug + `language`) plus
      filter (e.g. `book=ROM`). Enqueues
      `App\Application\Jobs\AddReferencesBatchJob` which iterates the
      target rows, calls the action per row, and writes the result
      back in a transaction. Returns `202 Accepted` with an
      `import_jobs.id` for status polling (existing endpoint from
      MBA-022 §15).

### Tests

15. Unit tests for `ClaudeClient`:
    - Cache-control payload shape on system messages.
    - Retry/backoff on 429.
    - Token-usage logging populates `ai_calls` correctly (hit vs miss
      tokens, on every call).
16. Unit tests for `AddReferencesAction` with a faked `ClaudeClient`:
    - Default version resolution chain (input > settings > fallback).
    - Invalid `<a>` link stripping (e.g. links without `class="reference"`,
      links whose href doesn't parse).
    - `references_added` counter accuracy.
17. Feature tests for the sync endpoint covering: 401 (no token), 403
    (non-super admin), 422 (missing `html` or `language`), happy path,
    Claude-failure 502.
18. Feature test for the async endpoint asserting:
    - 202 response with `import_jobs.id`.
    - Job actually runs and updates rows when invoked synchronously
      under `Bus::fake()`.
19. Migration test asserting `language_settings` seeded for all 7 ISO-2
    codes.

## Scope

### In Scope

- Anthropic SDK install + config + client wrapper.
- Prompt caching machinery + versioned prompt registry.
- `ai_calls` audit log table.
- `language_settings` table + super-admin endpoints.
- `AddReferences` prompt V1 + action + sync endpoint.
- Async batch endpoint scaffolding (job class, route, response). The
  job enqueueing requires Horizon (MBA-031); until then the job runs
  on the existing `database` queue.

### Out of Scope

- Commentary-specific AI workflow (correction prompt, translation
  prompt, error reports table, SQLite export). All in MBA-029.
- Admin UI for language settings or the AI button. In admin MB-016.
- Frontend consumption of `language_settings` (post-login modal, default
  Bible version pre-selection). In frontend MB-016.
- Anything other-than-text (image generation, vision input, etc.).

## API Contract Required

- `POST /api/v1/admin/ai/add-references` — described in AC §13.
- `POST /api/v1/admin/ai/add-references/batch` — described in AC §14.
- `GET /api/v1/admin/language-settings` — list (super-admin).
- `PATCH /api/v1/admin/language-settings/{language}` — update.
- `GET /api/v1/language-settings/{language}` — public read.

## Technical Notes

- Prompt caching: the Anthropic SDK marks the system prompt with
  `cache_control: { type: 'ephemeral' }` and Claude returns
  `cache_creation_input_tokens` / `cache_read_input_tokens` separately
  in `usage`. We log both. The cache TTL is 5 minutes — back-to-back
  calls on the same prompt see the discount; calls separated by long
  gaps pay full price for the first one.
- Versioned prompts as code (not as DB rows or external files): the
  prompt is part of the system's behaviour, indistinguishable from
  application logic. Storing it in the DB invites silent drift between
  environments and undocumented changes; storing it as a file invites
  it being edited without a code review. PHP code with `const VERSION`
  is the cleanest path.
- The audit log table is intentionally polymorphic (`subject_type`,
  `subject_id`) so any feature that calls AI can attach the call to its
  row without joining through a feature-specific column. Indices favour
  the cost-by-feature roll-up that admin will eventually want
  ("how much did the commentary AI cost this month?").
- `language_settings` is a single-row-per-language table. We considered
  a generic `(language, key, value)` shape but it adds query complexity
  for no clear gain — there are at most a half-dozen settings per
  language ever, and each is a typed FK.
- Default Bible version resolution falls back to `VDC` (Cornilescu, RO)
  rather than throwing — a setting being unconfigured shouldn't break
  the AI flow.

## References

- MBA-023 cross-chapter parser (validates AI-emitted reference URLs).
- MBA-029 commentary AI workflow (the first big consumer of this
  foundation).
- MBA-031 Horizon migration (unlocks the async batch path).
- Admin MB-016 (UI for language settings + AI button cross-feature).
- Admin MB-017 (commentary-specific AI workflow UI).
- Frontend MB-016 (post-login modal reads `language_settings`).
- Anthropic SDK documentation: prompt caching — `cache_control`
  ephemeral type.
