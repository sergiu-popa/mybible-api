# Code Review: MBA-008-verses-and-daily-verse

**Reviewer:** Code Reviewer agent
**Verdict:** `REQUEST CHANGES`
**Scope:** Diff against `main` for commit `6951380` — 28 files, +1538/-27.

---

## Summary

The diff implements the two endpoints (`GET /api/v1/verses`, `GET /api/v1/daily-verse`) in line with the plan: Option A daily-verse shape, canonical+split request forms, three-tier version cascade, partial-resolution via `meta.missing`, and cross-cutting exception handlers for `InvalidReferenceException` / `NoDailyVerseForDateException`. Tests exercise the happy paths, split params, partial resolution, auth gate, cache header, and the unit-level cascade. Architecture, DTOs, and resources match the plan's table.

The findings below are not blockers in shipping posture, but two `Warning`s should be resolved (or acknowledged) before APPROVE.

---

## Warnings

- [x] **`ResolveVersesRequest::resolveVersion()` — case handling is inconsistent between the three tiers.** `app/Http/Requests/Verses/ResolveVersesRequest.php:141-190`.
  - Explicit `?version=` tier: `strtoupper($explicit)` **before** `versionExists()`. ✅
  - Language-config tier: `config()` map returns canonical abbreviations; `versionExists()` matches. ✅
  - **User-preferred tier (L169-173):** reads `$this->user()?->getAttribute('preferred_version')` and passes it **as-is** to `versionExists()`, only uppercasing the returned value. On a case-sensitive collation (utf8mb4_bin), a stored `"kjv"` would fail the lookup and fall through to the config tier silently — reordering the cascade without warning.
  - **Fix:** uppercase once before the DB check, identical to the explicit-query branch:
    ```php
    $preferred = $this->user()?->getAttribute('preferred_version');
    if (is_string($preferred) && $preferred !== '') {
        $normalized = strtoupper($preferred);
        if ($this->versionExists($normalized)) {
            return $normalized;
        }
    }
    ```
  - **Why flag now:** MBA-018 will add this column, at which point the bug is live; easier to fix inside the story that introduces the read.

- [x] **`ResolveVersesAction::handle()` re-loads relations that the query builder already resolved.** `app/Domain/Verses/Actions/ResolveVersesAction.php:19-21` calls `$verses->load(['version', 'book'])` right after `BibleVerseQueryBuilder::lookupReferences()` returned them. That adds two extra `SELECT` queries per request, and the abbreviations used in the `computeMissing()` key (`$verse->version->abbreviation`) are already known from the input `Reference[]`.
  - **Fix (pick one):**
    1. Map the resolved `$versionIds` / `$bookIds` from `BibleVerseQueryBuilder` back to abbreviations in-place (return a lightweight DTO alongside the `BibleVerse`s), OR
    2. Drop the `load()` and build `computeMissing()`'s resolved-keys off the input references' `(version, book, chapter)` plus `$verse->verse`, since every row in the result-set belongs to exactly one group.
  - **Why it matters:** the action claims (see task 22 / `test_it_batches_queries_by_version_book_chapter`) that query count ≈ distinct `(version, book, chapter)` groups. That assertion only counts `bible_verses` rows (filtered by SQL prefix) — it silently ignores the two relation-reload queries `load()` triggers. The current code violates the plan's batching intent.

---

## Suggestions

- **`ResolveVersesController` double-wraps the collection.** `app/Http/Controllers/Api/V1/Verses/ResolveVersesController.php:27-28` passes `VerseResource::collection($result->verses)` (an `AnonymousResourceCollection` of already-wrapped `VerseResource`s) into `new VerseCollection(...)` which itself declares `$collects = VerseResource::class`. Works today because `collectResource()` tolerates pre-wrapped items, but is redundant and fragile to future refactors. Prefer:
  ```php
  return (new VerseCollection($result->verses))
      ->additional(['meta' => ['missing' => $result->missing]]);
  ```

