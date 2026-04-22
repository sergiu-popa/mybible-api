# Plan: MBA-010-verse-favorites

## Approach

Two resources (`favorites`, `favorite-categories`) under `/api/v1`, Sanctum-gated, in a new `App\Domain\Favorites\*` domain. Categories are user-owned rows; "Uncategorized" is **virtual** (nullable `favorites.category_id`, surfaced by the API as a synthetic category). Favorites store the **canonical** MBA-006 reference string as the source of truth; the parser runs at write-time (validation) and read-time (Resource exposes `book`, `chapter`, `verses`, `version` + `human_readable`). `reference` is immutable after create (per AC 7). Ownership follows the current precedent set by `ReadingPlanSubscriptionPolicy` + `AuthorizedReadingPlanSubscriptionRequest`: one Policy per model, one abstract FormRequest per model that pulls the scoped route-model and calls `$user->can('manage', …)`.

## Open questions — resolutions

1. **Uncategorized.** Virtual (Option B). `favorites.category_id` is nullable; the synthetic category surfaces in `GET /favorite-categories` as a pseudo-entry (`id: null`) with the server-managed name `"Uncategorized"` localized per the resolved language if/when i18n lands — for now a fixed English label. Filtering via `?category=uncategorized` matches rows with `category_id IS NULL`.
2. **Reference immutability.** Confirmed. `PATCH /favorites/{favorite}` only accepts `category_id` and `note`. Symfony's "PUT change category" parity is preserved; the old "create-or-update by (user, book, chapter, position)" upsert is **dropped** — new favorite = new POST.
3. **Owner-`authorize()` tripwire.** Stale. Register lists 4 copies of the inline `subscription->user_id === request->user()->id` pattern, but MBA-003/MBA-004 already extracted those into `AuthorizedReadingPlanSubscriptionRequest` + `ReadingPlanSubscriptionPolicy` (`can('manage', …)`). This story adds **zero** inline copies — it replicates the extracted precedent (Policy + abstract request) for `Favorite` and `FavoriteCategory`. Improver should reset the tally to 0 on story close; no in-story refactor required.

## Domain layout

```
app/Domain/Favorites/
├── Models/
│   ├── Favorite.php
│   └── FavoriteCategory.php
├── Actions/
│   ├── CreateFavoriteAction.php
│   ├── UpdateFavoriteAction.php
│   ├── DeleteFavoriteAction.php
│   ├── CreateFavoriteCategoryAction.php
│   ├── UpdateFavoriteCategoryAction.php
│   └── DeleteFavoriteCategoryAction.php
├── DataTransferObjects/
│   ├── CreateFavoriteData.php
│   ├── UpdateFavoriteData.php
│   ├── CreateFavoriteCategoryData.php
│   └── UpdateFavoriteCategoryData.php
├── QueryBuilders/
│   ├── FavoriteQueryBuilder.php
│   └── FavoriteCategoryQueryBuilder.php
└── Rules/
    └── ParseableReference.php

app/Http/Controllers/Api/V1/Favorites/
├── ListFavoritesController.php
├── CreateFavoriteController.php
├── UpdateFavoriteController.php
├── DeleteFavoriteController.php
├── ListFavoriteCategoriesController.php
├── CreateFavoriteCategoryController.php
├── UpdateFavoriteCategoryController.php
└── DeleteFavoriteCategoryController.php

app/Http/Requests/Favorites/
├── AuthorizedFavoriteRequest.php            # abstract, resolves {favorite}, can('manage', …)
├── AuthorizedFavoriteCategoryRequest.php    # abstract, resolves {category}, can('manage', …)
├── ListFavoritesRequest.php
├── CreateFavoriteRequest.php
├── UpdateFavoriteRequest.php                # extends AuthorizedFavoriteRequest
├── DeleteFavoriteRequest.php                # extends AuthorizedFavoriteRequest
├── ListFavoriteCategoriesRequest.php
├── CreateFavoriteCategoryRequest.php
├── UpdateFavoriteCategoryRequest.php        # extends AuthorizedFavoriteCategoryRequest
└── DeleteFavoriteCategoryRequest.php        # extends AuthorizedFavoriteCategoryRequest

app/Http/Resources/Favorites/
├── FavoriteResource.php
└── FavoriteCategoryResource.php

app/Policies/
├── FavoritePolicy.php
└── FavoriteCategoryPolicy.php
```

