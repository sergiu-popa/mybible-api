# Plan: MBA-012-devotionals

## Approach

Port the Symfony devotional feature as a read-only Laravel domain: `App\Domain\Devotional\*` owns `Devotional` + `DevotionalFavorite` Eloquent models, a `DevotionalType` enum (`adults`/`kids`), and a `DevotionalQueryBuilder` that encapsulates the `(date, language, type)` lookup and the archive window. Three invokable controllers expose fetch (`show today / by date`), archive (paginated), and favorites (list + toggle). The toggle endpoint mirrors the MBA-009 `reading-progress/toggle` response envelope (`201` / `200 { deleted: true }`). Passage stays a free string — no MBA-006 parsing (story scope §Out of Scope and AC dependency note).

## Open questions — resolutions

1. **Archive default window.** No default window — return everything paginated when `from`/`to` are omitted. `from` / `to` remain optional filters. Story floated "last 90 days" as a performance hedge; pagination (max 30 per page, newest-first) already bounds the first page, and clients expect Symfony parity. Flag as a follow-up if first-page latency regresses.
2. **Passage linking.** Out of scope — `passage` stays a string. Dependencies section explicitly pushes MBA-006 cross-linking to a follow-up.
3. **Content HTML sanitisation.** Pass-through. Admin is authenticated and content is reviewed; sanitisation belongs in the admin authoring story, not the read API.

## Domain layout

```
app/Domain/Devotional/
├── Enums/DevotionalType.php
├── Models/
│   ├── Devotional.php
│   └── DevotionalFavorite.php
├── QueryBuilders/
│   ├── DevotionalQueryBuilder.php
│   └── DevotionalFavoriteQueryBuilder.php
├── Actions/
│   ├── FetchDevotionalAction.php
│   └── ToggleDevotionalFavoriteAction.php
└── DataTransferObjects/
    ├── FetchDevotionalData.php
    ├── ListDevotionalArchiveData.php
    └── ToggleDevotionalFavoriteData.php

app/Http/Controllers/Api/V1/Devotionals/
├── ShowDevotionalController.php
├── ListDevotionalArchiveController.php
├── ListDevotionalFavoritesController.php
└── ToggleDevotionalFavoriteController.php

app/Http/Requests/Devotionals/
├── ShowDevotionalRequest.php
├── ListDevotionalArchiveRequest.php
├── ListDevotionalFavoritesRequest.php
└── ToggleDevotionalFavoriteRequest.php

app/Http/Resources/Devotionals/
├── DevotionalResource.php
└── DevotionalFavoriteResource.php
```

## Key types

| Type | Role |
|---|---|
| `DevotionalType` (enum, `string`) | Cases `Adults = 'adults'`, `Kids = 'kids'`. Cast on `Devotional::$type`. Form Requests validate via `Rule::enum()`. |
| `Devotional` (model) | Columns: `id`, `date` (date), `language` (`en`/`ro`/`hu`), `type` (enum), `title`, `content` (text), `passage` (nullable string), `author` (nullable string), `created_at`, `updated_at`. No soft-deletes (admin-managed). Uses `DevotionalQueryBuilder`. `favorites()` hasMany. |
| `DevotionalFavorite` (model) | Columns: `id`, `user_id`, `devotional_id`, `created_at`. Composite unique `(user_id, devotional_id)`. Belongs-to `User` + `Devotional`. Uses `DevotionalFavoriteQueryBuilder`. |
| `DevotionalQueryBuilder` | `forLanguage(Language)`, `ofType(DevotionalType)`, `onDate(CarbonImmutable)`, `publishedUpTo(CarbonImmutable $today)`, `withinRange(?CarbonImmutable $from, ?CarbonImmutable $to)`, `newestFirst()`. |
| `DevotionalFavoriteQueryBuilder` | `forUser(User)`, `withDevotional()`, `newestFirst()`, `matching(User $user, int $devotionalId)`. |
| `FetchDevotionalAction` | `execute(FetchDevotionalData): Devotional` — applies `forLanguage` + `ofType` + `onDate`; throws `ModelNotFoundException` on miss (handler returns 404 envelope). |
| `ToggleDevotionalFavoriteAction` | `execute(ToggleDevotionalFavoriteData): ToggleResult` where `ToggleResult` is a readonly DTO exposing `bool $created` and `?DevotionalFavorite $favorite`. Mirror of MBA-009 toggle contract. Wrapped in a transaction. |
| `FetchDevotionalData` (readonly DTO, `spatie/laravel-data`) | `Language $language`, `DevotionalType $type`, `CarbonImmutable $date`. Built by `ShowDevotionalRequest`. |
| `ListDevotionalArchiveData` | `Language`, `DevotionalType`, `?CarbonImmutable $from`, `?CarbonImmutable $to`, `int $perPage`. |
| `ToggleDevotionalFavoriteData` | `User $user`, `int $devotionalId`. |
| `DevotionalResource` | Shape: `{ id, date (ISO-8601), type, language, title, content, passage?, author? }`. Nulls for optional fields are omitted via `whenNotNull`. |
| `DevotionalFavoriteResource` | Shape: `{ id, created_at, devotional: DevotionalResource }` — devotional embedded (AC 9). |

