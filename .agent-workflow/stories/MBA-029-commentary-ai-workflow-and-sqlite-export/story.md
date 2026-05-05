# Story: MBA-029-commentary-ai-workflow-and-sqlite-export

## Title

Commentary AI workflow: store original Symfony content, AI-correct to
plain Romanian, AI-add references, AI-translate to other languages,
collect user-reported errors, export per-source per-language SQLite
bundles for mobile offline use.

## Status

`planned`

## Description

Symfony's commentary content (e.g. SDA Bible Commentary) ships as long
prose with embedded references. The PM wants to:

1. Run an AI correction pass on the Romanian source — fix typos,
   awkward phrasing, normalise punctuation — without losing meaning.
   Result is the **plain** column.
2. Run an AI reference linker pass — wrap Bible references in the
   plain text with `<a class="reference" href="GEN.1:1.VDC">…</a>`.
   Result is the **with_references** column. Prompt knows the language
   and the per-language default Bible version (from MBA-028's
   `language_settings`).
3. Translate the corrected plain text to other languages by calling
   the AI translate prompt, then run reference linking again on the
   translated text (so each language gets reference URLs in its own
   default Bible version).
4. Show users the AI-corrected version on the frontend, with a notice
   "corrected with AI — if you spot mistakes, report here". Reports go
   into a queue admin triages.
5. Export per-source SQLite files (one per commentary, e.g.
   `sda.sqlite`) so mobile apps can ship offline content. Each row has
   one column per language with the `with_references` HTML.

This story builds the whole pipeline on top of the foundation in
MBA-028 (Claude client, prompt registry, language settings) and the
commentary domain from MBA-024.

## Acceptance Criteria

### Schema additions to `commentary_texts`

1. Add columns:
   - `original LONGTEXT NULL` — initial import from Symfony, untouched.
   - `plain LONGTEXT NULL` — AI-corrected (without references).
   - `with_references LONGTEXT NULL` — `plain` + reference links.
   - `errors_reported INT UNSIGNED NOT NULL DEFAULT 0` — counter of
     pending user reports.
   - `ai_corrected_at TIMESTAMP NULL`
   - `ai_corrected_prompt_version VARCHAR(20) NULL`
   - `ai_referenced_at TIMESTAMP NULL`
   - `ai_referenced_prompt_version VARCHAR(20) NULL`
2. Existing `content` column is retained as the canonical "what to render
   to users" source. Logic: if `with_references` is non-null, `content`
   serves as a fallback only when admin explicitly disables AI on a row;
   admins can override with a per-row `prefer_original BOOLEAN` flag —
   defer that flag for now (assume preference: with_references → plain
   → original → content). Default behaviour: serve `with_references` if
   present, else `content`.
3. ETL by MBA-031: at import, `original = content`; `plain` and
   `with_references` stay NULL until AI runs.

### New `commentary_error_reports` table

4. Columns:
   - `id`, `commentary_text_id BIGINT UNSIGNED NOT NULL` FK
     `ON DELETE CASCADE`
   - `user_id INT UNSIGNED NULL` FK `ON DELETE SET NULL` (anonymous
     reports allowed)
   - `device_id VARCHAR(64) NULL` — anonymous attribution
   - `book VARCHAR(8) NOT NULL`, `chapter SMALLINT UNSIGNED NOT NULL`,
     `verse SMALLINT UNSIGNED NULL` — denormalised for triage filters
   - `description TEXT NOT NULL`
   - `status VARCHAR(16) NOT NULL DEFAULT 'pending'` — `pending`,
     `reviewed`, `fixed`, `dismissed`
   - `reviewed_by_user_id INT UNSIGNED NULL` FK
   - `reviewed_at TIMESTAMP NULL`
   - `created_at TIMESTAMP`
5. Indexes: `(status, created_at)`, `(commentary_text_id, status)`.
6. When a report is created, `commentary_texts.errors_reported` is
   incremented. When status moves to `fixed` or `dismissed`, the counter
   is decremented (down to 0 floor).

### Prompt versions