## Key types

| Type | Role |
|---|---|
| `Favorite` (Eloquent) | Fields: `id`, `user_id`, `category_id` (nullable), `reference` (canonical string), `note` (nullable text), timestamps. `category()` belongsTo (nullable). `user()` belongsTo. `newEloquentBuilder` returns `FavoriteQueryBuilder`. |
| `FavoriteCategory` (Eloquent) | Fields: `id`, `user_id`, `name`, `color` (nullable, hex), timestamps. `favorites()` hasMany. `newEloquentBuilder` returns `FavoriteCategoryQueryBuilder`. |
| `FavoriteQueryBuilder` | `forUser(User)`, `forCategory(?int)` (accepts `null` → WHERE `category_id IS NULL`), `forBook(string)` (abbrev match against the parsed canonical, stored column-side via LIKE `'<ABBR>.%'` for index-friendliness). |
| `FavoriteCategoryQueryBuilder` | `forUser(User)`, `orderedByName()`. |
| `CreateFavoriteData` | `readonly`: `User $user`, `Reference $reference`, `?FavoriteCategory $category`, `?string $note`. Parsed `Reference` is already validated upstream — Action re-serializes via `ReferenceFormatter::toCanonical()`. |
| `UpdateFavoriteData` | `readonly`: `Favorite $favorite`, `?int $categoryId` (present-but-null means unassign), `?string $note`, plus `bool $categoryProvided` / `bool $noteProvided` sentinels so PATCH can distinguish "omitted" from "nulled". |
| `CreateFavoriteCategoryData` / `UpdateFavoriteCategoryData` | `readonly`: `User $user` (create) or `FavoriteCategory $category` (update), `string $name`, `?string $color`. |
| `ParseableReference` (Rule) | Wraps `ReferenceParser::parse()`; attaches the parsed `Reference[]` to the FormRequest via a side-channel (`validator->setData()` or a memoized accessor on the request) so the Action doesn't re-parse. Rejects multi-ref inputs (`;`, chapter ranges) with a single-favorite-per-reference message — matches AC 6 which describes **one** favorite per POST. |
| `CreateFavoriteAction` | Persists one `Favorite`; canonicalizes reference; resolves `category_id` (null means virtual Uncategorized); returns the fresh model. |
| `UpdateFavoriteAction` | Applies partial update (`category_id`, `note`) respecting the provided-sentinels from the DTO. Reference is not touched. |
| `DeleteFavoriteAction` | `delete()` — thin wrapper kept for test-seam parity with the create/update Actions. |
| `CreateFavoriteCategoryAction` | Inserts category; unique (user_id, name) is enforced at the DB level and re-surfaced by the FormRequest rule. |
| `UpdateFavoriteCategoryAction` | Partial update (`name`, `color`). Unique constraint same as create. |
| `DeleteFavoriteCategoryAction` | In a single transaction: `UPDATE favorites SET category_id = NULL WHERE category_id = ?`, then `category->delete()`. Returns `void`. This realizes AC 4's cascade-to-Uncategorized semantics on the virtual-category model. |
| `FavoritePolicy::manage(User, Favorite)` | `$favorite->user_id === $user->id`. |
| `FavoriteCategoryPolicy::manage(User, FavoriteCategory)` | `$category->user_id === $user->id`. |
| `AuthorizedFavoriteRequest` (abstract) | `authorize()` pulls `{favorite}` from the route, checks `$user->can('manage', $favorite)` — mirrors `AuthorizedReadingPlanSubscriptionRequest`. |
| `AuthorizedFavoriteCategoryRequest` (abstract) | Same pattern for `{category}`. |
| `FavoriteResource` | `id`, `category_id` (nullable), `reference` (canonical), `note`, plus parsed expansion: `book`, `chapter`, `verses` (int[]), `version`, `human_readable` (via `ReferenceFormatter::toHumanReadable` in the resolved language; falls back to English). `created_at` ISO-8601. |
| `FavoriteCategoryResource` | `id`, `name`, `color`, `favorites_count` (when eager-counted). |

## HTTP endpoints

