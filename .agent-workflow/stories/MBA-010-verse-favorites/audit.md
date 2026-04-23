# Audit: MBA-010-verse-favorites

**Verdict:** `PASS`
**Auditor:** Auditor agent
**Scope:** Diff against `main` for commits `dadcf0e`, `56fa7ff`, `904638b`, `b0cf871` — 59 files, +2837/-38.

---

## Dimensions reviewed

- Architecture compliance (Beyond CRUD + project conventions)
- Code quality (readability, redundancy, fragility)
- API design (verbs, status codes, envelope, versioning)
- Security (Sanctum, ownership, cross-user leakage, input validation)
- Performance (N+1, redundant writes, container lookups)
- Test coverage (feature + unit; happy + failure + edge)

---

## Issues

| #  | Issue                                                                                                                                                                                                  | Location                                                                            | Severity   | Status                 | Resolution                                                                                                                                                                           |
|----|--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|-------------------------------------------------------------------------------------|------------|------------------------|--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| 1  | `DeleteFavoriteCategoryAction` double-writes: explicit `UPDATE favorites SET category_id = NULL` runs alongside the FK's `ON DELETE SET NULL` cascade. Every delete costs two writes where one suffices; no observer is wired to justify the duplication. | `app/Domain/Favorites/Actions/DeleteFavoriteCategoryAction.php:27-33` (pre-audit)  | Warning    | Fixed                  | Removed the redundant update and the now-pointless transaction wrapper; FK cascade handles reparenting atomically. Tests (`DeleteFavoriteCategoryActionTest`, `FavoriteCategoryCrudTest::test_it_deletes_a_category_and_cascades_favorites_to_uncategorized`) still pass, proving the FK cascade covers AC 4. |
| 2  | `CreateFavoriteRequest::rules()` assigns a fresh `ParseableReference` to an instance property on every call; a second `rules()` invocation would reset the memoized `parsed()` state before `toData()` reads it. Works in practice but fragile.             | `app/Http/Requests/Favorites/CreateFavoriteRequest.php:16,28,49`                    | Warning    | Fixed                  | Replaced with a private `referenceRule()` accessor that lazily memoizes (`??=`) the rule instance, so `rules()` returns the same holder across calls and `toData()` reads a stable `parsed()`. |
| 3  | `ListFavoritesRequest::categoryFilter()` silently returns "no filter" on garbage string inputs (e.g. `?category=hunh`). Validation rule is intentionally lenient and Review acknowledged this as cross-endpoint policy, not a spot fix. | `app/Http/Requests/Favorites/ListFavoritesRequest.php:45-62`                        | Warning    | Skipped-with-reason    | Matches MBA-008's lenient query-string precedent (`ResolveVersesRequest`) and Review's documented acknowledgement. Tightening should land as a cross-endpoint policy, not here.        |
| 4  | `FavoriteResource::parseReference()` swallows `InvalidReferenceException`, returning `null` parsed fields for a poisoned row rather than surfacing the inconsistency.                                  | `app/Http/Resources/Favorites/FavoriteResource.php:59-72`                           | Warning    | Skipped-with-reason    | Intentional defensive fallback per plan ("re-parse on read for display"): keeps a single malformed row from failing the whole paginated page. Unreachable for rows written by `CreateFavoriteAction`. |
| 5  | `FavoriteResource` resolves `ReferenceFormatter` + `ReferenceParser` via `app(...)` on every row (≈40 container lookups per paginated page).                                                           | `app/Http/Resources/Favorites/FavoriteResource.php:48,66`                           | Suggestion | Skipped-with-reason    | Mirrors MBA-008 `ResolveVerses` precedent. Pure-PHP parse; measured cost negligible at 15-per-page default. Revisit if profiling shows pressure — pointer: memoize parsed `Reference` as a model accessor. |
| 6  | `UpdateFavoriteRequest::withValidator()` attaches the reference-immutability error imperatively; a declarative `'reference' => ['prohibited']` rule would express the same invariant in one line.    | `app/Http/Requests/Favorites/UpdateFavoriteRequest.php:33-44`                       | Suggestion | Skipped-with-reason    | Current approach is correct, tested, and distinct from a generic "unknown field rejected" — the custom message "cannot be changed after a favorite is created" is specifically intentional. Low-value swap; defer to a future cleanup pass. |
| 7  | `ListFavoritesRequest::categoryFilter()` tri-state `string\|int\|null` sentinel is awkward for controllers (forced string compare before int deref).                                                   | `app/Http/Requests/Favorites/ListFavoritesRequest.php:19,45-62`                     | Suggestion | Deferred-with-pointer  | Considered — fixing would ripple into the controller and tests without a user-visible change. Pointer: split into `hasCategoryFilter(): bool` + `categoryId(): ?int` helpers next time this request is touched. |
| 8  | `FavoriteCategoryResource::favorites_count` uses `whenCounted('favorites')`, but every controller preloads the count, so the field is always present. The guard is therefore inert.                     | `app/Http/Resources/Favorites/FavoriteCategoryResource.php:25`                      | Suggestion | Skipped-with-reason    | `whenCounted` is harmless defense — if a future caller forgets to preload, the field degrades to absent instead of erroring. No runtime cost. Not worth the churn.                 |
| 9  | `ListFavoriteCategoriesController` injects the synthetic `Uncategorized` entry on page 1 only, desyncing it from `meta.total`. Accepted per plan; no inline comment flags the invariant.              | `app/Http/Controllers/Api/V1/Favorites/ListFavoriteCategoriesController.php:44-58`  | Suggestion | Deferred-with-pointer  | Documented in plan and review; controller reads cleanly. Pointer: add a one-line comment near the `$isFirstPage` check next time this file is touched, so future readers see the `meta` divergence is intentional. |
| 10 | Plan deviation: `CreateFavoriteRequest::toData()` does `FavoriteCategory::query()->find($categoryId)` unscoped. Review confirmed safety via the `Rule::exists` scoping in `rules()`.                    | `app/Http/Requests/Favorites/CreateFavoriteRequest.php:58-60`                       | Suggestion | Skipped-with-reason    | Safe because validation already proves ownership; an unscoped `find` cannot leak another user's row. No change needed.                                                                  |
| 11 | Plan tripwire stale entry: §7 register shows 4 inline owner-`authorize()` copies, but MBA-003/MBA-004 already extracted those. This story adds **zero** inline copies, replicating the abstract-request + policy pattern. | `.agent-workflow/CLAUDE.md:151-152`                                                 | Suggestion | Deferred-with-pointer  | Pointer: Improver to reset the tally on story close per plan note. Out of scope for Auditor (improver.md owns the register).                                                          |

