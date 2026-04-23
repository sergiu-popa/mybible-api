# QA: MBA-013-hymnal

**Verdict:** `QA PASSED`
**Run:** `make test` (full suite, post-migrate)
**Totals:** 407 passed, 1264 assertions. Zero failures, zero skipped.

## AC coverage

| AC | Requirement | Covering test(s) |
|---|---|---|
| 1 | `GET /api/v1/hymnal-books` paginated, language filter, shape | `tests/Feature/Api/V1/Hymnal/ListHymnalBooksTest.php:28` (pagination + shape), `:51` (language filter), `:63` (song_count), `:87` (name resolved per language), `:115` (language filter validation) |
| 2 | `GET /api/v1/hymnal-books/{book}/songs` paginated, search (title + numeric), cap 200 | `tests/Feature/Api/V1/Hymnal/ListHymnalBookSongsTest.php:25` (book scoped), `:47` (order by number), `:60` (numeric branch), `:81` (textual branch), `:109` (per_page cap 422) |
| 3 | `GET /api/v1/hymnal-songs/{song}` full payload (stanzas, chorus, author, composer, copyright) | `tests/Feature/Api/V1/Hymnal/ShowHymnalSongTest.php:25` (full payload incl. stanzas/is_chorus/author/composer/copyright), `:65` (language fallback) |
| 4 | 404 on unknown book or song | `ListHymnalBookSongsTest.php:102`, `ShowHymnalSongTest.php:83` |
| 5 | `Cache-Control: public, max-age=3600` on catalog | `ListHymnalBooksTest.php:74`, `ShowHymnalSongTest.php:90` (ListHymnalBookSongs inherits the group middleware and the 200-path assertions exercise the route) |
| 6 | Catalog protected by `api-key-or-sanctum` | `ListHymnalBooksTest.php:99` (sanctum accepted), `:109` (no credentials rejected); `ListHymnalBookSongsTest.php:119`; `ShowHymnalSongTest.php:103`; api-key path exercised via `WithApiKeyClient` in happy-path tests |
| 7 | `GET /api/v1/hymnal-favorites` — Sanctum required, caller's favorites only, song embedded | `ListHymnalFavoritesTest.php:19` (own favorites only), `:34` (embedded song payload), `:54` (401 without token), `:60` (api-key alone rejected) |
| 8 | `POST /api/v1/hymnal-favorites/toggle` — 201 insert, 200 delete with `{deleted:true}` | `ToggleHymnalFavoriteTest.php:20` (201 on create), `:35` (200 + deleted:true), `:51`/`:60` (validation), `:92` (sanctum gate) |
| 9 | Cross-user access blocked | `ListHymnalFavoritesTest.php:19`; `ToggleHymnalFavoriteTest.php:69` (two users independent); `ToggleHymnalFavoriteActionTest.php:108` |
| 10 | Feature tests for catalog listing, search, detail, 404, favorites toggle, cross-user | covered by the Feature tests above |
| 11 | Unit tests for Actions | `tests/Unit/Domain/Hymnal/Actions/ToggleHymnalFavoriteActionTest.php` (5 tests: insert, delete, rollback×2, isolation) + `tests/Unit/Domain/Hymnal/QueryBuilders/HymnalSongQueryBuilderTest.php` (5 tests) |

## Edge cases probed (via existing tests)
- Empty search / whitespace query: `HymnalSongQueryBuilderTest.php:93`.
- Numeric vs textual search branches: `HymnalSongQueryBuilderTest.php:29`, `:52`, `:75`.
- Pagination overflow (`per_page=201`) rejected 422, not silently clamped: `ListHymnalBookSongsTest.php:109`.
- Transactional rollback on insert and delete: `ToggleHymnalFavoriteActionTest.php:53`, `:78`.
- Language fallback to English when locale key absent: `ShowHymnalSongTest.php:65`.
- Cross-user favorites do not leak: `ListHymnalFavoritesTest.php:19`; `ToggleHymnalFavoriteActionTest.php:108`.
- Two users favoriting the same song do not collide on the unique index: `ToggleHymnalFavoriteTest.php:69`.

## Regressions
None. Full suite 407/407 passes. No test outside the Hymnal tree was disturbed; migrations are additive (three new tables, no alters).

## Outstanding review items
None critical. The two acknowledged Warnings and the non-blocking Suggestions from `review.md` do not gate QA.
