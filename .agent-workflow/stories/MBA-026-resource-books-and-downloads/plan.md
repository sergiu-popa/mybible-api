# Plan: MBA-026-resource-books-and-downloads

## Approach

Build a `ResourceBook` + `ResourceBookChapter` surface inside the existing
`App\Domain\EducationalResources` namespace (alongside `EducationalResource`)
and bootstrap a new `App\Domain\Analytics` namespace whose first inhabitant
is the polymorphic `ResourceDownload`. Three evolution migrations adapt the
Symfony tables already renamed by MBA-023 (`resource_books`,
`resource_book_chapters`, `resource_downloads`) into the AC §1/§3/§6 target
shape — backfilling `slug`, `downloadable_type`, and dropping `ip_address`
in-place. The HTTP layer reuses every existing precedent (slug-bound public
binding gated to `published()` like MBA-024 commentaries; admin id-binding
under `super-admin`; tag-invalidating `CachedRead` like MBA-016 educational
resources; named rate limiter registered in `AppServiceProvider` like
`per-user`/`public-anon`). Download endpoints dual-write a polymorphic row
into `resource_downloads` AND dispatch a Laravel event
`DownloadOccurred` whose listener lands in MBA-030 — when MBA-026 ships
first the dispatch is a no-op, when MBA-030 ships first the events flow
immediately, no coordination required.

## Open questions — resolutions

1. **MorphMap registration.** Polymorphic `downloadable_type` values
   (`educational_resource`, `resource_book`, `resource_book_chapter`) must
   resolve to model classes deterministically — never to FQCNs which would
   break under namespace renames. Resolution: register
   `Relation::enforceMorphMap([...])` in `AppServiceProvider::boot()` once,
   then use string keys throughout. This is the project's first morphMap;
   the constants live in `App\Domain\Analytics\Models\ResourceDownload`
   public constants `TYPE_EDUCATIONAL_RESOURCE`, `TYPE_RESOURCE_BOOK`,
   `TYPE_RESOURCE_BOOK_CHAPTER` so writers don't sprinkle string literals.
2. **Rate-limit shape.** AC §15 says "60/min per IP+device_id". A named
   `RateLimiter::for('downloads', …)` keyed by
   `sprintf('%s|%s', $request->ip(), $deviceId ?? 'no-device')` covers
   both the with-device-id and without-device-id paths cleanly. Registered
   in `AppServiceProvider::boot()` next to `public-anon`/`per-user`. Routes
   apply via `throttle:downloads`.
