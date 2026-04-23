# Audit: MBA-013-hymnal

**Auditor:** Auditor agent
**Verdict:** `PASS`
**Status transition:** `qa-passed` → `done`

## Scope

Full pass over the MBA-013 diff (`main...mba-013`): 41 files, +2200/-25. Hymnal domain — books, songs, favorites — five endpoints, three migrations, three QueryBuilders, one Action, five controllers, five Form Requests, four Resources, three factories, ten test files.

All artifacts (story, plan, review, qa) read. Review iteration 2 had already cleared the one blocking Warning (transactional rollback tests). QA passed the full suite at 407/1264. This audit addresses the non-blocking Suggestions from `review.md`.

## Findings

| # | Issue | Location | Severity | Status | Resolution |
|---|---|---|---|---|---|
| 1 | `ToggleHymnalFavoriteActionTest` missing transactional-rollback tests | `tests/Unit/Domain/Hymnal/Actions/ToggleHymnalFavoriteActionTest.php` | Warning | Fixed (pre-audit) | Resolved in iteration 2 by the Engineer — two rollback tests added (insert + delete branches). Verified: `make test filter=ToggleHymnalFavoriteActionTest` → 5 passed. |
| 2 | Cross-domain coupling — Hymnal resources import `App\Domain\ReadingPlans\Support\LanguageResolver` | `app/Http/Resources/Hymnal/HymnalBookResource.php:8`, `HymnalSongResource.php:8`, `HymnalSongSummaryResource.php:8` | Warning | Acknowledged | Established precedent across `Bible/*Resource` (`app/Http/Resources/Bible/BibleBookResource.php:8`). Extraction into `App\Domain\Shared\Support` belongs in a dedicated cleanup story when a third domain ships — out of scope per review iteration 2 acknowledgement. |
| 3 | `ListHymnalFavoritesTest::test_it_rejects_api_key_only` does not configure api-key client | `tests/Feature/Api/V1/Hymnal/ListHymnalFavoritesTest.php:60-66` | Warning | Acknowledged | Route is `auth:sanctum` only; api-key middleware is never consulted, so the assertion is structurally correct regardless of client config. Flipping this into a true negative would require wiring `WithApiKeyClient` only to assert the same 401 — no behaviour change, net churn. Acknowledged per review iteration 2. |
| 4 | `HymnalSongQueryBuilder::search()` interpolates JSON path into `whereRaw` | `app/Domain/Hymnal/QueryBuilders/HymnalSongQueryBuilder.php:30-38` | Suggestion | Fixed | Rewrote the `whereRaw` to bind the JSON path as a parameter (`JSON_EXTRACT(title, ?)` + `[$jsonPath, '%…%']`). Statement is now binding-driven end-to-end. Tests still pass. |
| 5 | `ToggleHymnalFavoriteRequest::toData()` runs one extra `SELECT` after `exists:` validation | `app/Http/Requests/Hymnal/ToggleHymnalFavoriteRequest.php:38` | Suggestion | Deferred | Single-hit endpoint (favorites toggle); saving one round-trip requires either passing the raw id through to the Action or collapsing validation + load. Not worth the churn; re-evaluate if a batch-toggle endpoint ships. |
| 6 | `HymnalSongFactory::definition()` uses `fake()->unique()` on title + number | `database/factories/HymnalSongFactory.php:23,27` | Suggestion | Fixed | Dropped `->unique()` on both the title sentence and the `number` column. Neither has a DB uniqueness constraint, and all tests that assert a specific value pass it explicitly. Eliminates the theoretical `OverflowException` at scale. Full suite still passes. |
| 7 | Race on concurrent toggle-create requests for the same `(user, song)` | `app/Domain/Hymnal/Actions/ToggleHymnalFavoriteAction.php:16-34` | Suggestion | Deferred | Rare client double-tap. Raw 500 with retry is acceptable. A defensive `upsert` would muddy the return-result semantics (`$created` no longer binary). Track as a potential follow-up. |
| 8 | `ListHymnalBooksRequest::languageFilter()` uses `Language::tryFrom()` where the validator guarantees a valid case | `app/Http/Requests/Hymnal/ListHymnalBooksRequest.php:55` | Suggestion | Fixed | Switched to `Language::from()` with a comment noting the validator invariant. Return type still `?Language` because the method short-circuits to `null` when the query param is absent. |
| 9 | `HymnalSongSummaryResource::book.slug` guarded by `whenLoaded('book')` while the list controller always eager-loads | `app/Http/Resources/Hymnal/HymnalSongSummaryResource.php:32`; `app/Http/Controllers/Api/V1/Hymnal/ListHymnalBookSongsController.php:35` | Suggestion | Deferred | Defensive `whenLoaded` is harmless and matches the precedent in `Bible/*Resource`. Dropping the guard would couple the resource to a single call-site contract. Keep as-is. |
| 10 | `HymnalFavoriteResource::song` guarded by `whenLoaded('song')` while the list controller always eager-loads | `app/Http/Resources/Hymnal/HymnalFavoriteResource.php:24` | Suggestion | Deferred | Same rationale as #9 — defensive pattern; keep. |
| 11 | `ShowHymnalSongController` unconditionally `->load('book')` post-binding | `app/Http/Controllers/Api/V1/Hymnal/ShowHymnalSongController.php:23` | Suggestion | Deferred | Micro-optimisation (single extra round-trip on a route that already hits one row and one join). Fixing cleanly requires a `resolveRouteBinding` override on `HymnalSong`, which is orthogonal to the audit scope. |
| 12 | `HymnalSongResource::resolveStanzas()` hard-codes `Language::En` as the fallback locale | `app/Http/Resources/Hymnal/HymnalSongResource.php:64` | Suggestion | Deferred | Cleanly resolved by adding a `LanguageResolver::resolveArray()` helper, but that touches a class outside the Hymnal domain — same cross-domain extraction concern as Finding #2. Bundle with the future `App\Domain\Shared\Support\LanguageResolver` cleanup story. |

