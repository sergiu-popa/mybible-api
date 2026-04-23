# QA: MBA-012-devotionals

**QA Agent:** QA
**Verdict:** `QA PASSED`

## Test Totals

`make test` — **419 passed, 1284 assertions, 0 failures** (7.20s).

## Acceptance Criteria Coverage

| AC | Covering test(s) |
|---|---|
| 1. GET today's devotional by language+type | `tests/Feature/Api/V1/Devotionals/ShowDevotionalTest.php:27` |
| 2. Optional `date` query param | `tests/Feature/Api/V1/Devotionals/ShowDevotionalTest.php:57` |
| 3. Response shape `{ id, date, type, language, title, content, passage?, author? }` | `tests/Unit/Http/Resources/Devotionals/DevotionalResourceTest.php` + `ShowDevotionalTest.php:27,57` (assertJsonStructure) |
| 4. 404 on missing tuple | `ShowDevotionalTest.php:75` + `:87` (no language fallback) |
| 5. Protected by `api-key-or-sanctum` | `ShowDevotionalTest.php:123` (missing-auth 401) |
| 6. `Cache-Control: public, max-age=3600` | `ShowDevotionalTest.php:50-52` |
| 7. Archive paginated newest-first, max 30 | `ListDevotionalArchiveTest.php:27` + `:121` (per_page cap) |
| 8. `from` / `to` window | `ListDevotionalArchiveTest.php:54` + `:142` (`to < from` 422) |
| 9. Favorites list with embedded devotional | `ListDevotionalFavoritesTest.php:19` + `DevotionalFavoriteResourceTest.php` |
| 10. Toggle 201 create / 200 delete | `ToggleDevotionalFavoriteTest.php:19,40` |
| 11. Cross-user blocked | `ListDevotionalFavoritesTest.php:38` + `ToggleDevotionalFavoriteTest.php:69` |
| 12. Feature tests (today, past date, 404, both types, both languages, archive pagination, date window) | All tests under `tests/Feature/Api/V1/Devotionals/` |
| 13. Favorite tests (insert, remove, cross-user) | `ToggleDevotionalFavoriteTest.php:19,40,69,89` |
| 14. Unit tests for Actions | `tests/Unit/Domain/Devotional/Actions/FetchDevotionalActionTest.php`, `ToggleDevotionalFavoriteActionTest.php` |

## Edge Cases Probed

- Future-dated exclusion from archive: `ListDevotionalArchiveTest.php:83`.
- Type mix isolation: `ListDevotionalArchiveTest.php:102`.
- Unknown enum rejection: `ShowDevotionalTest.php:105`.
- Malformed date rejection: `ShowDevotionalTest.php:114`.
- Unknown `devotional_id` → 422: `ToggleDevotionalFavoriteTest.php:59`.
- Unauthenticated favorite list / toggle → 401: `ListDevotionalFavoritesTest.php:73`, `ToggleDevotionalFavoriteTest.php:89`.
- Language no-fallback (RO asked, only HU exists) → 404: `ShowDevotionalTest.php:87`.

## Regressions

None. Full suite (419 tests) green; no prior-story tests fail.

## Review Critical Items

Review (`review.md`) had **no Critical or Warning items** — verdict `APPROVE` with only non-blocking suggestions. Nothing to gate on.

## Verdict

All 14 AC covered by passing tests, no regressions, no outstanding Critical items → **QA PASSED**.