## Route-model binding

- `/devotionals/{devotional}` is **not used** — the show endpoint is keyed by query params, not path param (Symfony parity).
- Favorite toggle accepts `devotional_id` in the body — no route binding; the Form Request validates `exists:devotionals,id`. No scoped-model resolution needed.

## HTTP endpoints

| Method | Path | Controller | Request | Resource | Auth |
|---|---|---|---|---|---|
| GET | `/api/v1/devotionals` | `ShowDevotionalController` | `ShowDevotionalRequest` | `DevotionalResource` | `api-key-or-sanctum` + `resolve-language` |
| GET | `/api/v1/devotionals/archive` | `ListDevotionalArchiveController` | `ListDevotionalArchiveRequest` | `DevotionalResource::collection` (paginated) | `api-key-or-sanctum` + `resolve-language` |
| GET | `/api/v1/devotional-favorites` | `ListDevotionalFavoritesController` | `ListDevotionalFavoritesRequest` | `DevotionalFavoriteResource::collection` | `auth:sanctum` |
| POST | `/api/v1/devotional-favorites/toggle` | `ToggleDevotionalFavoriteController` | `ToggleDevotionalFavoriteRequest` | inline JSON (`201` created / `200 { deleted: true }`) | `auth:sanctum` |

Notes:
- `ShowDevotionalController` returns a plain `DevotionalResource` with a `Cache-Control: public, max-age=3600` header added via `response()->additional` or `->response()->header(...)` (engineer picks). Archive does **not** apply cache headers (lists churn more).
- `language` for the archive + show endpoints is read from `ResolveRequestLanguage::ATTRIBUTE_KEY` (middleware precedent) — the `?language=` query param drives the same pipeline, so the query param documented in the AC is the same value the middleware attaches.
- Route ordering: register `/devotionals/archive` **before** `/devotionals` to avoid any future confusion with a `{devotional}` segment.

## Data & migrations

Admin populates `devotional` from Symfony cutover; the API owns a migration that creates (or reconciles) the shared tables. Matches shared-DB posture from MBA-005.

- Migration `create_devotionals_table`: `id`, `date` (date, indexed), `language` (char(2), indexed), `type` (enum/string), `title` (string), `content` (longText), `passage` (string, nullable), `author` (string, nullable), `created_at`, `updated_at`. Composite index `(language, type, date)` for the show path; composite index `(language, type, date DESC)` or rely on the prior one — engineer picks.
- Migration `create_devotional_favorites_table`: `id`, `user_id` (fk → users.id, cascade on delete), `devotional_id` (fk → devotionals.id, cascade on delete), `created_at`. Unique `(user_id, devotional_id)`.
- If Symfony tables already exist in the shared DB under different names (`devotional_day` / `daily_reading` mentioned in the prompt), the engineer reconciles via the MBA-005 pattern (see `2026_04_22_100000_reconcile_symfony_user_table.php`) — introspect the DB first, only create/rename what's missing.

## Factories & seeders

- `DevotionalFactory` — states: `adults()`, `kids()`, `forLanguage(Language)`, `onDate(CarbonImmutable)`.
- `DevotionalFavoriteFactory` — state: `forUser(User)`.
- No seeder beyond what the factories cover; admin owns real content.

## Authorization

