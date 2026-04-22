# Plan: MBA-014-bible-collections

## Approach

Port the Symfony `collection_topic` / `collection_reference` tables behind two read-only endpoints under a new `App\Domain\Collections` domain. Stored raw reference strings are parsed on read via `App\Domain\Reference\Parser\ReferenceParser` (MBA-006) inside a dedicated `ResolveCollectionReferencesAction` that owns the per-row try/catch so one malformed row cannot 500 the whole topic. Language filtering and fallback follow the existing `ResolveRequestLanguage` + `Language` enum pattern already used by Reading Plans.

## Open questions — resolutions

1. **Language fallback.** Match MBA-008 — no fallback. If the topic's language does not match the resolved language (explicit or default), return 404. Eliminates a "why did RO return EN content" class of bug.
2. **Description format.** Plain text. Resource serializes `description` unmodified; clients render as text. Any HTML-like content is passed through verbatim — we do not sanitize because admin is trusted, and we do not convert linebreaks because the Symfony payload already carried the original string.
3. **Paginating references within a topic.** No pagination. Topics are bounded (Symfony historical max <50). Embed the full list under `data.references`.
4. **`ReferenceCollection` VO extraction.** Not now. The endpoint response flattens each stored row to `{ raw, parsed, display_text, parse_error }` independently; a VO wrapping `array<Reference>` gains nothing the array does not already provide. Re-evaluate if MBA-010 (favorites) or a future admin-write story needs aggregate behaviour (dedupe across rows, range merging across rows).

## Domain layout

```
app/Domain/Collections/
├── Models/
│   ├── CollectionTopic.php
│   └── CollectionReference.php
├── Actions/
│   └── ResolveCollectionReferencesAction.php
├── DataTransferObjects/
│   └── ResolvedCollectionReference.php
└── QueryBuilders/
    └── CollectionTopicQueryBuilder.php

app/Http/Controllers/Api/V1/Collections/
├── ListCollectionTopicsController.php
└── ShowCollectionTopicController.php

app/Http/Requests/Collections/
├── ListCollectionTopicsRequest.php
└── ShowCollectionTopicRequest.php

app/Http/Resources/Collections/
├── CollectionTopicResource.php              # list shape (summary)
├── CollectionTopicDetailResource.php        # show shape (with references)
└── ResolvedCollectionReferenceResource.php
```

## Key types

| Type | Role |
|---|---|
| `CollectionTopic` (Eloquent) | Columns: `id`, `language` (iso2), `name`, `description` (nullable text), `position` (int sort), `created_at`, `updated_at`. Relation `references(): HasMany<CollectionReference>` ordered by `position`. Custom `resolveRouteBinding()` filters by the request-resolved `Language` (strategy: model-owned scoped binding — mirrors `ReadingPlan::resolveRouteBinding()` which already filters by `published()`). `newEloquentBuilder()` returns `CollectionTopicQueryBuilder`. |
| `CollectionReference` (Eloquent) | Columns: `id`, `collection_topic_id` (FK, cascade), `reference` (stored raw string, e.g. `GEN.1:1.VDC`), `position` (int sort), `created_at`, `updated_at`. `$timestamps = true`. No route binding. |
| `CollectionTopicQueryBuilder` | `forLanguage(Language $language)` — filter by `language` column. `withReferenceCount()` — `withCount('references as reference_count')` for the list resource. `ordered()` — `orderBy('position')`. |
| `ResolveCollectionReferencesAction` | Takes `iterable<CollectionReference>` + `Language`. Returns `array<ResolvedCollectionReference>`. For each row calls `ReferenceParser::parse()`; on `InvalidReferenceException`, captures the `reason()` into the DTO's `parseError`, logs `warning` with topic id + reference id + raw string + reason (channel: default). Uses `ReferenceFormatter::toHumanReadable()` per parsed `Reference` for `displayText`. |
| `ResolvedCollectionReference` (readonly DTO) | Fields: `string $raw`, `?array $parsed` (`array<int, array{book:string, chapter:int, verses:array<int,int>, version:?string}>` — serialized Reference VOs suitable for JSON), `?string $displayText`, `?string $parseError`. Produced only by the Action; Resource reads it. |
| `ListCollectionTopicsRequest` | `authorize` returns true. Rule: `language` nullable iso2 string. `perPage()` helper matching `ListReadingPlansRequest` (default 15, max 100). |
| `ShowCollectionTopicRequest` | `authorize` returns true. No rules beyond the route binding (topic already resolved by middleware-language scope). |
| `CollectionTopicResource` | Summary list shape: `id`, `name`, `description`, `language`, `reference_count` (from `withReferenceCount`). |
| `CollectionTopicDetailResource` | Detail shape: `id`, `name`, `description`, `language`, `references: ResolvedCollectionReferenceResource::collection(...)`. |
| `ResolvedCollectionReferenceResource` | `raw`, `parsed`, `display_text`, `parse_error` (all keys present; `null` on degraded rows). |

