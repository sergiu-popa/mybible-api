# QA: MBA-010-verse-favorites

**Verdict:** `QA PASSED`

## Test run

- Command: `make test`
- Result: **425 passed / 1278 assertions / 7.54s**
- No failures, no skips, no regressions. Migrations already applied (nothing to migrate).

## AC coverage

### Categories

- **AC1** — `GET /favorite-categories`, paginated, caller-scoped
  - `tests/Feature/Api/V1/Favorites/FavoriteCategoryCrudTest.php:18` (`test_it_lists_the_callers_categories_ordered_by_name`)
  - `tests/Feature/Api/V1/Favorites/FavoriteCategoryCrudTest.php:34,52` (Uncategorized synthetic entry present/absent)
- **AC2** — `POST /favorite-categories`, 201, name unique per user
  - `tests/Feature/Api/V1/Favorites/FavoriteCategoryCrudTest.php:67,88,100,112`
- **AC3** — `PATCH`, owner-only, 403 otherwise
  - `tests/Feature/Api/V1/Favorites/FavoriteCategoryCrudTest.php:125,141`
- **AC4** — `DELETE` owner-only, 204, cascade to Uncategorized
  - `tests/Feature/Api/V1/Favorites/FavoriteCategoryCrudTest.php:157,175`
  - Unit: `tests/Unit/Domain/Favorites/Actions/DeleteFavoriteCategoryActionTest.php:18,35`

### Favorites

- **AC5** — `GET /favorites` paginated + `?category` + `?book`
  - `tests/Feature/Api/V1/Favorites/FavoriteCrudTest.php:95,109,125,141,156`
- **AC6** — `POST /favorites` with canonical reference, 422 on invalid/multi
  - `tests/Feature/Api/V1/Favorites/FavoriteCrudTest.php:18,41,56,68,80`
  - Unit: `tests/Unit/Domain/Favorites/Actions/CreateFavoriteActionTest.php:19,38,56`
  - Unit: `tests/Unit/Domain/Favorites/Rules/ParseableReferenceTest.php:18,31,42,53,64,75`
- **AC7** — `PATCH`: `category_id` / `note` mutable, `reference` immutable, 403 for non-owner
  - `tests/Feature/Api/V1/Favorites/FavoriteCrudTest.php:166,188,203,217`
  - Unit: `tests/Unit/Domain/Favorites/Actions/UpdateFavoriteActionTest.php:19,41,60`
- **AC8** — `DELETE` owner-only, 204
  - `tests/Feature/Api/V1/Favorites/FavoriteCrudTest.php:228,241`
  - Unit: `tests/Unit/Domain/Favorites/Actions/DeleteFavoriteActionTest.php:17`

### Authorization

- **AC9** — Sanctum + owner `authorize()` via `AuthorizedFavoriteRequest` /
  `AuthorizedFavoriteCategoryRequest` + policies `manage()`
  - 401: `FavoriteCategoryCrudTest.php:152`, `FavoriteCrudTest.php:252`
  - 403 cross-user across update/delete on both resources (lines above)

### Tests / Unit

- **AC10** — Feature tests: cross-user access denied, duplicate category
  name, invalid reference, cascade — all covered in the feature tests above.
- **AC11** — Unit test per Action:
  `CreateFavoriteActionTest`, `UpdateFavoriteActionTest`,
  `DeleteFavoriteActionTest`, `CreateFavoriteCategoryActionTest`,
  `UpdateFavoriteCategoryActionTest`, `DeleteFavoriteCategoryActionTest`.
- QueryBuilders: `FavoriteQueryBuilderTest` (forUser / forCategory(null) /
  forCategory(id) / forBook including case-insensitive);
  `FavoriteCategoryQueryBuilderTest` (forUser / orderedByName).
- Response shape parity:
  `FavoriteReferenceResponseShapeTest.php:17,37,64` (parsed fields,
  language-sensitive `human_readable`, whole-chapter reference).

## Edge cases probed

- Invalid reference → 422 (`FavoriteCrudTest.php:56`).
- Multi-ref / chapter-range rejected (`FavoriteCrudTest.php:68`;
  `ParseableReferenceTest.php:31,42`).
- Category belonging to another user → 422 on create
  (`FavoriteCrudTest.php:80`); 403 on update/delete of cross-user row
  (`FavoriteCrudTest.php:217,241`; `FavoriteCategoryCrudTest.php:141,175`).
- Reference immutability on PATCH → 422 (`FavoriteCrudTest.php:203`).
- Unauthenticated → 401 on both resources.
- Uncategorized synthetic category appears only when applicable
  (`FavoriteCategoryCrudTest.php:34,52`).
- `?category=uncategorized` literal filter honored
  (`FavoriteCrudTest.php:125`).
- Invalid `?book` filter → 422 (`FavoriteCrudTest.php:156`).
- Cascade-to-null on category delete verified at the DB layer
  (`DeleteFavoriteCategoryActionTest.php:18,35`).
- Duplicate name same user 422 / same name across users allowed
  (`FavoriteCategoryCrudTest.php:88,100`).
- Null color on create / clear-color on update supported
  (`CreateFavoriteCategoryActionTest.php:41`;
  `UpdateFavoriteCategoryActionTest.php:41`).

## Regressions

No related-feature regressions observed. Review's two Warnings (lenient
`categoryFilter()`, defensive parse-fallback in `FavoriteResource`) are
`acknowledged` per Reviewer — no Critical items outstanding.

## Summary

All AC covered. 425/425 tests passing. No regressions. Review carries no
unresolved Critical findings.