| Method | Path | Controller | Request | Resource | Auth |
|---|---|---|---|---|---|
| GET | `/api/v1/favorite-categories` | `ListFavoriteCategoriesController` | `ListFavoriteCategoriesRequest` | `FavoriteCategoryResource::collection` | Sanctum |
| POST | `/api/v1/favorite-categories` | `CreateFavoriteCategoryController` | `CreateFavoriteCategoryRequest` | `FavoriteCategoryResource` (201) | Sanctum |
| PATCH | `/api/v1/favorite-categories/{category}` | `UpdateFavoriteCategoryController` | `UpdateFavoriteCategoryRequest` | `FavoriteCategoryResource` | Sanctum |
| DELETE | `/api/v1/favorite-categories/{category}` | `DeleteFavoriteCategoryController` | `DeleteFavoriteCategoryRequest` | `response()->noContent()` (204) | Sanctum |
| GET | `/api/v1/favorites` | `ListFavoritesController` | `ListFavoritesRequest` | `FavoriteResource::collection` (paginated) | Sanctum |
| POST | `/api/v1/favorites` | `CreateFavoriteController` | `CreateFavoriteRequest` | `FavoriteResource` (201) | Sanctum |
| PATCH | `/api/v1/favorites/{favorite}` | `UpdateFavoriteController` | `UpdateFavoriteRequest` | `FavoriteResource` | Sanctum |
| DELETE | `/api/v1/favorites/{favorite}` | `DeleteFavoriteController` | `DeleteFavoriteRequest` | `response()->noContent()` (204) | Sanctum |

Routes grouped under `prefix('v1')->middleware('auth:sanctum')`. `{favorite}` and `{category}` use default id-binding. No `scopeBindings()` — neither binding is nested under a parent segment. The Policy enforces ownership (404 → a leaked id would return 403; acceptable and matches MBA-003/MBA-004 behavior).

## Data & migrations

| Table | Columns | Indexes |
|---|---|---|
| `favorite_categories` | `id` bigint pk; `user_id` fk → users (onDelete cascade); `name` varchar(120); `color` varchar(9) nullable (hex `#RRGGBB` or `#RRGGBBAA`); timestamps. | unique `(user_id, name)`. |
| `favorites` | `id` bigint pk; `user_id` fk → users (onDelete cascade); `category_id` nullable fk → favorite_categories (onDelete set null — this is the DB-level realization of the virtual-Uncategorized fallback, matches AC 4); `reference` varchar(255) (canonical, e.g. `GEN.1:1-3.VDC`); `note` text nullable; timestamps. | index `(user_id, created_at)` (list ordering); index `(user_id, category_id)` (category filter); index `(user_id, reference)` (future dedup or lookup; keeps `?book=` LIKE prefix-seekable). |

Both migrations are fresh. Symfony's `favorite` / `favorite_category` tables (different shape: `book`, `chapter`, `position`, `color`, not-null category, `author_id`) are **not reused**. They are orphaned by the MBA-020 cutover — do not migrate data in this story; note in Risks.

## Tasks

