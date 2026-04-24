# Plan: MBA-016-educational-resources

## Approach

Port the Symfony resource library into `App\Domain\EducationalResources\*` as a read-only slice: two Eloquent models (`ResourceCategory`, `EducationalResource`), three invokable controllers, JSON API resources, and a `ResourceType` enum. Localisation follows the existing reading-plans shape — translatable fields as JSON maps, resolved via `LanguageResolver` against the `ResolveRequestLanguage` middleware's attribute. `EducationalResource` is keyed internally by `id` but publicly exposed via `uuid`; UUID route-model binding is wired through `getRouteKeyName()` on the model, and no scoped resolver override is needed (resources have no soft-delete or published() scope per story scope — admin-authoring endpoints are out of scope). Media URLs are absolute-ised in the Resource class via the `media` disk.

## Open questions — resolutions

1. **Media storage disk.** Introduce a project-level config key `educational_resources.media_disk` defaulting to `'public'`. The Symfony app's `uploads/resources/...` paths work against the existing `public` disk out of the box; the prod environment flips the env var to `s3` at cutover (MBA-020 concern, not this story). Resolves disk coupling without hard-coding `s3`.
2. **UUID already set in DB?** Assume yes — Symfony schema provides `resource.uuid` (story AC 7 locks this). If MBA-020 cutover discovers a missing/nullable column, a reconciliation migration is added *in this story* (Task 3 below owns the conditional reconciliation path, mirroring MBA-005).
3. **Language on resource.** Per-resource translatable JSON columns (`title`, `summary`, `content`) matching the reading-plans precedent. Category inheritance is rejected: the Symfony `resource` row carries its own localised copy in practice, and JSON maps degrade to `en` fallback via `LanguageResolver` when an entry is missing. Category's `language` column in the ACs is treated as the category's *declared* primary locale string, not a filter key.

## Domain layout

```
app/Domain/EducationalResources/
├── Enums/
│   └── ResourceType.php                  # article | video | pdf | audio
├── Models/
│   ├── ResourceCategory.php              # int id PK, translatable fields, hasMany resources
│   └── EducationalResource.php           # int id PK + uuid (public key), belongsTo category
├── QueryBuilders/
│   ├── ResourceCategoryQueryBuilder.php  # withResourceCount(), forLanguage()
│   └── EducationalResourceQueryBuilder.php # ofType(), latestPublished()
└── Support/
    └── MediaUrlResolver.php              # absolute URL from a stored path via the configured disk

app/Http/
├── Controllers/Api/V1/EducationalResources/
│   ├── ListResourceCategoriesController.php
│   ├── ListResourcesByCategoryController.php
│   └── ShowEducationalResourceController.php
├── Requests/EducationalResources/
│   ├── ListResourceCategoriesRequest.php
│   ├── ListResourcesByCategoryRequest.php
│   └── ShowEducationalResourceRequest.php
└── Resources/EducationalResources/
    ├── ResourceCategoryResource.php      # id, name, description?, language, resource_count
    ├── EducationalResourceListResource.php # uuid, type, title, summary?, thumbnail_url?, published_at
    └── EducationalResourceDetailResource.php # full detail incl. content, media_url, author, category

config/educational_resources.php          # media_disk key
```

No Actions. All three endpoints are trivial reads — controllers compose a QueryBuilder call and return a Resource / paginated Resource collection. An Action would be a pass-through and fails the "helper needs a named consumer" test. If an admin-authoring story introduces writes later, that story owns the Action.

## Key types