## Dimensions

| Dimension | Assessment |
|---|---|
| Architecture compliance | Matches plan + project layout. Domain under `App\Domain\Hymnal`, HTTP under `App\Http\Controllers\Api\V1\Hymnal`. QueryBuilders wired via `newEloquentBuilder`. |
| Code quality | `strict_types` + `final` throughout. DTOs `readonly`. No inline validation, no controller business logic. |
| API design | Correct verbs (GET catalog, POST toggle) and status codes (201 on create, 200 on delete with `{deleted: true}`, 404 on unknown ids, 422 on validation, 401 on missing auth). `/api/v1` versioning respected. Cache-Control applied at the middleware group. |
| Security | Catalog routes dual-auth (`api-key-or-sanctum`); favorites sanctum-only. Cross-user isolation covered by feature + unit tests. JSON path in `search()` now bound, not interpolated (Finding #4). No raw model returns. |
| Performance | Eager-loads on list endpoints (`withSong`, book preload). `songs_count` aggregate via `withCount`. One acknowledged extra SELECT on toggle (Finding #5). |
| Test coverage | 40 Hymnal tests, 125 assertions. Happy paths, 404s, 422s, auth gates, language fallback, cross-user isolation, transactional rollback. Full suite 407/1264. |

## Gate results (post-fix)

- `make lint-fix` → **PASS** (292 files clean).
- `make stan` → **OK, no errors** (272 files).
- `make test filter=Hymnal` → **40 passed (125 assertions)**.
- `make test` (full suite) → **407 passed (1264 assertions)**. Zero failures, zero skipped.

## Deferred-extractions tripwire

Unchanged. Hymnal adds zero owner-`authorize()` blocks and zero lifecycle `withProgressCounts()` consumers. Counts remain at 4 / 2.

## Verdict rationale

All Critical: none. All Warnings: resolved (pre-audit) or acknowledged per review iteration 2. Every Suggestion accounted for (3 fixed, 6 deferred with rationale). Full suite green. Story moves to `done`.