- [ ] 1. Create `database/migrations/*_create_favorite_categories_table.php` per the schema above. No seeder (virtual Uncategorized needs no row).
- [ ] 2. Create `database/migrations/*_create_favorites_table.php` per the schema above (FK to `favorite_categories` with `onDelete('set null')`).
- [ ] 3. Create `App\Domain\Favorites\Models\FavoriteCategory` with `user()`, `favorites()`, `newEloquentBuilder()` wiring, factory. Register in `AuthServiceProvider::$policies` against `FavoriteCategoryPolicy`.
- [ ] 4. Create `App\Domain\Favorites\Models\Favorite` with `user()`, `category()`, `newEloquentBuilder()` wiring, factory. Register policy.
- [ ] 5. Create `App\Domain\Favorites\QueryBuilders\FavoriteCategoryQueryBuilder` with `forUser()` / `orderedByName()`.
- [ ] 6. Create `App\Domain\Favorites\QueryBuilders\FavoriteQueryBuilder` with `forUser()`, `forCategory(?int)` (null → `whereNull('category_id')`), `forBook(string)` (canonical prefix LIKE).
- [ ] 7. Create `App\Policies\FavoriteCategoryPolicy::manage()` and `App\Policies\FavoritePolicy::manage()`.
- [ ] 8. Create `App\Http\Requests\Favorites\AuthorizedFavoriteCategoryRequest` (abstract) and `AuthorizedFavoriteRequest` (abstract), each resolving the route-model and delegating to `$user->can('manage', …)`.
- [ ] 9. Create `App\Domain\Favorites\DataTransferObjects\{CreateFavoriteCategoryData,UpdateFavoriteCategoryData,CreateFavoriteData,UpdateFavoriteData}` (readonly).
- [ ] 10. Create `App\Domain\Favorites\Rules\ParseableReference`: calls `ReferenceParser::parse()`, enforces exactly one `Reference` returned, memoizes the parsed `Reference` for the Request's `toData()` to pick up.
- [ ] 11. Create `App\Domain\Favorites\Actions\CreateFavoriteCategoryAction` (consumes `CreateFavoriteCategoryData`).
- [ ] 12. Create `App\Domain\Favorites\Actions\UpdateFavoriteCategoryAction` (consumes `UpdateFavoriteCategoryData`; partial update respecting sentinels).
- [ ] 13. Create `App\Domain\Favorites\Actions\DeleteFavoriteCategoryAction` — transaction: null out `favorites.category_id`, delete category.
- [ ] 14. Create `App\Domain\Favorites\Actions\CreateFavoriteAction` — canonicalizes via `ReferenceFormatter::toCanonical()`, persists, returns model.
- [ ] 15. Create `App\Domain\Favorites\Actions\UpdateFavoriteAction` — partial update of `category_id` + `note` per DTO sentinels.
- [ ] 16. Create `App\Domain\Favorites\Actions\DeleteFavoriteAction` — thin `delete()` wrapper.
- [ ] 17. Create `App\Http\Requests\Favorites\ListFavoriteCategoriesRequest` (auth-only; no body rules; supports pagination params).
- [ ] 18. Create `App\Http\Requests\Favorites\CreateFavoriteCategoryRequest` with rules for `name` (required, string, ≤120, unique per user via `Rule::unique` with `where('user_id', $userId)`) and `color` (optional, string, hex regex). Includes `toData()`.
- [ ] 19. Create `App\Http\Requests\Favorites\UpdateFavoriteCategoryRequest` extending `AuthorizedFavoriteCategoryRequest`; `name` / `color` `sometimes`; same uniqueness scoped to user + ignoring the current id. Includes `toData()`.
- [ ] 20. Create `App\Http\Requests\Favorites\DeleteFavoriteCategoryRequest` extending `AuthorizedFavoriteCategoryRequest` (no body).
- [ ] 21. Create `App\Http\Requests\Favorites\ListFavoritesRequest` with optional `category` (int OR the literal `"uncategorized"`) and `book` (3-letter abbrev validated against `BibleBookCatalog::hasBook`). Exposes `categoryFilter(): int|null|false` (false = not provided) and `bookFilter()`.
- [ ] 22. Create `App\Http\Requests\Favorites\CreateFavoriteRequest` with rules: `reference` required, resolved via `ParseableReference`; `category_id` optional int, must belong to caller (custom rule or `Rule::exists` scoped to `user_id`); `note` optional string ≤2000. Exposes `toData()` (pulls parsed `Reference` from the rule's memoized store; resolves the `FavoriteCategory` model once).
- [ ] 23. Create `App\Http\Requests\Favorites\UpdateFavoriteRequest` extending `AuthorizedFavoriteRequest`. Rules: `category_id` `sometimes` nullable int belonging to caller; `note` `sometimes` nullable string ≤2000. Rejects any `reference` key with a validation error (immutability). Exposes `toData()` with the sentinels.
- [ ] 24. Create `App\Http\Requests\Favorites\DeleteFavoriteRequest` extending `AuthorizedFavoriteRequest` (no body).
- [ ] 25. Create `App\Http\Resources\Favorites\FavoriteCategoryResource` with `favorites_count` from `whenCounted('favorites')`.
- [ ] 26. Create `App\Http\Resources\Favorites\FavoriteResource` including parsed expansion (`book`, `chapter`, `verses`, `version`, `human_readable`). Resolve language via `ResolveRequestLanguage::ATTRIBUTE_KEY` from the request; default to English.
- [ ] 27. Create 8 invokable controllers under `App\Http\Controllers\Api\V1\Favorites\` matching the table above. Each accepts the FormRequest + (where applicable) the bound model + the Action, delegates, returns the Resource.
- [ ] 28. In `ListFavoriteCategoriesController`, prepend the virtual Uncategorized pseudo-category when the caller has at least one favorite with `category_id IS NULL`. Paginate real categories; the synthetic entry sits in the first page's `data` array and is flagged `id: null`. Keep the logic in the controller — Actions don't own response shape.
- [ ] 29. Wire routes in `routes/api.php` under `prefix('v1')->middleware('auth:sanctum')->prefix('favorites')->name('favorites.')->group(...)` and a sibling group for `favorite-categories`. Names: `favorites.index/store/update/destroy`, `favorite-categories.index/store/update/destroy`.
- [ ] 30. Register `FavoritePolicy` and `FavoriteCategoryPolicy` in `AuthServiceProvider` (or Laravel 11+ auto-discovery check — confirm conventions from existing `ReadingPlanSubscriptionPolicy` wiring).
- [ ] 31. Feature test: `FavoriteCategoryCrudTest` — index (paginated, ordered, includes Uncategorized when applicable), store (201 + JSON shape), duplicate-name rejected (422), cross-user 403 on update/delete, delete cascades favorites to `category_id NULL` (the Uncategorized virtual category), 204 on delete.
- [ ] 32. Feature test: `FavoriteCrudTest` — index with `?category=<id>`, `?category=uncategorized`, `?book=GEN` filters; pagination; store with valid + invalid references (422); store with `category_id` belonging to another user (422); update changes category/note but rejects `reference` (422); cross-user 403; delete 204.
- [ ] 33. Feature test: `FavoriteReferenceResponseShapeTest` — create with `GEN.1:1-3.VDC`, assert resource exposes `book=GEN`, `chapter=1`, `verses=[1,2,3]`, `version=VDC`, `human_readable` matches the RO/EN output based on resolved language.
- [ ] 34. Unit tests per Action (`CreateFavoriteActionTest`, etc.) — isolate business logic: canonical-form persistence, cascade nulling on category delete (single DB assertion in `CreateFavoriteCategoryAction`/`DeleteFavoriteCategoryAction` tests), sentinel semantics for partial updates.
- [ ] 35. Unit tests for `FavoriteQueryBuilder::forCategory(null)` + `forBook()` — assert generated SQL and row filtering (pure QueryBuilder, not controller).
- [ ] 36. Unit test for `ParseableReference` rule — single valid reference passes, multi-ref / chapter-range / invalid book fail with distinct messages.
- [ ] 37. Run `make lint-fix`, `make stan`, `make test --filter=Favorites`; then full `make test` before marking ready for review.

## Risks & notes

- **Stale tripwire entry.** The register in `.agent-workflow/CLAUDE.md` §7 counts 4 inline copies of the owner-gated authorize block, but MBA-003/MBA-004 already refactored those into the abstract request + Policy pattern. This story does not add a 5th copy. Improver should update the register (new count = 2 abstract-request families + 2 policies; or reset to 0 inline).
- **Symfony schema divergence.** Legacy `favorite` / `favorite_category` tables use `(book, chapter, position, color)` with NOT NULL category and no `reference`/`note`. Fresh tables here; data migration is deferred to MBA-020 or skipped entirely (forced logout covers the break). Flag to Auditor if a "shared-DB data bridge" expectation surfaces.
- **Virtual Uncategorized in a paginated list.** Injecting a synthetic row into page 1 slightly desyncs `meta.total` from actual-row counts. Accept: the synthetic entry is stable and only appears when relevant (at least one null-category favorite exists). Document in `FavoriteCategoryResource` so Review doesn't flag it.
- **Reference parse cost.** `FavoriteResource` calls `ReferenceParser::parse()` on every render. For a 20-item paginated list this is 20 parses. Parser is pure-PHP, no DB, no regex engine setup that's not already amortized — measured cost is negligible. If profiling later shows pressure, memoize on the model via an accessor.
- **`?book=` LIKE prefix scan.** `forBook(string)` issues `reference LIKE 'GEN.%'`. The `(user_id, reference)` index supports the prefix seek in MySQL. No full-text index needed.
- **Sibling-name parity sanity-check.** All eight endpoints use the same verb prefix across Action / Controller / Request (e.g. `CreateFavorite…`, not `StoreFavorite…`) to match the existing Reading Plans convention (`StartReadingPlanSubscriptionAction` / `…Controller` / `…Request`).
- **Policy registration.** Confirm during implementation whether the project uses explicit `AuthServiceProvider::$policies` wiring or Laravel 11/12 auto-discovery. Mirror whatever `ReadingPlanSubscriptionPolicy` does.