- `DevotionalFavoritePolicy::view(User, DevotionalFavorite)` — owner-only (`$favorite->user_id === $user->id`). Gate used from `ListDevotionalFavoritesController` by filtering via `forUser($request->user())` (the list is owner-scoped by query, not by route-model binding, so no policy check needed there — the query can't leak).
- No policy registration required for the toggle: the Form Request authorizes `$this->user() !== null` and the Action constrains favorites to the authenticated user.

## Tests

### Feature (HTTP)

- `ShowDevotionalControllerTest` — today (RO adults), today (EN kids), by `date`, 404 on missing tuple, 404 on language-without-content (per AC "no fallback"), enum rejection, `Cache-Control` header present.
- `ListDevotionalArchiveControllerTest` — pagination (max 30 enforced, default page size picked by engineer ≤ 30), newest-first ordering, `from`/`to` window, `from` only, `to` only, no window returns all paginated.
- `ListDevotionalFavoritesControllerTest` — owner list, cross-user scoping (user B never sees user A's favorites), 401 without sanctum, embedded devotional shape.
- `ToggleDevotionalFavoriteControllerTest` — create (`201`), remove (`200 { deleted: true }`), unknown `devotional_id` (`422`), cross-user cannot affect another user's row, 401 without sanctum.

### Unit

- `FetchDevotionalActionTest` — delegates to query builder, throws `ModelNotFoundException` on miss. (Justification: isolates the 404 path from HTTP.)
- `ToggleDevotionalFavoriteActionTest` — insert branch, delete branch, race-safe under transaction (assert one row in either state).
- `DevotionalQueryBuilderTest` — `onDate`, `withinRange`, `publishedUpTo` (never returns future-dated rows), `newestFirst`.
- `DevotionalResourceTest` / `DevotionalFavoriteResourceTest` — JSON shape, `passage`/`author` omitted when null, embedded devotional.
- Form Request suites — `Rule::enum()` on `type`, date format, `from <= to`, `per_page` max 30.

No duplicate suites: controller tests already cover routing/auth, and the query builder tests cover the filter plumbing — action tests stay narrow.

## Tasks

- [x] 1. Create `App\Domain\Devotional\Enums\DevotionalType` with `Adults` / `Kids` cases.
- [x] 2. Create migration `create_devotionals_table` (columns + indexes per Data & migrations). Reconcile with any existing Symfony table before creating.
- [x] 3. Create migration `create_devotional_favorites_table` with composite-unique `(user_id, devotional_id)`.
- [x] 4. Create `App\Domain\Devotional\Models\Devotional` with casts, `DevotionalType` enum cast, `favorites()` hasMany, and `newEloquentBuilder()` returning `DevotionalQueryBuilder`.
- [x] 5. Create `App\Domain\Devotional\Models\DevotionalFavorite` with `user()` / `devotional()` belongs-to, `newEloquentBuilder()` returning `DevotionalFavoriteQueryBuilder`.
- [x] 6. Create `App\Domain\Devotional\QueryBuilders\DevotionalQueryBuilder` with the methods listed in Key types.
- [x] 7. Create `App\Domain\Devotional\QueryBuilders\DevotionalFavoriteQueryBuilder` with the methods listed in Key types.
- [x] 8. Create `DevotionalFactory` + `DevotionalFavoriteFactory` with the states listed above.
- [x] 9. Create `App\Domain\Devotional\DataTransferObjects\FetchDevotionalData`, `ListDevotionalArchiveData`, `ToggleDevotionalFavoriteData` (readonly, `spatie/laravel-data`).
- [x] 10. Create `App\Domain\Devotional\Actions\FetchDevotionalAction` delegating to the query builder; throws `ModelNotFoundException` on miss.
- [x] 11. Create `App\Domain\Devotional\Actions\ToggleDevotionalFavoriteAction` (transaction-wrapped insert/delete returning `ToggleResult` DTO).
- [x] 12. Create `App\Http\Resources\Devotionals\DevotionalResource` with `whenNotNull` on `passage` / `author`.
- [x] 13. Create `App\Http\Resources\Devotionals\DevotionalFavoriteResource` embedding `DevotionalResource`.
- [x] 14. Create `App\Http\Requests\Devotionals\ShowDevotionalRequest` — validates `language`, `type` (`Rule::enum(DevotionalType::class)`), optional `date` (`YYYY-MM-DD`); builds `FetchDevotionalData`.
- [x] 15. Create `App\Http\Requests\Devotionals\ListDevotionalArchiveRequest` — validates `language`, `type`, optional `from` / `to` with `from <= to`, `per_page` (default engineer's choice ≤ 30, max 30); builds `ListDevotionalArchiveData`.
- [x] 16. Create `App\Http\Requests\Devotionals\ListDevotionalFavoritesRequest` — authenticated-only; validates pagination params.
- [x] 17. Create `App\Http\Requests\Devotionals\ToggleDevotionalFavoriteRequest` — authenticated-only; validates `devotional_id` (`integer`, `exists:devotionals,id`); builds `ToggleDevotionalFavoriteData`.
- [x] 18. Create `App\Http\Controllers\Api\V1\Devotionals\ShowDevotionalController` — calls `FetchDevotionalAction`, returns `DevotionalResource` with `Cache-Control: public, max-age=3600`.
- [x] 19. Create `App\Http\Controllers\Api\V1\Devotionals\ListDevotionalArchiveController` — query-builder-driven pagination.
- [x] 20. Create `App\Http\Controllers\Api\V1\Devotionals\ListDevotionalFavoritesController` — `DevotionalFavorite::query()->forUser($request->user())->withDevotional()->newestFirst()->paginate(...)`.
- [x] 21. Create `App\Http\Controllers\Api\V1\Devotionals\ToggleDevotionalFavoriteController` — calls the action; returns `201` with `DevotionalFavoriteResource` on create, `200 { deleted: true }` on delete.
- [x] 22. Register routes in `routes/api.php` under the `v1` prefix: archive **before** show on the `/devotionals` prefix; favorites group under `/devotional-favorites` with `auth:sanctum`.
- [x] 23. Write `DevotionalQueryBuilderTest` covering `onDate` / `withinRange` / `publishedUpTo` / `newestFirst`.
- [x] 24. Write `FetchDevotionalActionTest` (found + 404 paths).
- [x] 25. Write `ToggleDevotionalFavoriteActionTest` (insert + delete + transaction isolation).
- [x] 26. Write unit tests for `DevotionalResource` and `DevotionalFavoriteResource` (null-field omission, embedded devotional).
- [x] 27. Write Form Request unit tests for the four requests (valid / invalid / enum rejection / date format / `from <= to`).
- [x] 28. Write `ShowDevotionalControllerTest` (today, by date, 404, enum rejection, Cache-Control header).
- [x] 29. Write `ListDevotionalArchiveControllerTest` (pagination, newest-first, `from`/`to` window combinations, max per_page).
- [x] 30. Write `ListDevotionalFavoritesControllerTest` (owner list, cross-user scoping, 401, embedded shape).
- [x] 31. Write `ToggleDevotionalFavoriteControllerTest` (create 201, delete 200, unknown id 422, 401).
- [x] 32. Run `make lint-fix`, `make stan`, `make test --filter=Devotional`, then `make test` before marking ready.

## Risks & notes

- **Shared DB reconciliation.** Symfony names (`devotional`, `devotional_day`, `daily_reading`) may already exist. Follow MBA-005's reconcile pattern — introspect before creating. If the Symfony table is `devotional` (singular), decide now whether to keep the Symfony name or rename to Laravel plural. Recommend keeping `devotionals` (plural) via explicit `$table = 'devotional'` on the model only if the reconcile migration cannot rename safely under cutover.
- **Language fallback is intentionally absent.** AC is explicit; do not quietly fall back to `en`. 404 on a language miss is the contract.
- **Composite index coverage.** The show endpoint is `WHERE language = ? AND type = ? AND date = ?` and must be a single index hit. Archive uses the same prefix plus an `ORDER BY date DESC` — one index on `(language, type, date)` serves both.
- **No route-model binding for `show`.** Query-param-keyed lookup means no `{devotional}` path segment and no scoped resolver — the 404 comes from `FetchDevotionalAction` throwing `ModelNotFoundException` (handled globally).
- **Deferred Extractions register.** This story adds a fifth owner-gated Form Request (favorite toggle is owner-scoped via action, not a Form Request `authorize()` ownership check — so the counter stays at 4). Toggle uses `forUser($user)` scoping in the action, not route-model binding, so the owner-authorize trait extraction tripwire is not yet tripped by this story. Flag at Auditor pass.
- **MBA-009 toggle parity.** The toggle response envelope (`201` / `200 { deleted: true }`) is not yet implemented — MBA-009 has only a story, no plan. This story locks the shape first; MBA-009 must match, not the other way around. Coordinate via Improver if MBA-009 lands with a different shape.
- **Cache header on JSON.** Emitting `Cache-Control: public, max-age=3600` on an endpoint that can also be called with a Sanctum token is intentional (AC 6). Shared caches must not key on the bearer token — this endpoint does not personalise the response, so public caching is correct. Document in code.