7. New prompts shipped under `App\Domain\AI\Prompts\`:
   - `Commentary\CorrectV1` — input: `original` HTML, language. Output:
     `plain` HTML. Preserves HTML structure (tags, paragraph breaks),
     fixes only language. Conservative: when in doubt, leave as-is.
   - `Commentary\TranslateV1` — input: `plain` HTML in source language,
     target language. Output: translated HTML preserving structure.
   - The existing `AddReferencesV1` from MBA-028 runs on the post-
     correction (or post-translation) HTML.
8. Prompt versioning convention: each prompt's version is recorded on
   the row that consumed it (e.g.
   `commentary_text.ai_corrected_prompt_version`). When a prompt is
   bumped (V1 → V2), existing rows keep their old version; admin can
   re-run with V2 explicitly.

### Translation pipeline

9. Translating commentary to a new language creates a **new
   `commentaries` row** with `language = <target>`,
   `source_commentary_id = <source.id>` (from MBA-024 §1). The new
   commentary's `commentary_texts` rows are clones of the source's, with
   `original` copied from source's `plain`, `plain` populated by
   `Commentary\TranslateV1`, and `with_references` populated by
   `AddReferencesV1` against the target language's default Bible version.
10. Endpoint to start translation:
    - `POST /api/v1/admin/commentaries/{commentary}/translate` — body:
      `{ target_language: "en" }`. Validates that the source commentary
      has been corrected (`plain` populated on all texts) before
      translation runs. Enqueues
      `App\Application\Jobs\TranslateCommentaryJob` (Horizon-backed).
      Returns `202 Accepted` with `import_jobs.id`.
11. Idempotency: re-running translate on a target language that already
    exists either fails with 409 or, with `?overwrite=true`, replaces
    the existing target commentary's texts (keeps the `commentaries`
    row and metadata). Default behaviour: 409.

### Per-row AI endpoints (sync)

12. `POST /api/v1/admin/commentary-texts/{text}/ai-correct` —
    super-admin gated. Runs `Commentary\CorrectV1` on `original`,
    writes `plain`, `ai_corrected_at`, `ai_corrected_prompt_version`.
    Response: 200 with the updated row.
13. `POST /api/v1/admin/commentary-texts/{text}/ai-add-references` —
    runs `AddReferencesV1` on `plain` against the commentary's
    language's default Bible version. Writes `with_references`,
    `ai_referenced_at`, `ai_referenced_prompt_version`. Response: 200
    with the updated row.

### Batch AI endpoints (async, Horizon-backed)

14. `POST /api/v1/admin/commentaries/{commentary}/ai-correct-batch` —
    runs correction across all texts of the commentary; respects an
    optional `?book=ROM&chapter=8` filter. Returns 202 +
    `import_jobs.id`.
15. `POST /api/v1/admin/commentaries/{commentary}/ai-add-references-batch`
    — same shape, runs reference linking.
16. Job classes:
    - `App\Application\Jobs\CorrectCommentaryBatchJob`
    - `App\Application\Jobs\AddReferencesCommentaryBatchJob`
    - `App\Application\Jobs\TranslateCommentaryJob`
    Each iterates rows in chunks of 50, calls the relevant action,
    writes results in a per-row transaction. Failures are logged to
    `import_jobs.error` payload (per-row error trail) without aborting
    the whole job; the import job ends `partial` if any row failed.

### Error reports endpoints

17. Public submission (anonymous-friendly):
    - `POST /api/v1/commentary-texts/{text}/error-reports` — body:
      `{ description, verse?, device_id? }`. Auto-attaches `user_id` if
      authenticated. Rate-limited at 5/min/IP.
18. Admin queue:
    - `GET /api/v1/admin/commentary-error-reports?status=pending&page=`
      — paginated triage queue.
    - `PATCH /api/v1/admin/commentary-error-reports/{report}` — body:
      `{ status }`. Updates status + `reviewed_by_user_id`,
      `reviewed_at`; adjusts `commentary_texts.errors_reported` counter.

### SQLite export

19. `POST /api/v1/admin/commentaries/{commentary}/sqlite-export` —
    super-admin gated. Enqueues `App\Application\Jobs\ExportCommentarySqliteJob`.
    Returns 202 + `import_jobs.id`.
20. The job builds an `.sqlite` file matching the schema documented for
    mobile (see References below):
    - `meta` table — schema_version, source_slug, source_name,
      source_language, languages (CSV of populated languages),
      exported_at, exported_revision.
    - `commentary_text` table — `(book, chapter, position, verse_label,
      verse_from, verse_to, content_ro, content_en, content_hu,
      content_es, content_fr, content_de, content_it)` UNIQUE on
      `(book, chapter, position)`.
    - Indexes: `(book, chapter)`, `(book, chapter, verse_from,
      verse_to)`.
    - `PRAGMA user_version = 1; PRAGMA application_id = 0x4D424342;`.
21. The export joins the source commentary (in its source language)
    with all its `source_commentary_id`-linked translations. For each
    `(book, chapter, position)` triplet, the source row's
    `with_references` populates `content_<source_language>`, and each
    translation's `with_references` populates `content_<target_language>`.
    Languages absent from the populated list have NULL columns.
22. Output uploaded to S3 at
    `commentaries/{slug}/{revision}.sqlite`; `import_jobs.payload`
    records the URL on completion. Admin downloads from there.
23. Revision tag: increments per export (`v1`, `v2`, …) — used by
    mobile clients for cache busting.

### Tests

24. Feature tests for every new endpoint covering:
    - Auth + super-admin gate.
    - 422 on validation errors (missing description, invalid status
      transitions on reports).
    - Counter math on error reports
      (`pending → reviewed`: counter unchanged;
      `pending → fixed`: counter decrements;
      `fixed → pending`: counter increments back).
    - Translation 409 on existing target without `overwrite`.
25. Unit tests for the actions:
    - `CorrectCommentaryAction`, `AddReferencesCommentaryAction`,
      `TranslateCommentaryAction` with faked `ClaudeClient` returning
      canned HTML.
    - SQLite export action against a small fixture (3 rows ×
      2 languages) asserting schema, indexes, content columns
      populated correctly, NULL where translation is absent.
26. Job tests asserting partial-failure semantics: 100 rows where row
    50 errors → 99 rows succeed, job ends `partial`,
    `import_jobs.error` payload lists the offending row id and the
    error message.

## Scope

### In Scope

- Schema columns on `commentary_texts` for AI workflow.
- `commentary_error_reports` table.
- Three new prompts (Correct, Translate, AddReferences-extending).
- Per-row sync AI endpoints + per-source async batch endpoints.
- Translation pipeline (creates new commentary row).
- Error reports submission + admin triage.
- SQLite export job + S3 upload.

### Out of Scope

- Frontend public commentary reader rendering `with_references` and
  showing the "AI-corrected" notice — frontend MB-019.
- Frontend submission of error reports (modal with prefilled book/
  chapter/verse) — frontend MB-019.
- Admin UI for the AI buttons, error queue, SQLite export trigger —
  admin MB-017.
- Mobile consumption of the SQLite file (the `meta`, `commentary_text`
  schema is the contract; integration is mobile team).

## API Contract Required

- All endpoints listed in AC §10–22.
- `CommentaryErrorReportResource` with full status, reviewer, timestamps.
- SQLite file structure documented per AC §20–21 — this is the contract
  for mobile.

## Technical Notes

- The commentary AI pipeline has three independent passes — correct,
  reference, translate. They are **not** chained automatically: an
  admin runs each explicitly so partial output is reviewable. Once
  prompts stabilise, a "run all" button can chain them; this story
  keeps them independent.
- Reference linking always runs on the **post-correction**, **post-
  translation** text. Wrapping references on the original Symfony text
  would waste tokens (typos in book names would confuse the parser).
- Translation source-of-truth chain: `original → plain → translate(plain)
  → with_references(translation)`. Re-running correction on the source
  invalidates downstream translations only logically, not technically —
  admin must re-trigger translate. We do **not** auto-cascade because
  AI passes are paid; an admin should opt in.
- The error-report counter on `commentary_texts.errors_reported` is
  denormalised on purpose — it is the only field the public reader
  needs to decide whether to surface a "this commentary has open
  reports" badge. Joining to `commentary_error_reports` on every read
  is wasteful.
- SQLite export bundles all languages into a single file (one column
  per language). Alternative: one file per language. We chose
  one-file-per-source-multi-language because mobile users frequently
  switch languages; the single bundle deduplicates `book`/`chapter`/
  `position` rows and lets the client use `COALESCE(content_user_lang,
  content_en)` for fallbacks.

## References

- MBA-024 commentary domain (the bedrock).
- MBA-028 Claude API foundation (client, prompt registry,
  `language_settings`, `ai_calls` audit, AddReferencesV1 prompt).
- MBA-031 Horizon (queue infra for async jobs).
- Admin MB-017 (UI side of this story).
- Frontend MB-019 (public reader + error-report modal).
- SQLite schema documentation: chat thread 2026-05-02 (post-AC §3
  decision on per-source per-language structure).
- Sample commentary content: `Code/mybible/docs/commentaries/`.