3. **`device_id` ingress.** From request header `X-Device-Id` (matches the
   MBA-030 convention from AC's tech notes). A `ClientContextResolver`
   helper in `App\Domain\Analytics\Support\` extracts `device_id`,
   `source` (User-Agent → `ios|android|web|null`), and `language` (from
   `ResolveRequestLanguage::ATTRIBUTE_KEY`). One callsite means one helper,
   not three duplicated controller blocks. Source inference is bare
   substring matching (`MyBibleMobile` UA segment + platform suffix) —
   matching MBA-030's tech-notes contract; a missing/unrecognised UA stays
   `NULL`.
4. **Public route binding strategy.** `ResourceBook::resolveRouteBinding(
   $value, $field)` returns `static::published()->where('slug',
   $value)->firstOrFail()` only when `$field === 'slug'`; default
   `{book}` (id-bound) admin routes skip the published filter. Same
   precedent as MBA-024 `Commentary::resolveRouteBinding` and applies to
   the public `{book:slug}` chapter download endpoint, which must NOT
   record a download for a draft. Soft-deletes are honoured globally by
   the model trait so no extra logic on the resolver.
5. **Slug uniqueness scope.** AC §1 specifies `slug VARCHAR(255) UNIQUE`
   (global). Backfill from `Str::slug(strtolower($name))` with a numeric
   suffix on collision (`-2`, `-3`, …). Same pattern as MBA-024 commentary
   slug backfill.
6. **`resource_downloads` shape evolution.** This story's migration owns
   the column-level evolution AND the deterministic backfill of
   `downloadable_type='educational_resource'` for existing legacy rows
   (the only possible value pre-this-story), so the column ships NOT NULL
   from day one. MBA-031 then verifies + drops `ip_address` is gone and
   re-asserts indexes; if MBA-031 ships before this story, that ETL block
   becomes a no-op. AC §26's migration test pins the post-migration shape
   so a regression on either side is caught.
7. **Reorder body shape — books vs chapters.** AC §20 says reorder books
   "within a language" — the language scope must be on the body, so a
   dedicated `ReorderResourceBooksRequest` accepts `{language, ids[]}`
   and the action asserts every id has that language. AC §21 reorders
   chapters within a book — the book is in the URL path, so the standard
   `App\Http\Requests\Admin\ReorderRequest` (`{ids}`) suffices and the
   action asserts every id belongs to the parent book.
8. **Public auth on download endpoints (AC §18).** `auth:api` is NOT
   required, but we still need user_id capture when a token is present.
   Solution: gate the three download routes with `api-key-or-sanctum`
   (current public-anon precedent — accepts an API key for anonymous and
   captures the Sanctum user when a Bearer token is present). The route
   group also gets `resolve-language` and `throttle:downloads`. The AC's
   "anonymous OK" guarantee comes from the existing api-key-or-sanctum
   middleware.
9. **`is_published` admin flow.** Two dedicated `publish` /
   `unpublish` endpoints (AC §20) match MBA-024 commentary precedent —
   keep the publication switch out of generic PATCH so audit logs are
   clear and `published_at` is set deterministically by the action
   (`now()` on publish; left intact on unpublish so re-publish reuses
   the original date if desired — operator can override via PATCH).
10. **Two `Resource` classes per shape.** `ResourceBookListResource`
    (slug, name, language, description, cover_image_url, author,
    published_at, chapter_count) vs `ResourceBookDetailResource` (list
    shape + nested `chapters[]` of {id, position, title, has_audio,
    duration_seconds}). Splitting matches the EducationalResources
    list/detail precedent and keeps each shape statically inspectable.
    Admin reuses the same public shape and adds an `AdminResourceBookResource`
    that prepends `id`, `is_published`, `position`. Same split for chapter:
    `ResourceBookChapterResource` (full content/audio fields) and
    `AdminResourceBookChapterResource` (prepends `id`, `position`).
11. **Downloads summary shape (AC §22).** Returns
    `{ data: [{ date, downloadable_type, downloadable_id, language,
    count, unique_devices }], meta: { from, to, group_by } }`. When the
    requested range exceeds 7 days the action joins to MBA-030's
    `analytics_daily_rollups` (filtered to download event types); for
    shorter / current-day windows it groups raw `resource_downloads`
    directly. The MBA-030-side rollup table doesn't exist yet — the
    action's "rollup branch" is a TODO that throws `BadRequestHttpException`
    with `"long-range download summary requires MBA-030 rollups"` until
    MBA-030 ships. Documented in Risks below.
12. **Cache layer.** Public list/detail use both `cache.headers` middleware
    (AC TTLs: 3600/3600/600s respectively per §12-§14) AND a Redis-backed
    `CachedRead` in the action (mirroring MBA-016's
    `ListResourcesByCategoryAction`) with tag invalidation tied to
    `resource_books`. Admin writes flush the tag via `CachedRead::flush`.
    Chapter detail goes through `CachedRead` only when `is_published` —
    admin previewing a draft chapter skips the cache because the public
    `{book:slug}` route 404s on drafts anyway.

## Domain layout

```
app/Domain/EducationalResources/
├── Models/
│   ├── ResourceBook.php                                 # NEW — soft-deletes, slug routekey, published-on-slug routebinding
│   └── ResourceBookChapter.php                          # NEW
├── QueryBuilders/
│   ├── ResourceBookQueryBuilder.php                     # NEW — published(), forLanguage(Language), orderedForList()
│   └── ResourceBookChapterQueryBuilder.php              # NEW — ordered()
├── Actions/
│   ├── ListResourceBooksAction.php                      # NEW — cached + tag-invalidating
│   ├── ShowResourceBookAction.php                       # NEW — cached
│   ├── ShowResourceBookChapterAction.php                # NEW — cached
│   ├── CreateResourceBookAction.php                     # NEW
│   ├── UpdateResourceBookAction.php                     # NEW
│   ├── DeleteResourceBookAction.php                     # NEW — soft-delete, schedules cover_image cleanup
│   ├── SetResourceBookPublicationAction.php             # NEW — handle(ResourceBook, bool $published): void
│   ├── ReorderResourceBooksAction.php                   # NEW — handle(Language, list<int> $ids): void
│   ├── CreateResourceBookChapterAction.php              # NEW
│   ├── UpdateResourceBookChapterAction.php              # NEW
│   ├── DeleteResourceBookChapterAction.php              # NEW
│   └── ReorderResourceBookChaptersAction.php            # NEW — handle(ResourceBook, list<int> $ids): void
├── DataTransferObjects/
│   ├── ResourceBookData.php                             # NEW — slug/name/language/description/cover_image_url/author
│   └── ResourceBookChapterData.php                      # NEW — title/content/audio_cdn_url/audio_embed/duration_seconds
└── Support/
    └── ResourceBooksCacheKeys.php                       # NEW — TAG_ROOT='resource_books'; key builders for list/detail/chapter

app/Domain/Analytics/                                    # NEW DOMAIN
├── Models/
│   └── ResourceDownload.php                             # NEW — polymorphic morphTo; public TYPE_* constants
├── QueryBuilders/
│   └── ResourceDownloadQueryBuilder.php                 # NEW — for(Model), countsByDay(Carbon $from, Carbon $to)
├── Actions/
│   ├── RecordResourceDownloadAction.php                 # NEW — handle(Model $target, ResourceDownloadContextData): ResourceDownload
│   └── SummariseResourceDownloadsAction.php             # NEW — handle(SummaryQueryData): array
├── DataTransferObjects/
│   ├── ResourceDownloadContextData.php                  # NEW — device_id/language/source/user_id (resolved from request)
│   └── SummaryQueryData.php                             # NEW — from/to/group_by/downloadable_type/language
├── Events/
│   └── DownloadOccurred.php                             # NEW — Laravel event; carries event_type, subject, context
└── Support/
    └── ClientContextResolver.php                        # NEW — fromRequest(Request): ResourceDownloadContextData

app/Http/Controllers/Api/V1/EducationalResources/
├── ListResourceBooksController.php                      # NEW
├── ShowResourceBookController.php                       # NEW
├── ShowResourceBookChapterController.php                # NEW
├── RecordEducationalResourceDownloadController.php      # NEW — POST /resources/{resource:uuid}/downloads
├── RecordResourceBookDownloadController.php             # NEW — POST /resource-books/{book:slug}/downloads
└── RecordResourceBookChapterDownloadController.php      # NEW — POST /resource-books/{book:slug}/chapters/{chapter}/downloads

app/Http/Controllers/Api/V1/Admin/EducationalResources/
├── ListResourceBooksController.php                      # NEW (admin)
├── CreateResourceBookController.php                     # NEW
├── UpdateResourceBookController.php                     # NEW
├── DeleteResourceBookController.php                     # NEW
├── PublishResourceBookController.php                    # NEW
├── UnpublishResourceBookController.php                  # NEW
├── ReorderResourceBooksController.php                   # NEW
├── ListResourceBookChaptersController.php               # NEW
├── CreateResourceBookChapterController.php              # NEW
├── UpdateResourceBookChapterController.php              # NEW
├── DeleteResourceBookChapterController.php              # NEW
├── ReorderResourceBookChaptersController.php            # NEW
└── ShowResourceDownloadsSummaryController.php           # NEW

app/Http/Requests/EducationalResources/                  # NEW public requests
├── ListResourceBooksRequest.php                         # ?language=&page=
├── ShowResourceBookChapterRequest.php                   # validates {chapter} as int
├── RecordEducationalResourceDownloadRequest.php         # body: optional device_id, source
├── RecordResourceBookDownloadRequest.php
└── RecordResourceBookChapterDownloadRequest.php

app/Http/Requests/Admin/EducationalResources/            # NEW admin requests (every write has its own)
├── ListResourceBooksRequest.php                         # ?language=&published=&page=
├── CreateResourceBookRequest.php
├── UpdateResourceBookRequest.php
├── DeleteResourceBookRequest.php                        # empty body — gate via super-admin mw
├── PublishResourceBookRequest.php
├── UnpublishResourceBookRequest.php
├── ReorderResourceBooksRequest.php                      # body: {language, ids[]}
├── ListResourceBookChaptersRequest.php
├── CreateResourceBookChapterRequest.php
├── UpdateResourceBookChapterRequest.php
├── DeleteResourceBookChapterRequest.php
├── ReorderResourceBookChaptersRequest.php               # reuses Admin\ReorderRequest semantically — see task list (decision in task 23)
└── ShowResourceDownloadsSummaryRequest.php              # ?from=&to=&group_by=&downloadable_type=&language=

app/Http/Resources/EducationalResources/                 # NEW
├── ResourceBookListResource.php
├── ResourceBookDetailResource.php
├── ResourceBookChapterListItemResource.php              # nested in detail: id, position, title, has_audio, duration_seconds
├── ResourceBookChapterResource.php                      # full chapter content
├── AdminResourceBookResource.php                        # adds id, is_published, position
└── AdminResourceBookChapterResource.php                 # adds id

app/Http/Resources/Analytics/                            # NEW
└── ResourceDownloadSummaryRowResource.php

database/migrations/                                     # NEW (timestamp slice 2026_05_03_002000+ — after MBA-023)
├── 2026_05_03_002000_evolve_resource_books_table.php
├── 2026_05_03_002001_evolve_resource_book_chapters_table.php
└── 2026_05_03_002002_evolve_resource_downloads_for_polymorphic_shape.php

database/factories/                                      # NEW
├── ResourceBookFactory.php                              # name, slug, language, description, cover_image_url + draft()/published() states
├── ResourceBookChapterFactory.php                       # title, content, audio defaults; withAudio() state
└── ResourceDownloadFactory.php                          # polymorphic helper: forResource()/forBook()/forChapter() states
```

`AppServiceProvider::boot()` — add `Relation::enforceMorphMap([
'educational_resource' => EducationalResource::class,
'resource_book' => ResourceBook::class,
'resource_book_chapter' => ResourceBookChapter::class,
])` and the `downloads` rate-limiter.

## Schema changes

| Table | Change | Notes |
|---|---|---|
| `resource_books` | `+ slug VARCHAR(255) UNIQUE NOT NULL` | Backfilled `Str::slug(strtolower(name))` with `-N` collision suffix; column added nullable, backfilled, then made NOT NULL UNIQUE. |
| `resource_books` | `+ position INT UNSIGNED DEFAULT 0` | Admin reorder. |
| `resource_books` | `+ is_published BOOLEAN DEFAULT false` | Drafts hidden by default. |
| `resource_books` | `+ published_at TIMESTAMP NULL` | Set by publish action. |
| `resource_books` | `+ cover_image_url VARCHAR(255) NULL` | Stored URL, not path — file generation deferred. |
| `resource_books` | `+ author VARCHAR(255) NULL` | |
| `resource_books` | `+ deleted_at TIMESTAMP NULL` | Soft delete. |
| `resource_books` | `language → CHAR(2)` | Engineer must verify MBA-023's `standardise_language_column_widths` covered `resource_books`; add to that list if missing before resizing here, otherwise `ron`/`eng` truncate. |
| `resource_books` | drop legacy Symfony columns absent from AC §1 (engineer enumerates from current schema) | After confirming no Laravel reads them. |
| `resource_books` | `+ INDEX (language, is_published, position)` | AC §2 — primary list query. |
| `resource_book_chapters` | rename `resource_book` → `resource_book_id` if present | Via `ReconcileTableHelper::renameColumnIfPresent`. FK re-asserted ON DELETE CASCADE. |
| `resource_book_chapters` | `+ duration_seconds INT UNSIGNED NULL` | AC §3 — populated by admin/extraction (out of scope here). |
| `resource_book_chapters` | preserve `audio_cdn_url`, `audio_embed` as `TEXT NULL` | Confirm column types match TEXT after rename; widen if Symfony shipped them as VARCHAR. |
| `resource_book_chapters` | `+ UNIQUE (resource_book_id, position)` | AC §4. |
| `resource_downloads` | rename `resource_id` → `downloadable_id` | Via `ReconcileTableHelper::renameColumnIfPresent`. |
| `resource_downloads` | `+ downloadable_type VARCHAR(64)` | Added nullable; backfilled `'educational_resource'` for legacy rows; altered NOT NULL. |
| `resource_downloads` | `+ user_id INT UNSIGNED NULL` | FK → `users.id` ON DELETE SET NULL. |
| `resource_downloads` | `+ device_id VARCHAR(64) NULL` | |
| `resource_downloads` | `+ language CHAR(2) NULL` | |
| `resource_downloads` | `+ source VARCHAR(16) NULL` | |
| `resource_downloads` | drop `ip_address` | Per stakeholder (AC §8). |
| `resource_downloads` | `+ INDEX (downloadable_type, downloadable_id, created_at)` | AC §7. |
| `resource_downloads` | `+ INDEX (user_id, created_at)` | |
| `resource_downloads` | `+ INDEX (created_at)` | For daily aggregation. |

## HTTP endpoints

| Verb | Path | Controller | Request | Resource | Auth/middleware |
|---|---|---|---|---|---|
| GET | `/api/v1/resource-books` | `ListResourceBooksController` | `ListResourceBooksRequest` | `ResourceBookListResource::collection` | `api-key-or-sanctum` + `resolve-language` + `throttle:public-anon` + `cache.headers:public;max_age=3600;etag` |
| GET | `/api/v1/resource-books/{book:slug}` | `ShowResourceBookController` | implicit | `ResourceBookDetailResource` | same |
| GET | `/api/v1/resource-books/{book:slug}/chapters/{chapter}` | `ShowResourceBookChapterController` | `ShowResourceBookChapterRequest` | `ResourceBookChapterResource` | `api-key-or-sanctum` + `resolve-language` + `throttle:public-anon` + `cache.headers:public;max_age=600;etag` + `scopeBindings` |
| POST | `/api/v1/resources/{resource:uuid}/downloads` | `RecordEducationalResourceDownloadController` | `RecordEducationalResourceDownloadRequest` | 204 | `api-key-or-sanctum` + `resolve-language` + `throttle:downloads` |
| POST | `/api/v1/resource-books/{book:slug}/downloads` | `RecordResourceBookDownloadController` | `RecordResourceBookDownloadRequest` | 204 | same |
| POST | `/api/v1/resource-books/{book:slug}/chapters/{chapter}/downloads` | `RecordResourceBookChapterDownloadController` | `RecordResourceBookChapterDownloadRequest` | 204 | same + `scopeBindings` |
| GET | `/api/v1/admin/resource-books` | `Admin\ListResourceBooksController` | `Admin\ListResourceBooksRequest` | `AdminResourceBookResource::collection` | `auth:sanctum` + `super-admin` |
| POST | `/api/v1/admin/resource-books` | `Admin\CreateResourceBookController` | `Admin\CreateResourceBookRequest` | `AdminResourceBookResource` | same |
| PATCH | `/api/v1/admin/resource-books/{book}` | `Admin\UpdateResourceBookController` | `Admin\UpdateResourceBookRequest` | `AdminResourceBookResource` | same |
| DELETE | `/api/v1/admin/resource-books/{book}` | `Admin\DeleteResourceBookController` | `Admin\DeleteResourceBookRequest` | 204 | same |
| POST | `/api/v1/admin/resource-books/{book}/publish` | `Admin\PublishResourceBookController` | `Admin\PublishResourceBookRequest` | `AdminResourceBookResource` | same |
| POST | `/api/v1/admin/resource-books/{book}/unpublish` | `Admin\UnpublishResourceBookController` | `Admin\UnpublishResourceBookRequest` | `AdminResourceBookResource` | same |
| POST | `/api/v1/admin/resource-books/reorder` | `Admin\ReorderResourceBooksController` | `Admin\ReorderResourceBooksRequest` | `{ message: "Reordered." }` | same |
| GET | `/api/v1/admin/resource-books/{book}/chapters` | `Admin\ListResourceBookChaptersController` | `Admin\ListResourceBookChaptersRequest` | `AdminResourceBookChapterResource::collection` | same |
| POST | `/api/v1/admin/resource-books/{book}/chapters` | `Admin\CreateResourceBookChapterController` | `Admin\CreateResourceBookChapterRequest` | `AdminResourceBookChapterResource` | same |
| PATCH | `/api/v1/admin/resource-book-chapters/{chapter}` | `Admin\UpdateResourceBookChapterController` | `Admin\UpdateResourceBookChapterRequest` | `AdminResourceBookChapterResource` | same |
| DELETE | `/api/v1/admin/resource-book-chapters/{chapter}` | `Admin\DeleteResourceBookChapterController` | `Admin\DeleteResourceBookChapterRequest` | 204 | same |
| POST | `/api/v1/admin/resource-books/{book}/chapters/reorder` | `Admin\ReorderResourceBookChaptersController` | `Admin\ReorderResourceBookChaptersRequest` | `{ message: "Reordered." }` | same |
| GET | `/api/v1/admin/resource-downloads/summary` | `Admin\ShowResourceDownloadsSummaryController` | `Admin\ShowResourceDownloadsSummaryRequest` | `{ data: ResourceDownloadSummaryRowResource[], meta: { from, to, group_by } }` | same |

Route-model binding strategy: `ResourceBook::resolveRouteBinding($value,
$field)` applies `published()` when `$field === 'slug'` and skips it
otherwise (admin id-binding sees drafts). Nested `{chapter}` under
`{book:slug}` is wrapped in `Route::scopeBindings()` so a chapter id from a
different book 404s. Top-level `{chapter}` PATCH/DELETE under `/admin/resource-book-chapters/{chapter}` uses default id-binding because the URL doesn't carry the parent book.

## Tasks

- [x] 1. Write `2026_05_03_002000_evolve_resource_books_table.php` — early-return when `resource_books` absent; add `slug` (nullable), `position`, `is_published`, `published_at`, `cover_image_url`, `author`, `deleted_at`; backfill `slug` from `Str::slug(strtolower(name))` with numeric collision suffix; alter `slug` NOT NULL UNIQUE; resize `language` → `CHAR(2)` (verify MBA-023 backfill list includes `resource_books`, extend if missing); drop unused legacy Symfony columns; add `INDEX (language, is_published, position)`. Down() reverts column adds + index drop; does not restore Symfony-only columns.
- [x] 2. Write `2026_05_03_002001_evolve_resource_book_chapters_table.php` — early-return when table absent; rename FK column → `resource_book_id` via `ReconcileTableHelper::renameColumnIfPresent`; ensure FK ON DELETE CASCADE; widen `audio_cdn_url`/`audio_embed` to `TEXT` if Symfony shipped them VARCHAR; add `duration_seconds`; ensure `UNIQUE (resource_book_id, position)`.
- [x] 3. Write `2026_05_03_002002_evolve_resource_downloads_for_polymorphic_shape.php` — early-return when table absent; rename `resource_id` → `downloadable_id`; add `downloadable_type` (nullable), `user_id` (FK SET NULL), `device_id`, `language`, `source`; backfill `downloadable_type='educational_resource'` for all existing rows; alter `downloadable_type` NOT NULL; drop `ip_address`; add the three indexes from AC §7.
- [x] 4. Add `ResourceBook` model (`SoftDeletes` trait, `slug` route key, casts for `is_published`/`published_at`/`position`, `chapters()` hasMany ordered by `position`, `withCount('chapters')` macro on QueryBuilder, override `resolveRouteBinding` to apply `published()` when `$field === 'slug'`).
- [x] 5. Add `ResourceBookQueryBuilder` with `published()` (where `is_published = true`), `forLanguage(Language)`, `orderedForList()` (`ORDER BY position ASC, published_at DESC`).
- [x] 6. Add `ResourceBookChapter` model (casts for `position`/`duration_seconds` to int; `book()` belongsTo; `hasAudio()` accessor returning `audio_cdn_url !== null || audio_embed !== null`) with `ResourceBookChapterQueryBuilder::ordered()`.
- [x] 7. Add `ResourceDownload` model in `App\Domain\Analytics\Models\` with `morphTo downloadable()` and public constants `TYPE_EDUCATIONAL_RESOURCE='educational_resource'`, `TYPE_RESOURCE_BOOK='resource_book'`, `TYPE_RESOURCE_BOOK_CHAPTER='resource_book_chapter'`; `$timestamps = ['created_at']` only (no `updated_at`); `ResourceDownloadQueryBuilder::for(Model)`, `::countsByDay(Carbon $from, Carbon $to)`.
- [x] 8. In `AppServiceProvider::boot()`: register `Relation::enforceMorphMap` mapping the three constants to model classes; register named limiter `RateLimiter::for('downloads', …)` keyed by `ip|device_id` at 60/min.
- [x] 9. Add `ResourceBookFactory` (with `draft()`/`published()` states), `ResourceBookChapterFactory` (with `withAudio()` state), `ResourceDownloadFactory` (with `forResource(EducationalResource)`/`forBook(ResourceBook)`/`forChapter(ResourceBookChapter)` states). Hook factories via `#[UseFactory]` attribute on each model.
- [x] 10. Add `ResourceBooksCacheKeys` in `EducationalResources/Support/` with `TAG_ROOT='resource_books'`, key builders `list(Language $lang, int $page, int $perPage)`, `detail(string $slug)`, `chapter(string $bookSlug, int $chapterId)`, plus `tagsForList()`, `tagsForBook(int $bookId)`.
- [x] 11. Add `ResourceBookData` and `ResourceBookChapterData` readonly DTOs with `from(array $validated)` constructors; nullability matches AC's NULL columns.
- [x] 12. Add `ResourceDownloadContextData` (readonly: `?int $userId`, `?string $deviceId`, `?string $language`, `?string $source`) and `ClientContextResolver::fromRequest(Request)` extracting from `X-Device-Id` header + User-Agent (substring rules: `MyBibleMobile…ios → 'ios'`; `…android → 'android'`; default `'web'`; unrecognised → `null`) + `ResolveRequestLanguage::ATTRIBUTE_KEY`.
- [x] 13. Add `DownloadOccurred` event class in `App\Domain\Analytics\Events\` carrying `string $eventType`, `Model $subject`, `ResourceDownloadContextData $context`, `ResourceDownload $row`; no listener registered in this story (lands in MBA-030).
- [x] 14. Add `RecordResourceDownloadAction::handle(Model $target, ResourceDownloadContextData $context, string $eventType): ResourceDownload` — inserts the polymorphic row in a transaction, dispatches `DownloadOccurred` after commit; rejects unsupported `$target` types via a typed assertion against the morphMap.
- [x] 15. Implement public `ListResourceBooksController` + `ListResourceBooksRequest` (validates optional `?language=` enum, `?page=` int) + `ListResourceBooksAction` (cached via `CachedRead` keyed by `ResourceBooksCacheKeys::list`, paginated, returns `ResourceBookListResource::collection`) + route in `routes/api.php`. Feature test covers happy path, drafts hidden, language scoping, pagination shape, cache-control header.
- [x] 16. Implement `ShowResourceBookController` + `ShowResourceBookAction` (cached, eager-loads `chapters` ordered by position) + `ResourceBookDetailResource` nesting `ResourceBookChapterListItemResource[]`. Feature test covers happy path, 404 on draft, 404 on unknown slug, has_audio boolean correctness.
- [x] 17. Implement `ShowResourceBookChapterController` + `ShowResourceBookChapterRequest` (validates `{chapter}` int) + `ShowResourceBookChapterAction` (cached when book published) + `ResourceBookChapterResource` (full content, audio_cdn_url, audio_embed, duration_seconds) + scoped route. Feature test covers happy path, 404 on draft book, 404 on chapter from different book, cache-control header.
- [x] 18. Implement `RecordEducationalResourceDownloadController` + `RecordEducationalResourceDownloadRequest` (validates optional `device_id` string, `source` in enum) + route. Calls `ClientContextResolver::fromRequest` then `RecordResourceDownloadAction` with eventType `'resource.downloaded'`. Returns 204. Feature test covers anonymous accept, authenticated user_id capture, source inference from User-Agent, polymorphic row persistence, and rate-limit triggering at the 61st request.
- [x] 19. Implement `RecordResourceBookDownloadController` + request + route (eventType `'resource_book.downloaded'`). Feature test covers happy path, 404 on draft book (slug binding applies `published()`), polymorphic row asserts `downloadable_type='resource_book'`.
- [x] 20. Implement `RecordResourceBookChapterDownloadController` + request + scoped route (eventType `'resource_book.chapter.downloaded'`). Feature test covers happy path, 404 on chapter from a different book, polymorphic row asserts `downloadable_type='resource_book_chapter'`.
- [x] 21. Implement admin CRUD for resource books — `Admin\ListResourceBooksController` (returns drafts + published; filters `?language=`, `?published=`); `CreateResourceBookController` + `CreateResourceBookAction` (validates slug unique, language enum, name required); `UpdateResourceBookController` + `UpdateResourceBookAction` (slug unique except current); `DeleteResourceBookController` + `DeleteResourceBookAction` (soft-delete; schedules `cover_image_url` cleanup if file-disk-backed). Each request has its own `Admin\…Request` class. Each gets a feature test for 401, 403 (non-super), 422, happy path. Wire all four in `routes/api.php` under the `auth:sanctum` + `super-admin` admin group.
- [x] 22. Implement `Admin\PublishResourceBookController` + `Admin\UnpublishResourceBookController` backed by `SetResourceBookPublicationAction` (sets `is_published`, sets `published_at = now()` on first publish, leaves `published_at` intact on unpublish so re-publish reuses) + empty-body request classes + routes. Cache flush `ResourceBooksCacheKeys::tagsForList()` on transition. Feature test asserts publish/unpublish round-trip and that public list reflects the state change.
- [x] 23. Implement `Admin\ReorderResourceBooksController` + `Admin\ReorderResourceBooksRequest` (body `{language: enum, ids: int[]}`) + `ReorderResourceBooksAction` (asserts every id has the requested language; updates `position` in transaction). Feature test covers happy path, 422 on cross-language ids, idempotency.
- [x] 24. Implement admin chapter CRUD — `Admin\ListResourceBookChaptersController` (paginated, ordered by position); `CreateResourceBookChapterController` + `CreateResourceBookChapterAction` (auto-assigns next `position` if not provided; validates `position` unique within book if provided); `UpdateResourceBookChapterController` + `UpdateResourceBookChapterAction` (partial); `DeleteResourceBookChapterController` + `DeleteResourceBookChapterAction` (hard delete — chapters cascade with book). Each gets a request class and a feature test (happy path + 404 on cross-book chapter for the top-level routes).
- [x] 25. Implement `Admin\ReorderResourceBookChaptersController` + `Admin\ReorderResourceBookChaptersRequest` (body `{ids: int[]}` — reuses semantics of `Admin\ReorderRequest` but a dedicated subclass keeps siblings parity with the parent ReorderResourceBooksRequest pair) + `ReorderResourceBookChaptersAction` (asserts every id belongs to the parent book; updates `position` in transaction). Feature test covers happy path and 422 on cross-book ids.
- [x] 26. Implement `Admin\ShowResourceDownloadsSummaryController` + `Admin\ShowResourceDownloadsSummaryRequest` (validates `from`/`to` dates, `group_by` in `{day, week, month}`, optional `downloadable_type` against morphMap keys, optional `language`) + `SummariseResourceDownloadsAction` + `ResourceDownloadSummaryRowResource`. Action: when `to-from <= 7 days` query `resource_downloads` directly via `countsByDay` + group; when range exceeds 7 days throw `BadRequestHttpException` with the AC §22 + Risks-noted "MBA-030 rollups required" message until MBA-030 lands the rollup table. Feature test covers super-admin gate, ≤7-day range happy path, >7-day range returns 400 with the explicit deferral message.
- [x] 27. Add migration test in `tests/Feature/Database/ResourceDownloadsMigrationTest.php` (per AC §26) — seeds Symfony-shape rows pre-migration (`resource_id`, `ip_address`), runs the migration, asserts post-migration shape: `downloadable_type='educational_resource'`, `downloadable_id` matches old `resource_id`, `ip_address` column absent, indexes present.
- [x] 28. Add unit tests for `ClientContextResolver` covering: iOS UA → `'ios'`, Android UA → `'android'`, browser UA → `'web'`, unrecognised UA → `null`, missing `X-Device-Id` → `device_id=null`. Pure parser logic not exhaustively covered by feature tests.
- [x] 29. Run `make lint-fix` + `make stan` + `make test-api filter=ResourceBook` + `make test-api filter=ResourceDownload` + `make test-api`; confirm full suite passes before handoff.

## Risks & open questions

- **MBA-030 rollup dependency for downloads summary.** AC §22 requires
  joining to `analytics_daily_rollups` for ranges > 7 days; that table
  doesn't ship until MBA-030. Resolution: throw a typed 400 on long
  ranges with an explicit deferral message until MBA-030 lands. Admin
  dashboard owners (admin MB-015) must not rely on long-range queries
  before MBA-030 is deployed.
- **Symfony column inventory on `resource_books`.** This story drops
  legacy Symfony-only columns absent from AC §1; the engineer must
  enumerate them from the live schema (`SHOW CREATE TABLE
  resource_books`) before writing the migration. Dropping a column
  silently shadowed by a Laravel reader would break a feature; if any
  column is questionable, add it to a separate "deferred drop" migration
  rather than blanket-dropping in this story.
- **`device_id` cardinality.** Anonymous mobile clients send a UUID;
  some web clients may send a localStorage token of arbitrary length.
  The 64-char cap follows MBA-030's convention — if frontend MB-020
  ever exceeds it, the column truncates silently and devices coalesce.
  The frontend story owns sending a 64-char-or-less token.
- **MBA-023 language widening list.** If `resource_books` is missing
  from MBA-023's `standardise_language_column_widths` target list, the
  resize in task 1 truncates `ron`/`eng` legacy values. Engineer must
  verify before running and either extend MBA-023's list (one-line edit)
  or run an inline backfill in this story's migration before resize.
- **Admin BookBrowser drift.** Admin `BookBrowser` component already
  exists at `/admin/resources/books/{id}` and may expect a different
  endpoint shape. This story is the authority; admin client realignment
  lands in admin MB-015. Do not let the existing admin client shape
  reverse-engineer this story's contract.
- **Soft-deleted books in download URLs.** A POST to
  `/resource-books/{book:slug}/downloads` after the book is soft-
  deleted: model trait excludes it globally → 404. Acceptable. If a
  legitimate "download history persists past soft-delete" need surfaces,
  it's a separate story (querying `withTrashed()` on the morph target).

## References

- MBA-023 reconcile foundation — table renames + `ReconcileTableHelper` +
  `BackfillLegacyLanguageCodesAction` precedent.
- MBA-024 commentary plan — published-on-slug `resolveRouteBinding`
  precedent + admin/public Resource split.
- MBA-016 educational resources — `CachedRead` + tag invalidation +
  list/detail Resource split + reorder Action precedent.
- MBA-030 analytics foundation — `DownloadOccurred` listener consumer +
  `analytics_daily_rollups` table for the >7-day summary branch.
- MBA-031 Symfony ETL — verifies post-migration shape of
  `resource_downloads`; ETL job runs alongside this story's migration as
  a defensive verification.
- `ReadingPlan::resolveRouteBinding()` and `Commentary::resolveRouteBinding()`
  — slug-binding scoped-to-published precedent.
- `EducationalResources/Support/EducationalResourcesCacheKeys.php` —
  cache-key + tag helper precedent (sibling for `ResourceBooksCacheKeys`).
