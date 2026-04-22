# Code Review: MBA-008-verses-and-daily-verse

**Reviewer:** Code Reviewer agent
**Verdict:** `APPROVE`
**Scope:** Diff against `main` for commits `6951380` (initial) + `11035e1` (review fixes) — 28 files, +1636/-27.

---

## Summary

The diff implements the two endpoints (`GET /api/v1/verses`, `GET /api/v1/daily-verse`) in line with the plan: Option A daily-verse shape, canonical+split request forms, three-tier version cascade, partial-resolution via `meta.missing`, and cross-cutting exception handlers for `InvalidReferenceException` / `NoDailyVerseForDateException`. Tests exercise the happy paths, split params, partial resolution, auth gate, cache header, and the unit-level cascade. Architecture, DTOs, and resources match the plan's table.

Re-review after commit `11035e1` confirms both prior Warnings are fixed. `make test filter=Verses` → 37 passed. `make lint` + `make stan` clean.

---

## Warnings

- [x] **`ResolveVersesRequest::resolveVersion()` — case handling was inconsistent between the three tiers.** `app/Http/Requests/Verses/ResolveVersesRequest.php:169-177`. Fixed in `11035e1`: the user-preferred tier now uppercases `$preferred` into `$normalized` **before** calling `versionExists()`, matching the explicit-query branch. A case-sensitive collation can no longer silently skip the tier and reorder the cascade.
- [x] **`ResolveVersesAction::handle()` re-loaded relations already known to the query builder.** `app/Domain/Verses/Actions/ResolveVersesAction.php`. Fixed in `11035e1`: `BibleVerseQueryBuilder::lookupReferences()` now fetches full `BibleVersion`/`BibleBook` models (instead of id-only plucks) and attaches them via `setRelation()` on each resolved `BibleVerse`. The redundant `$verses->load(['version', 'book'])` call has been removed from the action. The batched-query guarantee asserted by `test_it_batches_queries_by_version_book_chapter` is preserved (still 2 catalog selects + N per-group verse selects, no post-hoc reload).

---

## Suggestions

_(Carried from the prior review — all non-blocking, engineer may address in a follow-up or ignore.)_

- **`ResolveVersesController` double-wraps the collection.** `app/Http/Controllers/Api/V1/Verses/ResolveVersesController.php:27-28` passes `VerseResource::collection($result->verses)` (an `AnonymousResourceCollection` of already-wrapped `VerseResource`s) into `new VerseCollection(...)` which itself declares `$collects = VerseResource::class`. Works today because `collectResource()` tolerates pre-wrapped items, but is redundant. Prefer:
  ```php
  return (new VerseCollection($result->verses))
      ->additional(['meta' => ['missing' => $result->missing]]);
  ```

- **`GetDailyVerseController::MAX_AGE` duplicates a capability already in `BibleCacheHeaders`.** `app/Http/Controllers/Api/V1/Verses/GetDailyVerseController.php:17`. The project already centralises cache constants in `App\Domain\Bible\Support\BibleCacheHeaders` (`LIST_MAX_AGE = 3600`, `EXPORT_MAX_AGE = 86400`). Consider adding a `DAILY_VERSE_MAX_AGE` constant there (or reusing `LIST_MAX_AGE`) so every public max-age lives in one file.

- **`tests/Feature/Api/V1/Verses/GetDailyVerseTest.php:42`** asserts `assertHeader('Cache-Control', 'max-age=3600, public')`. The string form relies on Symfony's alphabetical directive ordering; if Symfony ever changes the serialization (or we add another directive like `no-transform`), the assertion snaps. Prefer a `str_contains(...)` check, or leave a comment explaining the ordering constraint.

- **`DailyVerseFactory::definition()` uses `fake()->unique()->date()`.** `database/factories/DailyVerseFactory.php:23`. `unique()` accumulates across the test suite; `date()`'s default range is 1 Jan 1970 → today. Risk is only infinite retries if the sample space is exhausted (won't happen at realistic scale). Suggestion: drop `unique()` — the unique constraint is on the column, and every test that cares about a specific `for_date` passes it explicitly anyway.

- **`ResolveVersesRequest::rules()` regex `/^[0-9,\-]+$/`.** `app/Http/Requests/Verses/ResolveVersesRequest.php:33`. Accepts degenerate inputs like `verses=,-,`, `verses=1,,3`, `verses=-`. The parser then throws `InvalidReferenceException` for these, which renders as `422 { errors: { reference: [...] } }` — but the client sent a bad `verses` field. Tighten to `/^\d+(-\d+)?(,\d+(-\d+)?)*$/` so the 422 attributes the error to the right field.

- **`BibleVerseQueryBuilder::lookupReferences()` issues one query per `(version, book, chapter)` group.** `app/Domain/Bible/QueryBuilders/BibleVerseQueryBuilder.php:67-89`. For multi-chapter references (e.g. `GEN.1:1;2:1;3:1.VDC`) this is N queries. Acceptable for bounded ranges and explicit in the plan; a single `whereIn((bible_version_id, bible_book_id, chapter, verse), tuples)` would collapse it. Not worth refactoring today; revisit if/when a hot path emerges.

- **`ResolveVersesAction::wholeChapterVerseNumbers()` — process-level `static $cache`.** `app/Domain/Verses/Actions/ResolveVersesAction.php:118`. The cache key is `book|chapter` with no tenant/seed dimension. Bible catalog is effectively immutable in production, so this is safe at runtime; in a long-lived worker it also survives across requests which is what you want. Flag only in case the catalog ever becomes per-tenant or per-version-family.

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

Both Warnings from the initial review are fixed in `11035e1` and verified against source. No Critical findings. Remaining items are Suggestions (non-blocking). Story advances to `qa-ready`.
