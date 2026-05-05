# Plan — MBA-029-commentary-ai-workflow-and-sqlite-export

> Design, don't implement. No code blocks, no method bodies, no SQL.
> Every helper listed here must be referenced by a task below.

## Approach

Layer the AI workflow on top of MBA-028's `ClaudeClient` + prompt registry +
`AddReferencesAction` and on top of MBA-024's `Commentary` / `CommentaryText`
domain. We add three columns triple (`original` / `plain` / `with_references`)
plus per-pass timestamps and prompt-version stamps to `commentary_texts`, a
new `commentary_error_reports` table with a denormalised counter, two new
versioned prompts under `App\Domain\AI\Prompts\Commentary\` (Correct, Translate),
per-row sync admin endpoints, three Horizon-backed batch jobs (correct,
add-references, translate), an anonymous-friendly error-report submission
endpoint plus admin triage queue, and an SQLite export job that streams
`(book, chapter, position)` triplets across the source commentary and its
`source_commentary_id`-linked translations into a per-source bundle written
to S3 under `commentaries/{slug}/v{n}.sqlite`.

## Preconditions

- **MBA-028** must be merged first: this plan assumes
  `App\Domain\AI\Clients\ClaudeClient`, `App\Domain\AI\Prompts\Prompt`
  base + registry, `App\Domain\AI\Actions\AddReferencesAction`,
  `App\Domain\AI\Prompts\AddReferences\V1`, `language_settings`, `ai_calls`
  audit log, and the `super-admin` middleware are in place.
- **MBA-031** Horizon work is not blocking: jobs dispatch to the existing
  `database` queue today and migrate when Horizon ships. The plan calls
  out the connection + queue name so the swap is config-only.
- **`partial` status**: MBA-029 extends `App\Domain\Admin\Imports\Enums\ImportJobStatus`
  with a `Partial = 'partial'` case (terminal). No data migration is
  needed — column is `VARCHAR(16)` already.

## Domain layout

| Path | Role |
|---|---|
| `app/Domain/AI/Prompts/Commentary/CorrectV1.php` | New prompt — `original` HTML + language → `plain` HTML; preserves structure. |
| `app/Domain/AI/Prompts/Commentary/TranslateV1.php` | New prompt — `plain` HTML in source language → translated HTML in target language; preserves structure. |
| `app/Domain/Commentary/Models/CommentaryErrorReport.php` | New Eloquent model for the triage queue. |
| `app/Domain/Commentary/QueryBuilders/CommentaryErrorReportQueryBuilder.php` | `pending()`, `forStatus(string)`, `forCommentaryText(int)` scopes. |
| `app/Domain/Commentary/Enums/CommentaryErrorReportStatus.php` | `Pending` / `Reviewed` / `Fixed` / `Dismissed` with `decrementsCounter()` helper. |
| `app/Domain/Commentary/DataTransferObjects/AICorrectCommentaryTextData.php` | Wraps a `CommentaryText` for the per-row Action. |
| `app/Domain/Commentary/DataTransferObjects/AIAddReferencesCommentaryTextData.php` | Wraps a `CommentaryText` for the per-row Action. |
| `app/Domain/Commentary/DataTransferObjects/TranslateCommentaryData.php` | `source_commentary_id`, `target_language`, `overwrite`. |
| `app/Domain/Commentary/DataTransferObjects/SubmitCommentaryErrorReportData.php` | `commentary_text_id`, `description`, `verse?`, `device_id?`, `user_id?`. |
| `app/Domain/Commentary/DataTransferObjects/UpdateCommentaryErrorReportData.php` | Target `status`, `reviewed_by_user_id`. |
| `app/Domain/Commentary/Actions/CorrectCommentaryTextAction.php` | Per-row `Commentary\CorrectV1` invocation; writes `plain`, `ai_corrected_at`, `ai_corrected_prompt_version`. |
| `app/Domain/Commentary/Actions/AddReferencesCommentaryTextAction.php` | Per-row reference linker; calls MBA-028 `AddReferencesAction` against `plain` using the commentary's language → `language_settings.default_bible_version`; writes `with_references`, `ai_referenced_at`, `ai_referenced_prompt_version`. |
| `app/Domain/Commentary/Actions/TranslateCommentaryAction.php` | Creates target `Commentary` (`source_commentary_id = source.id`, `language = target`), clones each text row with `original = source.plain`, runs `Commentary\TranslateV1` per row to populate target `plain`, then runs `AddReferencesCommentaryTextAction` on each translated row. |
| `app/Domain/Commentary/Actions/SubmitCommentaryErrorReportAction.php` | Creates the report row + atomic increment of `commentary_texts.errors_reported`. |
| `app/Domain/Commentary/Actions/UpdateCommentaryErrorReportStatusAction.php` | Transitions status, sets reviewer/timestamp, adjusts the counter using a transition table (see Counter math below) clamped to a 0 floor. |
| `app/Domain/Commentary/Actions/ExportCommentarySqliteAction.php` | Builds the SQLite artefact for the source commentary + linked translations, uploads to S3, returns the URL + revision. |
| `app/Domain/Commentary/Support/CommentarySqliteSchemaBuilder.php` | Helper used by the export action — knows the `meta` and `commentary_text` DDL, indexes, pragmas, and the language-column allow-list (`ro`, `en`, `hu`, `es`, `fr`, `de`, `it`). |
| `app/Domain/Commentary/Support/CommentarySqliteRevisionResolver.php` | Computes the next `v{n}` revision for a given commentary slug from S3 (or a sibling counter — see Risks). |
| `app/Application/Jobs/CorrectCommentaryBatchJob.php` | Iterates filtered texts in chunks of 50, invokes `CorrectCommentaryTextAction` per row in its own transaction, accumulates per-row failures into `import_jobs.error` payload, ends `partial` if any row failed. |
| `app/Application/Jobs/AddReferencesCommentaryBatchJob.php` | Same shape, calls `AddReferencesCommentaryTextAction`. |
| `app/Application/Jobs/TranslateCommentaryJob.php` | Calls `TranslateCommentaryAction` once per source row (chunked), populates the target commentary; partial-failure semantics identical to the other batch jobs. |
| `app/Application/Jobs/ExportCommentarySqliteJob.php` | Calls `ExportCommentarySqliteAction`, writes the resulting URL + revision into `import_jobs.payload`. |
| `app/Application/Support/CommentaryBatchRunner.php` | Shared helper used by all three batch jobs to chunk the target collection, run the per-row action, and write per-row error trails into the import job — extracts the duplication that would otherwise spread across three jobs (see Tripwire note below). |

## Schema changes

| Table | Change | Notes |
|---|---|---|
| `commentary_texts` | `+ original LONGTEXT NULL` | Initial Symfony import slot. |
| `commentary_texts` | `+ plain LONGTEXT NULL` | AI-corrected. |
| `commentary_texts` | `+ with_references LONGTEXT NULL` | Reference-linked. |
| `commentary_texts` | `+ errors_reported INT UNSIGNED NOT NULL DEFAULT 0` | Denormalised pending-report counter. |
| `commentary_texts` | `+ ai_corrected_at TIMESTAMP NULL` | Stamp. |
| `commentary_texts` | `+ ai_corrected_prompt_version VARCHAR(20) NULL` | Stamp. |
| `commentary_texts` | `+ ai_referenced_at TIMESTAMP NULL` | Stamp. |
| `commentary_texts` | `+ ai_referenced_prompt_version VARCHAR(20) NULL` | Stamp. |
| `commentary_error_reports` | New table | `id`, `commentary_text_id BIGINT UNSIGNED NOT NULL` FK CASCADE, `user_id INT UNSIGNED NULL` FK SET NULL (column width matches `users.id` per the `import_jobs` precedent), `device_id VARCHAR(64) NULL`, `book VARCHAR(8) NOT NULL`, `chapter SMALLINT UNSIGNED NOT NULL`, `verse SMALLINT UNSIGNED NULL`, `description TEXT NOT NULL`, `status VARCHAR(16) NOT NULL DEFAULT 'pending'`, `reviewed_by_user_id INT UNSIGNED NULL` FK SET NULL, `reviewed_at TIMESTAMP NULL`, `created_at TIMESTAMP NULL`, `updated_at TIMESTAMP NULL`. |
| `commentary_error_reports` | Indexes | `(status, created_at)`, `(commentary_text_id, status)`. |

`content` (legacy from MBA-024) is retained untouched. Default read
preference is `with_references` → `content` (story §AC2). The
`prefer_original` flag is explicitly deferred.

## HTTP endpoints

| Verb | Path | Controller | Request | Resource | Auth |
|---|---|---|---|---|---|
| POST | `/api/v1/admin/commentary-texts/{text}/ai-correct` | `Admin/Commentary/AICorrectCommentaryTextController` | `Admin/Commentary/AICorrectCommentaryTextRequest` | `AdminCommentaryTextResource` (extended — see below) | `auth:sanctum` + `super-admin` |
| POST | `/api/v1/admin/commentary-texts/{text}/ai-add-references` | `Admin/Commentary/AIAddReferencesCommentaryTextController` | `Admin/Commentary/AIAddReferencesCommentaryTextRequest` | `AdminCommentaryTextResource` | same |
| POST | `/api/v1/admin/commentaries/{commentary}/ai-correct-batch` | `Admin/Commentary/AICorrectCommentaryBatchController` | `Admin/Commentary/AICorrectCommentaryBatchRequest` | 202 + `ImportJobResource` | same |
| POST | `/api/v1/admin/commentaries/{commentary}/ai-add-references-batch` | `Admin/Commentary/AIAddReferencesCommentaryBatchController` | `Admin/Commentary/AIAddReferencesCommentaryBatchRequest` | 202 + `ImportJobResource` | same |
| POST | `/api/v1/admin/commentaries/{commentary}/translate` | `Admin/Commentary/TranslateCommentaryController` | `Admin/Commentary/TranslateCommentaryRequest` | 202 + `ImportJobResource`; 409 on existing target without `?overwrite=true` | same |
| POST | `/api/v1/admin/commentaries/{commentary}/sqlite-export` | `Admin/Commentary/ExportCommentarySqliteController` | `Admin/Commentary/ExportCommentarySqliteRequest` | 202 + `ImportJobResource` | same |
| POST | `/api/v1/commentary-texts/{text}/error-reports` | `Commentary/SubmitCommentaryErrorReportController` | `Commentary/SubmitCommentaryErrorReportRequest` | `CommentaryErrorReportResource` (public shape) | `api-key-or-sanctum` + `throttle:commentary-error-reports` (5/min/IP) |
| GET | `/api/v1/admin/commentary-error-reports` | `Admin/Commentary/ListCommentaryErrorReportsController` | `Admin/Commentary/ListCommentaryErrorReportsRequest` | `AdminCommentaryErrorReportResource::collection` paginated | `auth:sanctum` + `super-admin` |
| PATCH | `/api/v1/admin/commentary-error-reports/{report}` | `Admin/Commentary/UpdateCommentaryErrorReportController` | `Admin/Commentary/UpdateCommentaryErrorReportRequest` | `AdminCommentaryErrorReportResource` | same |

`{commentary}` and `{text}` use id binding (admin-permissive — drafts
visible) per MBA-024's `Commentary::resolveRouteBinding($value, $field)`
strategy. `{report}` uses default id binding. `AdminCommentaryTextResource`
is extended — not duplicated — to expose `original`, `plain`,
`with_references`, the four AI stamps, and `errors_reported`. Public
`CommentaryTextResource` exposes the resolved `content` (story §AC2
fallback chain) plus `errors_reported` so the public reader can surface
the "open reports" badge without a join.

## Counter math

Transition matrix used by `UpdateCommentaryErrorReportStatusAction`:

| From → To | Counter delta |
|---|---|
| any → `pending` | `+1` (only when source was Fixed/Dismissed) |
| `pending` → `reviewed` | `0` (still open) |
| `reviewed` → `pending` | `0` |
| `pending`/`reviewed` → `fixed` | `-1` |
| `pending`/`reviewed` → `dismissed` | `-1` |
| `fixed` → `pending` / `reviewed` | `+1` |
| `dismissed` → `pending` / `reviewed` | `+1` |
| same → same | `0` |

Floor at 0 via `MAX(errors_reported - 1, 0)` semantics in the action.
`SubmitCommentaryErrorReportAction` always increments by 1. All
mutations execute inside a DB transaction with a `lockForUpdate()` on
the `CommentaryText` row to keep the counter consistent under concurrent
triage edits.

## Translation pipeline shape

`TranslateCommentaryAction` invariants the controller / job rely on:

1. Refuse to start unless every source `CommentaryText.plain` is non-null
   (controller validates this in the Form Request via a custom rule).
2. Look for an existing `Commentary` where
   `source_commentary_id = source.id AND language = target`. If found
   and `overwrite` is false → 409 from the controller. If found and
   `overwrite` is true → reuse the row, delete its texts inside the job's
   chunked work loop. If absent → create a new `Commentary` row using
   `CreateCommentaryAction` (MBA-024) with derived slug
   `{source.slug}-{target_language}`.
3. Per source text: clone `(book, chapter, position, verse_from, verse_to,
   verse_label)`; populate `original` from `source.plain`; run
   `Commentary\TranslateV1` to fill `plain`; run
   `AddReferencesCommentaryTextAction` to fill `with_references`.
4. Job ends `succeeded` if all rows ok, `partial` if any failed (per-row
   errors written into `import_jobs.error` JSON list).

## SQLite export shape

`ExportCommentarySqliteAction` runs entirely on a tmp file (`storage/app/tmp/{uuid}.sqlite`)
opened with the `sqlite` PDO driver — **not** registered as a Laravel DB
connection (avoids polluting `config/database.php`). Steps:

1. Resolve next revision via `CommentarySqliteRevisionResolver` (lists
   the existing S3 prefix or counts a sibling `commentary_sqlite_exports`
   bookkeeping shape; see Risks for the chosen path).
2. Build DDL using `CommentarySqliteSchemaBuilder` — `meta` table,
   `commentary_text` table with one `content_<lang>` column per
   allow-list language, indexes per AC §20, `PRAGMA user_version = 1;
   PRAGMA application_id = 0x4D424342;`.
3. Stream rows: outer query on the source commentary's
   `(book, chapter, position)` ordered by those three; for each triplet
   left-join each linked translation's matching row by
   `(book, chapter, position)`; insert one row with each `content_<lang>`
   filled from that language's `with_references` (NULL when no row or
   when `with_references` is null, falling back to `content` only for
   the source language as a last resort).
4. Vacuum the file (`PRAGMA optimize` + `VACUUM`) to keep mobile
   download size honest.
5. Upload to S3 at `commentaries/{slug}/v{n}.sqlite` using the project's
   default disk pattern; record URL + revision into `import_jobs.payload`.
6. Delete the local tmp file in a `try/finally`.

`exported_revision` in the `meta` table mirrors the S3 path's `v{n}`.
Languages absent from the populated translation set leave their column
columns NULL. Mobile clients use `COALESCE(content_<user_lang>, content_en)`.

## Throttling

`RateLimiter::for('commentary-error-reports')` registered in
`AppServiceProvider`: `Limit::perMinute(5)->by($request->ip())`. Distinct
from `public-anon` so a corporate NAT doesn't poison ordinary reads.

## Tripwire register update

`CommentaryBatchRunner` is the new shared chunk/transaction/per-row
error-trail helper for the three batch jobs. It is **not** a tripwire
deferral — three uses on day one is past the threshold; we extract
immediately. Improver should not log this as a duplication.

## Tasks

- [x] 1. Migration: add the eight columns (`original`, `plain`, `with_references`, `errors_reported`, four AI stamps) to `commentary_texts`. Default `errors_reported` to `0` NOT NULL, others nullable.
- [x] 2. Migration: create `commentary_error_reports` per the schema table above (FKs, indexes). Add factory + seeder is not needed.
- [x] 3. Extend `App\Domain\Admin\Imports\Enums\ImportJobStatus` with `Partial = 'partial'` (terminal in `isTerminal()`); update `ImportJobResource` + tests that enumerate the cases.
- [x] 4. Add `CommentaryErrorReportStatus` enum with `decrementsCounter()` and `isOpen()` helpers.
- [x] 5. Add `CommentaryErrorReport` model (timestamps, FKs cast) + `CommentaryErrorReportFactory` + `CommentaryErrorReportQueryBuilder` (`pending()`, `forStatus()`, `forCommentaryText()`).
- [x] 6. Extend `CommentaryText` model casts/`@property` for the new columns; add the `errorReports()` hasMany relation.
- [x] 7. Add `App\Domain\AI\Prompts\Commentary\CorrectV1` extending the MBA-028 `Prompt` base — version `1.0.0`, system prompt covers the no-meaning-change rule + HTML-structure preservation; user message takes `original` and `language`.
- [x] 8. Add `App\Domain\AI\Prompts\Commentary\TranslateV1` — version `1.0.0`, system prompt covers structure preservation + idiomatic translation; user message takes `plain`, `source_language`, `target_language`. Pin `claude-opus-4-7` per MBA-028 §AC1 note.
- [x] 9. Register both new prompts in MBA-028's prompt registry.
- [x] 10. Add `AICorrectCommentaryTextData`, `AIAddReferencesCommentaryTextData`, `TranslateCommentaryData`, `SubmitCommentaryErrorReportData`, `UpdateCommentaryErrorReportData` readonly DTOs.
- [x] 11. Add `CorrectCommentaryTextAction` — calls `ClaudeClient` via `CorrectV1`, writes `plain` + stamps in a transaction, polymorphic `subject_type/subject_id` on the resulting `ai_calls` row points at the `CommentaryText`.
- [x] 12. Add `AddReferencesCommentaryTextAction` — resolves bible version via `language_settings.default_bible_version_id`, calls MBA-028 `AddReferencesAction`, writes `with_references` + stamps in a transaction.
- [x] 13. Add `TranslateCommentaryAction` — implements the four-step translation pipeline above; reuses `CreateCommentaryAction` (MBA-024) for the target-commentary creation path; deletes target texts when `overwrite=true`.
- [x] 14. Add `SubmitCommentaryErrorReportAction` — creates the report and increments `errors_reported` atomically (`lockForUpdate` on the `CommentaryText`).
- [x] 15. Add `UpdateCommentaryErrorReportStatusAction` — applies the counter delta from the transition matrix above, with a 0 floor; rejects unknown status transitions with a domain exception that maps to 422.
- [x] 16. Add `CommentarySqliteSchemaBuilder` — exposes the `meta` DDL, the `commentary_text` DDL parameterised by populated languages, the index list, and the two pragmas; consumed only by `ExportCommentarySqliteAction`.
- [x] 17. Add `CommentarySqliteRevisionResolver` — list the S3 prefix `commentaries/{slug}/` and pick the next `v{n}`; bumped at most once per export job.
- [x] 18. Add `ExportCommentarySqliteAction` — implements the six-step export above; uploads to the default disk under `commentaries/{slug}/v{n}.sqlite`.
- [x] 19. Add `App\Application\Support\CommentaryBatchRunner` — shared chunk/transaction/per-row error-trail helper for the three batch jobs, parameterised by the chunk size (default 50) and the per-row callable; updates `import_jobs.progress` after each chunk and writes per-row failures into the `error` JSON list, returning the final `succeeded` / `partial` status.
- [x] 20. Add `CorrectCommentaryBatchJob`, `AddReferencesCommentaryBatchJob`, `TranslateCommentaryJob`, `ExportCommentarySqliteJob` — each delegating to the relevant Action via `CommentaryBatchRunner` (the export job is single-shot, not chunked, so it bypasses the runner). Connection: `database`, queue: `ai`.
- [x] 21. Form Requests for all eight admin endpoints + the public submission endpoint (validation per AC §10–22). The translate request enforces "all source texts have non-null `plain`" via a dedicated rule class (`Commentary\Rules\AllTextsCorrected`).
- [x] 22. Controllers for all eight admin endpoints + the public submission endpoint. Each is a single-action invokable controller delegating to the matching Action.
- [x] 23. Resources: `CommentaryErrorReportResource` (public) and `AdminCommentaryErrorReportResource` (adds `id`, reviewer, status timestamps, full description). Extend `AdminCommentaryTextResource` to expose the new columns + AI stamps + counter; extend public `CommentaryTextResource` to expose `errors_reported` and to switch the `content` field to the resolved fallback (`with_references` → `content`).
- [x] 24. Routes: add the nine routes per the HTTP table above to `routes/api.php` under the existing `commentaries` admin group + a new `commentary-texts` admin group + a new public `commentary-texts/{text}/error-reports` group; the admin `commentary-error-reports` group lives under the existing super-admin block.
- [x] 25. Register `commentary-error-reports` rate limiter in `AppServiceProvider`.
- [x] 26. Unit tests for `CorrectCommentaryTextAction`, `AddReferencesCommentaryTextAction`, `TranslateCommentaryAction` with a faked `ClaudeClient` returning canned HTML — assert the right prompt version is recorded, the right column is written, and the right `ai_calls` audit row is produced.
- [x] 27. Unit tests for `SubmitCommentaryErrorReportAction` and `UpdateCommentaryErrorReportStatusAction` covering every cell of the counter-math matrix above and the 0 floor.
- [x] 28. Unit test for `ExportCommentarySqliteAction` against a 3-row × 2-language fixture: assert the output file's `meta` row, `commentary_text` schema (column set, indexes, pragmas), populated `content_<lang>` cells, and NULL where translations are absent. Open the artefact via PDO sqlite to assert.
- [x] 29. Unit test for `CommentaryBatchRunner` partial-failure semantics: 100 rows where row 50 throws → 99 rows succeed, runner returns `Partial`, `import_jobs.error` payload lists the offending row id and message.
- [x] 30. Feature tests for each admin endpoint (auth + super-admin gate, 422 validation paths, happy path); 409 on existing translation target without `?overwrite=true`; 5/min throttle on the public submission endpoint via the named limiter.
- [x] 31. Feature test for the public reader contract: extended `CommentaryTextResource` returns `with_references` when present, falls back to `content`, and surfaces `errors_reported`.
- [x] 32. Feature test for the SQLite export endpoint asserting 202 + `import_jobs.id` and (with `Bus::fake()` + manual job dispatch) that the resulting `import_jobs.payload` records the S3 URL + revision and the artefact lands at the expected key.
- [x] 33. Run `make lint-fix`, `make stan`, `make test-api filter=Commentary`, then `make test-api` for full-suite green.

## Risks

- **Revision storage source-of-truth.** The plan resolves `v{n}` by
  listing the S3 prefix. Two pitfalls: (a) S3 listing is eventually
  consistent — an export racing with itself could pick the same `v{n}`;
  (b) the prefix grows unbounded. Mitigation: serialise per-slug exports
  via `Cache::lock("sqlite-export:{slug}")` inside
  `ExportCommentarySqliteJob`. If the lock is contended, the second
  export waits up to 30 s, then 423s the tracker. The bookkeeping-table
  alternative is cleaner but pulls a new migration; we only escalate to
  a table when the lock proves insufficient under load.
- **Tmp file pressure.** Large commentaries (SDA Bible Commentary in 7
  languages) produce SQLite files in the 30–80 MB range. The action
  writes to `storage/app/tmp/` — the worker container must have at
  least that headroom on disk. Engineer should `Storage::disk('local')`-
  size-check before kicking off the job so we fail with a useful error
  rather than mid-stream.
- **Translation idempotency edge case.** With `overwrite=true`, the job
  reuses the target `Commentary` row but deletes its texts before
  recreating them. Any in-flight `error_reports` whose
  `commentary_text_id` belonged to the deleted texts cascade-delete via
  the FK — this is correct (those reports targeted text that no longer
  exists), but the plan calls it out so the engineer doesn't add a
  protective guard that would silently leave dangling counters.
- **`AddReferencesV1` re-run cost.** Translation does `correct + reference`
  per row, doubling Claude calls relative to a "translate-only" pass.
  The story explicitly accepts this (admins opt in). Logged here so the
  Auditor doesn't flag it as N+1.
- **`Commentary\TranslateV1` model pin.** Translation quality is the
  most prompt-sensitive of the three passes. The plan pins
  `claude-opus-4-7` (per MBA-028 §AC1 note that the registry can override
  per use case). If Opus pricing later forces a downgrade, we bump
  `TranslateV1` → `TranslateV2` rather than silently swapping the model.

## References

- MBA-024 `plan.md` — `Commentary` / `CommentaryText` shape, route binding strategy, admin/public Resource split.
- MBA-028 `story.md` — `ClaudeClient`, prompt registry, `AddReferencesAction`, `language_settings`, `ai_calls`, `super-admin` middleware.
- MBA-031 — Horizon (queue connection target for the three batch jobs).
- `App\Domain\Admin\Imports\Models\ImportJob` + `ImportJobStatus` — async tracker reused unchanged except for the new `Partial` enum case.
- `App\Domain\Admin\Imports\ShowImportJobController` (route `/api/v1/admin/imports/{job}`) — admin polls this for batch progress.
- `AppServiceProvider::boot` — pattern for registering the new `commentary-error-reports` rate limiter alongside `public-anon`, `per-user`, `downloads`.