**Counts:** 0 Critical, 2 Warnings Fixed, 2 Warnings Skipped-with-reason (pre-acknowledged by Reviewer), 7 Suggestions accounted for.

---

## API design checks

- `POST` returns `201`; `PATCH` returns `200`; `DELETE` returns `204` — matches the table in `plan.md`.
- `/api/v1/favorites` and `/api/v1/favorite-categories` under the versioned prefix.
- Error envelope inherited from `bootstrap/app.php`; validation failures return the standard `{message, errors}` shape (verified in `FavoriteCrudTest::test_it_rejects_invalid_reference` and siblings).
- Sanctum protects every endpoint (feature tests cover 401 paths).
- Ownership enforced via `FavoritePolicy::manage()` + `FavoriteCategoryPolicy::manage()` through the two `Authorized*Request` abstract bases. No inline `user_id === user()->id` duplication introduced.

## Security checks

- Cross-user access rejected at the policy layer (`403`) — covered by four feature tests (`update`/`delete` on both resources).
- Cross-user category assignment on favorite create rejected at `422` via `Rule::exists('favorite_categories','id')->where('user_id', …)`.
- Reference immutability enforced on PATCH (`422`), covered by `test_it_rejects_changing_the_reference_on_update`.
- No mass-assignment exposure: `note`/`category_id`/`name`/`color` are the only mutable fields; `user_id` and `reference` (on favorites) are never sourced from request input after creation.
- Color input is regex-constrained to `#RRGGBB` / `#RRGGBBAA`.
- No SQL injection surface: all filters use parameter bindings; `forBook()` uses a LIKE with an uppercased abbreviation bound as a parameter.

## Performance checks

- `FavoriteResource` parse cost: ≈ (1 parse + 2 container lookups) per row. Default pagination = 15 per page. Negligible and reviewed.
- Indexes: `(user_id, created_at)`, `(user_id, category_id)`, `(user_id, reference)` support list ordering, category filter, and `?book=` prefix scan respectively.
- `ListFavoriteCategoriesController` emits a small extra `exists()` + `count()` on page 1 only when uncategorized favorites are present — acceptable, tested.
- Post-fix `DeleteFavoriteCategoryAction` issues one DELETE where it previously issued UPDATE + DELETE inside a transaction.

## Test coverage

- **Feature:** 30 tests across `FavoriteCrudTest`, `FavoriteCategoryCrudTest`, `FavoriteReferenceResponseShapeTest` — happy paths, 401/403/422, filters, cascade, parsed expansion, i18n-sensitive `human_readable`.
- **Unit:** 24 tests across 6 Action tests + 2 QueryBuilder tests + 1 Rule test.
- Re-ran on post-fix branch: `make test filter=Favorites` → 58 passed / 139 assertions; `make test` → 425 passed / 1278 assertions.

---

## Commands run

| Command                        | Result                              |
|--------------------------------|-------------------------------------|
| `make lint-fix`                | clean (309 files, PASS)             |
| `make stan`                    | 0 errors (289 files analyzed)       |
| `make test filter=Favorites`   | 58 passed, 139 assertions, 0.86s    |
| `make test`                    | 425 passed, 1278 assertions, 7.37s  |

---

## Verdict rationale

All Critical: none found. All Warnings either fixed (2) or explicitly acknowledged by Review with a documented cross-story rationale (2). Every Suggestion accounted for. Full test suite green pre- and post-fix. Story advances to `done`.