| Type | Role |
|---|---|
| `ResourceType` (string-backed enum) | Cases `Article`, `Video`, `Pdf`, `Audio` with values `article` / `video` / `pdf` / `audio`. Used by `EducationalResource::casts()` and `Rule::enum(ResourceType::class)` in the type-filter validator. |
| `ResourceCategory` | `int $id`, `array $name`, `array $description`, `string $language` (iso2), timestamps. Relation `resources(): HasMany<EducationalResource>`. `newEloquentBuilder()` returns `ResourceCategoryQueryBuilder`. |
| `EducationalResource` | `int $id` (PK, FK target), `string $uuid` (public identity, unique), `ResourceType $type`, `array $title`, `array $summary`, `array $content`, `?string $thumbnail_path`, `?string $media_path`, `?string $author`, `Carbon $published_at`, `int $resource_category_id`. Relation `category(): BelongsTo<ResourceCategory>`. `getRouteKeyName(): 'uuid'`. No `resolveRouteBinding()` override — default lookup by `uuid` is sufficient (no scope filter needed since there is no `published()` / soft-delete concern in scope). `newEloquentBuilder()` returns `EducationalResourceQueryBuilder`. |
| `ResourceCategoryQueryBuilder` | `withResourceCount(): self` — eager `withCount('resources')` aliased to `resource_count`. `forLanguage(Language): self` — filters on the `language` column (skipped when the query param is absent, per AC 1's `?language={iso2}` parameter). |
| `EducationalResourceQueryBuilder` | `ofType(ResourceType): self` — `where('type', $type->value)`. `latestPublished(): self` — `orderByDesc('published_at')`. No `published()` scope (story scope is read-only, no draft state in the Symfony source for resources). |
| `MediaUrlResolver` | `static absoluteUrl(?string $path, string $disk): ?string` — returns `null` when `$path` is null/empty, otherwise `Storage::disk($disk)->url($path)`. Single caller: `EducationalResourceDetailResource` for `thumbnail_url` + `media_url`; list resource for `thumbnail_url` only. |

## HTTP endpoints

| Method | Path | Controller | Request | Resource | Middleware |
|---|---|---|---|---|---|
| GET | `/api/v1/resource-categories` | `ListResourceCategoriesController` | `ListResourceCategoriesRequest` | `ResourceCategoryResource` (collection) | `api-key-or-sanctum`, `resolve-language` |
| GET | `/api/v1/resource-categories/{category}/resources` | `ListResourcesByCategoryController` | `ListResourcesByCategoryRequest` | `EducationalResourceListResource` (collection) | `api-key-or-sanctum`, `resolve-language` |
| GET | `/api/v1/resources/{resource:uuid}` | `ShowEducationalResourceController` | `ShowEducationalResourceRequest` | `EducationalResourceDetailResource` | `api-key-or-sanctum`, `resolve-language` |

Route-model binding notes:
- `{category}` binds `ResourceCategory` by default `id`; category is not scoped, so no custom `resolveRouteBinding()` override.
- `{resource:uuid}` uses `EducationalResource::getRouteKeyName()` returning `'uuid'`. Resolution strategy: **default Eloquent lookup by the model's route key** — no scope filter is needed because there is no draft/published split or soft-delete in scope, so Laravel's built-in `resolveRouteBinding` finds the row by `uuid` and a missing row raises `ModelNotFoundException` → 404 via the existing `bootstrap/app.php` renderer.

### Caching

`Cache-Control: public, max-age=3600` is set in `ListResourceCategoriesController` via `response()->header(...)` (AC 3). The controller wraps the `ResourceCategoryResource::collection(...)->response()` call, adds the header, and returns the Response. No middleware abstraction yet — one call site.

### Pagination

| Endpoint | Per-page default | Per-page max |
|---|---|---|
| categories | 50 | 100 |
| resources by category | 25 | 100 |

Per-page values live as `public const DEFAULT_PER_PAGE` / `MAX_PER_PAGE` on each Form Request (mirrors `ListReadingPlansRequest`). Both requests expose a `perPage(): int` method the controller calls.

### Request details

- `ListResourceCategoriesRequest` — `language: nullable|string|size:2`, `per_page: nullable|integer|min:1|max:100`. `authorize(): true`.
- `ListResourcesByCategoryRequest` — `type: nullable|string` + `Rule::enum(ResourceType::class)`, `per_page: nullable|integer|min:1|max:100`. `authorize(): true`. Exposes `resourceType(): ?ResourceType` (null-safe `tryFrom` on the validated value).
- `ShowEducationalResourceRequest` — no input rules; exists for sibling parity and to stamp `authorize(): true`.

### Response shapes

- `ResourceCategoryResource`: `{ id, name, description, language, resource_count }`. `name`/`description` resolved via `LanguageResolver::resolve($this->name, $language)`. `resource_count` read from `$this->resource_count` (populated by the `withResourceCount()` QueryBuilder helper).
- `EducationalResourceListResource`: `{ uuid, type, title, summary, thumbnail_url, published_at }`. `type` is `$this->type->value` (enum string). `thumbnail_url` via `MediaUrlResolver`. `published_at` as ISO-8601.
- `EducationalResourceDetailResource`: superset of list resource plus `content`, `media_url`, `author`, `category: { id, name }`. `category` is a nested mini-resource built inline (it's two fields — extracting is premature).

## Data & migrations

### `resource_categories` table (target shape)

| Column | Type | Null | Notes |
|---|---|---|---|
| id | unsigned int auto-increment PK | no | |
| name | json | no | `{en,ro,hu}` map |
| description | json | yes | `{en,ro,hu}` map |
| language | string(3) | no | primary declared locale, iso2 (`en`/`ro`/`hu`) |
| created_at, updated_at | datetime | yes | |

### `educational_resources` table (target shape)

| Column | Type | Null | Notes |
|---|---|---|---|
| id | unsigned int auto-increment PK | no | |
| uuid | char(36) unique | no | public identity, covered by unique index |
| resource_category_id | unsigned int FK → `resource_categories.id` | no | cascade on delete (admin concern) |
| type | string(16) | no | enum value; index on `(resource_category_id, type, published_at)` |
| title | json | no | |
| summary | json | yes | |
| content | json | no | |
| thumbnail_path | string(255) | yes | storage-relative; absolute-ised in resource |
| media_path | string(255) | yes | storage-relative; absolute-ised in resource |
| author | string(255) | yes | |
| published_at | datetime | no | sort key |
| created_at, updated_at | datetime | yes | |

### Migration strategy (mirrors MBA-005)

Two migrations, both idempotent:

1. **Create migration (CI/dev path):** early-return when `Schema::hasTable('resource')` is true (prod Symfony table exists); otherwise `Schema::create('resource_categories')` + `Schema::create('educational_resources')` per the schemas above.
2. **Reconciliation migration (prod path):** early-return when `Schema::hasTable('resource')` is false. Otherwise rename Symfony's `resource_category` → `resource_categories`, `resource` → `educational_resources`, align column names (snake_case), add any missing columns (`updated_at` if absent, `uuid` with backfill if absent, translatable JSON shape conversion is deferred to Task 3's comment — if Symfony stored single-string localised text, a one-shot data migration wraps the value under the current `language` key), and add the composite index on `(resource_category_id, type, published_at)`.

Both paths converge on the same target schema. The reconciliation migration is only exercised in prod at MBA-020 cutover.

## Tasks

- [x] 1. Create `App\Domain\EducationalResources\Enums\ResourceType` (string-backed, cases `Article=article`, `Video=video`, `Pdf=pdf`, `Audio=audio`).
- [x] 2. Add migration `create_resource_categories_and_educational_resources_table.php` per the target schemas above, guarded by `! Schema::hasTable('resource')` so prod short-circuits.
- [x] 3. Add migration `reconcile_symfony_resource_tables.php` that early-returns when Symfony tables are absent; otherwise renames + reconciles columns, backfills `uuid` per row, and adds the `(resource_category_id, type, published_at)` index. Down-path reverses the renames; no attempt to restore dropped Symfony-only columns.
- [x] 4. Add `config/educational_resources.php` with `'media_disk' => env('EDUCATIONAL_RESOURCES_DISK', 'public')`.
- [x] 5. Create `App\Domain\EducationalResources\Models\ResourceCategory` with translatable JSON casts, `resources()` `HasMany` relation, and `newEloquentBuilder()` wiring.
- [x] 6. Create `App\Domain\EducationalResources\Models\EducationalResource` with `uuid` as route key (`getRouteKeyName()`), `type` cast to `ResourceType`, translatable JSON casts, `category()` `BelongsTo`, and `newEloquentBuilder()` wiring.
- [x] 7. Create `App\Domain\EducationalResources\QueryBuilders\ResourceCategoryQueryBuilder` with `withResourceCount()` and `forLanguage(Language)`.
- [x] 8. Create `App\Domain\EducationalResources\QueryBuilders\EducationalResourceQueryBuilder` with `ofType(ResourceType)` and `latestPublished()`.
- [x] 9. Create `App\Domain\EducationalResources\Support\MediaUrlResolver::absoluteUrl()`.
- [x] 10. Create `ResourceCategoryFactory` with localised name/description faker arrays and a random `language` among `en|ro|hu`.
- [x] 11. Create `EducationalResourceFactory` with a stable `uuid()` call, `type` picked from `ResourceType` cases, localised title/summary/content, `published_at` in the past, nullable media columns, and a `forCategory(ResourceCategory)` state helper.
- [x] 12. Create `App\Http\Requests\EducationalResources\ListResourceCategoriesRequest` with `DEFAULT_PER_PAGE=50`, `MAX_PER_PAGE=100`, `language` + `per_page` validation, and `perPage()` accessor.
- [x] 13. Create `App\Http\Requests\EducationalResources\ListResourcesByCategoryRequest` with `DEFAULT_PER_PAGE=25`, `MAX_PER_PAGE=100`, `type` validated via `Rule::enum(ResourceType::class)`, `perPage()` accessor, and `resourceType(): ?ResourceType` accessor.
- [x] 14. Create `App\Http\Requests\EducationalResources\ShowEducationalResourceRequest` (empty rules, `authorize(): true`) for sibling parity.
- [x] 15. Create `App\Http\Resources\EducationalResources\ResourceCategoryResource` emitting `{ id, name, description, language, resource_count }`, using `LanguageResolver` for translatable fields and reading `resource_count` from the aggregate.
- [x] 16. Create `App\Http\Resources\EducationalResources\EducationalResourceListResource` emitting `{ uuid, type, title, summary, thumbnail_url, published_at }` via `LanguageResolver` and `MediaUrlResolver`.
- [x] 17. Create `App\Http\Resources\EducationalResources\EducationalResourceDetailResource` emitting the full detail shape incl. nested category mini-object; uses `LanguageResolver` and `MediaUrlResolver`.
- [x] 18. Create `App\Http\Controllers\Api\V1\EducationalResources\ListResourceCategoriesController` (invokable): paginates `ResourceCategory::query()->withResourceCount()` filtered by `forLanguage()` when the request provides it, and attaches `Cache-Control: public, max-age=3600` to the response.
- [x] 19. Create `App\Http\Controllers\Api\V1\EducationalResources\ListResourcesByCategoryController` (invokable): paginates `$category->resources()->latestPublished()` scoped via `->ofType($request->resourceType())` when present.
- [x] 20. Create `App\Http\Controllers\Api\V1\EducationalResources\ShowEducationalResourceController` (invokable): eager-loads `category` and returns the detail resource.
- [x] 21. Register the three routes inside `routes/api.php` under a new `Route::prefix('v1')` group entry with `['api-key-or-sanctum', 'resolve-language']` middleware; name them `resource-categories.index`, `resource-categories.resources.index`, `resources.show`.
- [x] 22. Add feature test `tests/Feature/Api/V1/EducationalResources/ListResourceCategoriesTest` covering the happy path, language filter, `resource_count` accuracy, pagination default 50, `Cache-Control` header, and unauthenticated (no api-key, no sanctum) = 401.
- [x] 23. Add feature test `tests/Feature/Api/V1/EducationalResources/ListResourcesByCategoryTest` covering happy path, `type` filter for each enum case, invalid `type` = 422, newest-first ordering, pagination default 25, and unknown category id = 404.
- [x] 24. Add feature test `tests/Feature/Api/V1/EducationalResources/ShowEducationalResourceTest` covering happy path (JSON structure, media URL absolute-isation with `Storage::fake`), unknown uuid = 404, category nested object present.
- [x] 25. Add unit test `tests/Unit/Domain/EducationalResources/QueryBuilders/ResourceCategoryQueryBuilderTest` — `withResourceCount()` populates the aggregate; `forLanguage()` filters.
- [x] 26. Add unit test `tests/Unit/Domain/EducationalResources/QueryBuilders/EducationalResourceQueryBuilderTest` — `ofType()` filters by type value; `latestPublished()` orders correctly.
- [x] 27. Add unit test `tests/Unit/Domain/EducationalResources/Support/MediaUrlResolverTest` — null/empty path returns null; valid path returns the disk's absolute URL; disk name is honoured.
- [x] 28. Run `make lint-fix && make stan && make test --filter=EducationalResources`, then `make test` before marking the story ready for review.

## Risks & notes

- **Symfony schema access is unavailable from this planning environment.** Column names, nullability, and translation shape for `resource` / `resource_category` are modelled on the reading-plans precedent (`JSON` maps + `LanguageResolver` fallback) and the story's ACs, not on a verified schema dump. Task 3 (reconciliation migration) must cross-check column names during implementation and adjust renames; shape-conversion of non-JSON localised columns (if Symfony stored raw strings) must happen inside that migration via a one-shot data block. Flag any divergence in the Engineer's report.
- **UUID backfill risk.** If prod resources lack UUIDs, Task 3's backfill runs `Str::uuid()` per row in PHP. Performance on a large table is acceptable because this runs once during MBA-020 cutover. Not acceptable as a live-traffic migration — callers depending on `uuid` before cutover must not exist (enforced by the fact that this is a new Laravel-side feature).
- **No `Action` class.** Per the "helper must have a named consumer" rule, no Action is defined because all three endpoints are pass-through reads. If an admin authoring story later adds writes, that story introduces the first Action (`CreateEducationalResourceAction` / etc.) and keeps the sibling-name parity with its controller/request.
- **`Cache-Control` only on category listing.** ACs specify the header for categories (AC 3) but not for resource listings or detail. Leave the two resource endpoints uncached at the HTTP layer; revisit when analytics justify it.
- **Media disk not wired yet.** `config/educational_resources.php` is introduced in Task 4 with a `'public'` default. Prod (`s3`) wiring is an MBA-020 env-var concern; document in the migration's header comment.
- **Deferred Extractions register.** No register entry triggered. `Cache-Control` copy-count is 1 (new) and pagination-constant pattern copy-count is 2 (`ListReadingPlansRequest`, `ListResourceCategoriesRequest`) — under threshold. No new tripwire row added.
