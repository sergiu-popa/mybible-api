# Audit: MBA-008-verses-and-daily-verse

**Auditor:** Auditor agent
**Verdict:** `PASS`
**Scope:** Diff against `main` (commits `6951380` + `11035e1`) — 28 files, +1636/-27. Post-review + QA passed. Re-verified after audit fixes.

---

## Summary

Final holistic pass after Review (`APPROVE`, two warnings closed at `11035e1`) and QA (`QA PASSED`, 366 tests). Review left six non-blocking suggestions; five of them are addressed in this audit. The sixth (query-builder N-per-group) is plan-acknowledged and deferred. One process-level static cache is retained with a skip-with-reason.

Architecture, security, API design, tests — all in line with the plan and CLAUDE.md. No Critical findings.

Post-fix gate: `make lint-fix` clean, `make stan` clean, `make test` → **367 passed**, 1139 assertions. Added one new feature test (`test_it_rejects_malformed_verses_field_on_the_verses_field`) to pin the tightened `verses` regex.

---

## Issues

| # | Issue | Location | Severity | Status | Resolution |
|---|---|---|---|---|---|
| 1 | `ResolveVersesController` double-wrapped the collection: `new VerseCollection(VerseResource::collection(...))`. Worked because `collectResource()` tolerates pre-wrapped items, but redundant with `$collects = VerseResource::class`. | `app/Http/Controllers/Api/V1/Verses/ResolveVersesController.php:27` | Suggestion | Fixed | Pass `$result->verses` directly; dropped the `VerseResource` import. |
| 2 | Daily-verse cache TTL hard-coded as a private controller constant, duplicating the `BibleCacheHeaders` pattern already used by other Bible endpoints. | `app/Http/Controllers/Api/V1/Verses/GetDailyVerseController.php:17` | Suggestion | Fixed | Added `BibleCacheHeaders::DAILY_VERSE_MAX_AGE = 3600`; controller consumes it. |
| 3 | `assertHeader('Cache-Control', 'max-age=3600, public')` asserted Symfony's alphabetical directive serialization — brittle against future Symfony changes or added directives. | `tests/Feature/Api/V1/Verses/GetDailyVerseTest.php:42` | Suggestion | Fixed | Split into two `assertStringContainsString` checks (`public` and `max-age=3600`) against the raw header value. |
| 4 | `DailyVerseFactory` used `fake()->unique()->date()`. The DB constraint already enforces uniqueness and every test that cares passes `for_date` explicitly. `unique()` accumulates across the suite with no benefit. | `database/factories/DailyVerseFactory.php:23` | Suggestion | Fixed | Dropped `->unique()`. |
| 5 | `verses` regex `/^[0-9,\-]+$/` accepted degenerate strings (`,-,`, `1,,3`, `-`). Those bubbled to `InvalidReferenceException` → `422` with `errors.reference`, misattributing the failure to the wrong field. | `app/Http/Requests/Verses/ResolveVersesRequest.php:33` | Warning | Fixed | Tightened to `/^\d+(-\d+)?(,\d+(-\d+)?)*$/`; added feature test `test_it_rejects_malformed_verses_field_on_the_verses_field` asserting `422` with `errors.verses`. |
| 6 | `BibleVerseQueryBuilder::lookupReferences()` issues one query per `(version, book, chapter)` group. For multi-chapter references this is N queries. | `app/Domain/Bible/QueryBuilders/BibleVerseQueryBuilder.php:67-89` | Suggestion | Deferred | Plan-acknowledged ("bounded by explicit ranges"); optimisation to a single `whereIn(tuple, …)` only worth doing when a hot path surfaces. Tracked by `test_it_batches_queries_by_version_book_chapter`. |
| 7 | `ResolveVersesAction::wholeChapterVerseNumbers()` uses process-level `static $cache` keyed `book\|chapter` with no tenant/version dimension. | `app/Domain/Verses/Actions/ResolveVersesAction.php:118` | Suggestion | Skipped | Bible catalog is effectively immutable in production and the shape is catalog-wide (not per-version). Safe as-is. Flag only if the catalog ever becomes per-tenant. |

---

## Audit-dimension spot-checks

- **Architecture compliance.** Controllers thin, delegate to Actions, return Resources; DTOs `readonly` under `DataTransferObjects/`; Form Request → Action → Resource layering; `api-key-or-sanctum` + `resolve-language` middleware on both routes; exception handlers live in `bootstrap/app.php`. No magic strings; `final` everywhere; `strict_types=1` across all new files.
- **API design.** Correct verbs (`GET` for both reads), status codes (`200` / `422` / `404` / `401`), partial resolution via `meta.missing` with `200` per resolved open question #1, versioned under `/api/v1`. Error envelope matches the project's JSON shape.
- **Security.** Both routes gated by `api-key-or-sanctum`. `api_client` attribute, `ResolveRequestLanguage` attribute, and `preferred_version` reads are all null-safe. No raw SQL; inputs typed and regex-validated. Cache-Control `public` on daily-verse is appropriate — response is language-agnostic and per-date (not per-user).
- **Performance.** Query batching by `(version, book, chapter)` proven by unit test. Catalog lookups for `BibleVersion`/`BibleBook` collapsed into two `whereIn` queries before per-group verse selects. Daily verse is a single-row read indexed on `for_date` unique.
- **Test coverage.** Feature + unit tests cover every AC. Added one test for the regex-tightening so attribution is pinned. `make test` → 367 passed, 1139 assertions.
- **Documentation files.** None created — adheres to CLAUDE.md rule.

---

## Plan adherence

- Plan tasks 1–26 all `[x]`. No silent deviations; the two acknowledged deviations (query-builder location; extra malformed-date test) were already flagged in review.
- Deferred-extractions register unchanged: no new tripwire hits. `InvalidReferenceException` handler remains the cross-story concern to revisit at MBA-014.

---

## Verdict rationale

All Critical and Warning findings resolved. Suggestions accounted for (5 fixed, 1 deferred with plan pointer, 1 skipped with reason). Full suite + static analysis + formatter green. Story advances to `done`.