- **`GetDailyVerseController::MAX_AGE` duplicates a capability already in `BibleCacheHeaders`.** `app/Http/Controllers/Api/V1/Verses/GetDailyVerseController.php:17`. The project already centralises cache constants in `App\Domain\Bible\Support\BibleCacheHeaders` (`LIST_MAX_AGE = 3600`, `EXPORT_MAX_AGE = 86400`). Consider adding a `DAILY_VERSE_MAX_AGE` constant there (or reusing `LIST_MAX_AGE`) so every public max-age lives in one file. Not a bug — just the pattern this repo has adopted.

- **`tests/Feature/Api/V1/Verses/GetDailyVerseTest.php:42`** asserts `assertHeader('Cache-Control', 'max-age=3600, public')`. The string form relies on Symfony's alphabetical directive ordering; if Symfony ever changes the serialization (or we add another directive like `no-transform`), the assertion snaps. Prefer `assertHeader('Cache-Control', ...)` paired with `str_contains(...)` on the value, or at minimum leave a comment explaining the ordering constraint.

- **`DailyVerseFactory::definition()` uses `fake()->unique()->date()`.** `database/factories/DailyVerseFactory.php:23`. `unique()` accumulates across the test suite, and `date()`'s default range is 1 Jan 1970 → today. The state never resets between tests, so the only correctness risk is infinite retries if the sample space is exhausted (won't happen at realistic scale). Suggestion: `fake()->date()` without `unique()` — the unique constraint is on the column, and every test that cares about a specific `for_date` passes it explicitly anyway.

- **`ResolveVersesRequest::rules()` regex `/^[0-9,\-]+$/`.** `app/Http/Requests/Verses/ResolveVersesRequest.php:33`. Accepts degenerate inputs like `verses=,-,`, `verses=1,,3`, `verses=-`. The parser then throws `InvalidReferenceException` for these, which renders as `422 { errors: { reference: [...] } }` — but the client sent a bad `verses` field, not a bad `reference`. Consider tightening the rule (`/^\d+(-\d+)?(,\d+(-\d+)?)*$/`) so the 422 attributes the error to the right field.

- **`BibleVerseQueryBuilder::lookupReferences()` issues one query per `(version, book, chapter)` group.** `app/Domain/Bible/QueryBuilders/BibleVerseQueryBuilder.php:65-85`. For multi-chapter references (e.g. `GEN.1:1;2:1;3:1.VDC`) this is N queries. Acceptable for bounded ranges and explicit in the plan, but a single `whereIn((bible_version_id, bible_book_id, chapter, verse), tuples)` would collapse it. Not worth refactoring today; revisit if/when a hot path emerges.

---

## Guideline adherence (spot-checks)

- ✅ Strict types on every new file.
- ✅ `final` on concrete classes.
- ✅ `readonly` DTOs under `DataTransferObjects/`.
- ✅ Controllers are thin — delegate to Actions, return Resources.
- ✅ Form Request → Action → Resource layering; no `$request->validate()` inline.
- ✅ `api-key-or-sanctum` + `resolve-language` middleware (`routes/api.php:59-62`).
- ✅ Exception handlers wired in `bootstrap/app.php:79-88`, using the project's JSON envelope.
- ✅ Factories + feature + unit tests cover the plan's task list.
- ✅ `InvalidReferenceException` handler is a shared concern — flagged in plan risks; track in the deferred-extractions register once MBA-014 lands a second consumer.
- ✅ No Blade/Livewire/frontend additions.
- ✅ No new dependencies.

---

## Plan deviations — acknowledged

- Plan task 6 envisioned a `VerseQueryBuilder` under `App\Domain\Verses\QueryBuilders\`. Engineer extended the MBA-007-owned `App\Domain\Bible\QueryBuilders\BibleVerseQueryBuilder` instead — the better call (keeps the `BibleVerse` Eloquent builder contract single-sourced).
- Plan task 21 mentioned asserting `date` in the future → `422`; implementation also adds a malformed-date-format assertion (`test_it_rejects_malformed_date_format`) — net positive.

---

## Verdict rationale

Two `Warning`s remain unchecked:
1. Case-sensitivity inconsistency in `ResolveVersesRequest::resolveVersion()`.
2. Relation re-loading in `ResolveVersesAction::handle()` that defeats the batched-query guarantee asserted by the unit test.

Status stays `in-review` pending Engineer fixes (or explicit acknowledgement with a `— acknowledged: <reason>` line on each).
