# Audit: MBA-007-bible-catalog

## Verdict

**PASS** — all review suggestions addressed or consciously deferred, full suite green, stan + lint clean. Story → `done`.

## Gate results

- `make test` → 335 passed (1052 assertions), 7.32s — no regressions.
- Bible-scoped filter → 53 passed (272 assertions).
- `make stan` → 0 errors (214 files).
- `make lint` → clean (233 files).

## Findings

| # | Issue | Location | Severity | Status | Resolution |
|---|---|---|---|---|---|
| 1 | Full-export streaming query fires an extra `BibleBook::findOrFail($currentBookId)` per book transition (≤66 queries on a full canon) despite already joining `bible_books`. | `app/Domain/Bible/Support/BibleVersionExporter.php:45-69` | Warning | Fixed | Added `bible_books.abbreviation as book_abbreviation, bible_books.position as book_position` to the join's `select`, dropped the per-book query and the now-unused `BibleBook` import. Book metadata is read straight off the verse row via `$verse->getAttribute(...)`. |
| 2 | Seeder stored the long book names in `short_names` — wrong/misleading data for a column that will eventually feed a short-name consumer. | `database/seeders/BibleCanonSeeder.php:42-46` | Warning | Fixed | Introduced `placeholderShortNames()` that seeds the canonical abbreviation (e.g. `GEN`) per language as a neutral placeholder, matching `BibleBookFactory`'s default state. Inline docblock records the intent and points at the plan risk note so the follow-up work (adding `LanguageFormatter::bookShortName()`) is obvious. |
| 3 | `BibleCacheHeaders::forVersionList` cloned the query twice and ran two aggregate round-trips for every list request, including the cheap 304 path. | `app/Domain/Bible/Support/BibleCacheHeaders.php:24-41` | Suggestion | Fixed | Replaced with a single `selectRaw('MAX(updated_at) as max_updated_at, COUNT(*) as total')` via the underlying query builder (`->toBase()->reorder()`). Payload string + ETag hash are byte-identical, so existing `test_for_version_list_etag_*` assertions still hold. |
| 4 | `resolve-language` middleware was applied to the whole `bible-versions` / `books` groups, so it ran on `/bible-versions/{v}/export` and `/books/{b}/chapters` too — diverging from the plan, which scopes it to the list endpoints only. | `routes/api.php:37-51` | Suggestion | Fixed | Split the two list routes onto their own `->middleware('resolve-language')` so the shared group applies only `api-key-or-sanctum`. Export + chapters no longer pay for an unused Language resolution. |
| 5 | Redundant `/** @var BibleVerse $verse */` inside the `lazy()` loop. | `app/Domain/Bible/Support/BibleVersionExporter.php:54` | Suggestion | Fixed | Removed — `BibleVerse::query()->lazy()` is already typed as `LazyCollection<int, BibleVerse>`. |
| 6 | Plan risk note flagged that a future short-name consumer needs `LanguageFormatter::bookShortName()` (or equivalent) on the Reference domain; still unimplemented. | `app/Domain/Reference/Formatter/Languages/LanguageFormatter.php` | Suggestion | Deferred | No consumer in MBA-007. Tracking inline in the new `placeholderShortNames()` docblock; pick up when the first endpoint needs short names. |
| 7 | `ListBibleBookChaptersController` fetches chapters via `$book->chapters()->orderBy('number')->get()` even though `BibleBook::chapters()` already orders by `number`. | `app/Http/Controllers/Api/V1/Bible/ListBibleBookChaptersController.php:19` | Suggestion | Skipped | Redundant but harmless; the explicit `orderBy` at call-site documents ordering intent and is one line. Not worth the churn. |
| 8 | Migrations' `down()` unconditionally drops `bible_*` tables even though `up()` is guarded by `Schema::hasTable(...)` for the shared-prod scenario. | `database/migrations/2026_04_22_12000{0..3}_*` | Suggestion | Skipped | Plan §Risks already calls out the shared-DB assumption as a fiction in the current environment. `migrate:rollback` against prod is not part of the deploy model for this story; safer to leave the symmetrical rollback so local dev/test drop cleanly. Revisit during the prod cutover story. |
| 9 | `BibleVerseFactory` emits non-unique `(chapter, verse)` tuples and the `bible_verses` table has no unique index on `(version, book, chapter, verse)`. | `database/migrations/2026_04_22_120003_create_bible_verses_table.php:26-30`, `database/factories/BibleVerseFactory.php:27-28` | Suggestion | Deferred | Story scope explicitly defers verse writes to MBA-008. No test relies on verse-level uniqueness today. Pick up when MBA-008 introduces writes. |

No Critical issues. Every Warning fixed; Suggestions either fixed, deferred with a pointer, or consciously skipped.

## API Design (project-level dimension)

- Verbs/status codes consistent: `GET` list + show, `200`/`304`/`404`/`401`/`422` all covered by tests.
- Every endpoint flows through a Form Request + API Resource; no inline `$request->validate()`, no raw model in a response.
- JSON error envelope comes from the handler in `bootstrap/app.php`; exercised by the `422` and `401` feature tests.
- Versioning respected: all routes under `/api/v1`.
- Idempotency N/A for read-only endpoints.

## Deferred extractions tripwire

No changes. MBA-007 doesn't touch the owner-`authorize()` register or the reading-plan lifecycle Actions.