## HTTP endpoints

| Method | Path | Controller | Request | Resource | Middleware |
|---|---|---|---|---|---|
| GET | `/api/v1/collections` | `ListCollectionTopicsController` | `ListCollectionTopicsRequest` | `CollectionTopicResource::collection(...)` | `api-key-or-sanctum`, `resolve-language` |
| GET | `/api/v1/collections/{topic}` | `ShowCollectionTopicController` | `ShowCollectionTopicRequest` | `CollectionTopicDetailResource` | `api-key-or-sanctum`, `resolve-language` |

Route-model binding for `{topic}`: default id binding. Scope strategy: **model-owned via `CollectionTopic::resolveRouteBinding()`** that applies `forLanguage($resolvedLanguage)` before the lookup — language is read from `$request->attributes` (or the container's current request) exactly as `ReadingPlan::resolveRouteBinding()` reads its published scope. Returns `null` on mismatch so Laravel 404s before the controller runs.

Cache headers: both endpoints add `Cache-Control: public, max-age=3600`. Set on the controller return via a small `withCacheHeaders()` helper local to the controller or, since two endpoints share this, an existing pattern — inline `->response()->header(...)` in each controller for now (only two call sites). No ETag this story (data volume and churn low; defer until we hit ETag on MBA-007's versions export).

## Data & migrations

One migration creates both tables.

`collection_topics`:
- `id` bigIncrements
- `language` string(2), indexed
- `name` string
- `description` text nullable
- `position` integer default 0, indexed
- `timestamps`
- composite index `(language, position)`

`collection_references`:
- `id` bigIncrements
- `collection_topic_id` foreignId references `collection_topics(id)` onDelete cascade
- `reference` string (raw canonical form, e.g. `GEN.1:1-3.VDC`)
- `position` integer default 0
- `timestamps`
- composite index `(collection_topic_id, position)`

Table names are the Laravel-plural rename of Symfony's `collection_topic` / `collection_reference` (consistent with MBA-005 which renamed `user` → `users`). Engineer confirms Symfony source column names + types before finalising via the Symfony schema reference the story cites; any deviation surfaces as a follow-up note on the PR.

Factories required (for tests): `CollectionTopicFactory`, `CollectionReferenceFactory` with states `withValidReferences()`, `withMalformedReference()`. Seeder not required this story.

## Tasks

- [x] 1. Create the migration for `collection_topics` + `collection_references` with the columns and indexes listed under Data & migrations.
- [x] 2. Create `App\Domain\Collections\Models\CollectionTopic` with fillable/casts, `references()` HasMany, `newEloquentBuilder()`, and `resolveRouteBinding()` that filters by the request-resolved `Language`.
- [x] 3. Create `App\Domain\Collections\Models\CollectionReference` with `collectionTopic()` BelongsTo and no route binding.
- [x] 4. Create `App\Domain\Collections\QueryBuilders\CollectionTopicQueryBuilder` with `forLanguage()`, `withReferenceCount()`, `ordered()`.
- [x] 5. Create `App\Domain\Collections\DataTransferObjects\ResolvedCollectionReference` as a readonly class with the four fields listed in Key types.
- [x] 6. Create `App\Domain\Collections\Actions\ResolveCollectionReferencesAction` wrapping `ReferenceParser::parse()` + `ReferenceFormatter::toHumanReadable()` with per-row try/catch on `InvalidReferenceException`; log a warning on degraded rows.
- [x] 7. Create factories `CollectionTopicFactory` and `CollectionReferenceFactory`, plus states `withValidReferences()` and `withMalformedReference()` on the reference factory.
- [x] 8. Create `ListCollectionTopicsRequest` (language nullable, per_page with same bounds as `ListReadingPlansRequest`).
- [x] 9. Create `ShowCollectionTopicRequest` (authorize true, no body rules).
- [x] 10. Create `CollectionTopicResource` rendering the list summary shape.
- [x] 11. Create `ResolvedCollectionReferenceResource` rendering `raw`, `parsed`, `display_text`, `parse_error` (all keys always present).
- [x] 12. Create `CollectionTopicDetailResource` embedding `ResolvedCollectionReferenceResource::collection(...)` built from the Action's output, not from the raw `references` relation.
- [x] 13. Create `ListCollectionTopicsController` calling the QueryBuilder with `forLanguage` + `withReferenceCount` + `ordered` and paginating.
- [x] 14. Create `ShowCollectionTopicController` loading `references` (ordered), invoking `ResolveCollectionReferencesAction`, and handing the result to `CollectionTopicDetailResource`.
- [x] 15. Add the two routes under the `v1` prefix group with `api-key-or-sanctum` + `resolve-language` middleware and names `collections.index` / `collections.show`.
- [x] 16. Add `Cache-Control: public, max-age=3600` on both responses.
- [ ] 17. Write feature test `ListCollectionTopicsTest` covering: default-language result, explicit `?language=ro` filter, api-key auth path, `reference_count` present, pagination shape.
- [ ] 18. Write feature test `ShowCollectionTopicTest` covering: happy path with all references parsed, a topic containing one malformed reference (asserts `parse_error` non-null and sibling refs still parsed), 404 when topic id does not belong to the resolved language, 404 when id does not exist.
- [ ] 19. Write unit test `ResolveCollectionReferencesActionTest` covering: all-valid input, mixed valid + malformed (`InvalidReferenceException` branch recovered into DTO), parsed DTO shape round-trips through the Resource, log warning fired on degraded rows.
- [ ] 20. Run `make lint-fix`, `make stan`, `make test --filter=Collection`, then full `make test` before handing off for review.

## Risks & notes

- **No Symfony DB introspection available this session.** The migration column list is derived from the story and from Symfony convention (`collection_topic` / `collection_reference`); Engineer must cross-check against the Symfony schema dump before shipping and flag any divergence (e.g. if Symfony uses a `slug` column, a `visible` flag, or a `user_id` — none of which the story lists).
- **Stored reference strings may lack a version suffix.** MBA-006's `ReferenceFormatter::toCanonical()` throws when `$ref->version === null`, but `toHumanReadable()` does not need one. Relying on `ReferenceParser::parse()` + `toHumanReadable()` (not `toCanonical()`) keeps the endpoint resilient to `GEN.1:1` style stored values. If admin data is inconsistent across rows, graceful degradation still holds — those rows fail `parse()` and are surfaced via `parse_error`.
- **`ReferenceCollection` VO deliberately deferred.** Re-evaluate at MBA-010 (favorites) where multiple refs per user row may need dedupe/merge semantics that a flat array cannot express.
- **Language middleware dependency.** The `resolve-language` middleware must run before route-model binding, otherwise `CollectionTopic::resolveRouteBinding()` reads `Language::En` fallback. Existing `ReadingPlan` routes already rely on this ordering; confirm the route group registers middleware in the correct order.
- **No admin write endpoints.** Out of scope per story. Factories produce the test data; real admin data arrives via the shared MySQL instance during cutover.
- **ETag deferred.** List + show are cache-friendly but we do not emit ETags this story. Revisit if traffic on these endpoints warrants it.
