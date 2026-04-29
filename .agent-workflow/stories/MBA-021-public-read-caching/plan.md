# Plan: MBA-021-public-read-caching

## Approach

Wire the existing `redis` cache store to managed Valkey, then push a single
`Cache::tags($tags)->flexible($key, [$ttl, $ttl + $grace], $build)` call into
each public read path. The wrapping happens in **Domain Actions** — preserving
the existing `Action → Resource` boundary in controllers — and the closure
returns the **resolved Resource array** so cache hits skip ORM hydration. A
small `CachedRead` helper centralises hit/miss logging + Sentry tagging so
each Action calls `$this->cache->read(...)` rather than the facade chain
directly. Cache keys live in per-domain const classes
(`<Domain>\Support\<Domain>CacheKeys`) so write paths and tests reference the
same source of truth and never hard-code strings.

Public read endpoints fall into three groups for refactor cost: **(a) already
have an Action** — wrap the existing call (5 endpoints); **(b) no Action
today, controller calls a QueryBuilder/`paginate()` directly** — extract a
read Action, then wrap (8 endpoints); **(c) streaming/buffered** — `bible
versions/{v}/export` is the outlier and gets a non-streamed `BibleExportBuilder`
that returns a JSON string the cache can hold (1 endpoint).

Invalidation hooks are **named in a per-domain `tagsFor*()` method** but are
not wired into write paths today: every existing write path is per-user and
touches no public-read tag (favorites, highlights, answers, notes, profile,
reading-plan-subscriptions). Public-content writes — admin lesson update,
devotional import, news publish, bible version add — do not yet exist as
Domain Actions in this codebase; the cutover (MBA-020) is the upstream
writer. Until those Actions land, the artisan command
`mybible:cache-clear-tag {tag}` exists for ops and deploy hooks; the future
admin/import story will call the `tagsFor*()` helpers from inside its own
Actions.

Story is large — 14 endpoints, 9 new Actions, infra, observability, runbook —
but cohesive: the helper, key class pattern, test pattern, and Sentry wiring
are each built once and reused across every endpoint, so a split would
duplicate scaffolding. **No split recommended.** If a smaller first slice is
preferred, see "Risks & notes" §1 for a 3-PR cut.

## Open questions — resolutions

1. **`Cache::flexible` vs lock+remember.** Use `Cache::flexible($key,
   [$ttl, $ttl + 60], $build)` — Laravel 13 ships it on `Repository`, the
   `RedisStore` extends `TaggableStore`, and `flexible` already serialises
   regeneration via an internal lock so a cold miss on a hot key cannot
   stampede MySQL. The `60s` grace window is a single project-wide constant
   (`config('cache.flexible_grace_seconds')`, default `60`), not per-endpoint
   — uniform behaviour is easier to reason about than per-route tuning.
2. **Per-language fanout for `daily-verse`.** The `daily_verse` table has no
   `language` column (`App\Domain\Verses\Models\DailyVerse`); a row is
   keyed by `for_date` only, the verse text is rendered language-side via
   `BibleVerse` lookup downstream of the cached payload. Story key shape
   `verses:daily:{date}` is correct as-is; do **not** add `{lang}`.
3. **Tag flush vs explicit forget.** Per-entity tags (`ss:lesson:{id}`,
   `dev:{lang}:{type}`, `col:topic:{id}`, `oly:theme:{book}:{chapters}:{lang}`,
   `edu:cat:{id}`) → `Cache::tags(...)->flush()` on write — the tag is
   already narrow. Coarse tags (`ss`, `bible`, `news`, `qr`, `daily-verse`)
   → only flushed by the artisan command, never from a hot write path; a
   daily-import job that touched `ss` would purge every lesson page and
   defeat the cache.
4. **Sentry hit-rate metric vs StatsD.** Sentry transaction tag
   `cache_status: hit|miss` is sufficient for the rollout signal. Sentry's
   Performance view groups by tag, and 14 endpoints × hit/miss is a
   tractable cardinality. Prometheus is a separate observability story
   (deferred).
5. **Cache-warming on deploy.** Out of scope. Flag a follow-up `cache:warm`
   command that primes the top-N SS lessons + bible-versions list at deploy
   time once observability lands. If hit-rate after one peak Saturday is
   below 90%, that follow-up is the recommended next story.
6. **Story key shape gaps.** Story keys omit page numbers for
   `bible:versions:list`, `edu:categories:{lang}`, `col:topics:{lang}`,
   `oly:themes:{lang}` despite those endpoints paginating. Use
   `:p{page}:{perPage}` suffix on each. Key string lives in the per-domain
   `CacheKeys` class so the deviation from the story table is documented in
   one place.
7. **`oly:theme` key.** Story uses `oly:theme:{id}` but olympiad themes have
   no `id` — they are tuples. Use `oly:theme:{book}:{chapters}:{lang}` and
   shuffle outside the cache so a single cached payload covers any seed.
8. **Cache the resolved Resource array, not the Eloquent collection.**
   Story Technical Notes are explicit. The Action returns
   `array{data: ..., meta: ..., links: ...}`. To resolve a Resource the
   action needs a `Request`; pass it as a constructor-time scoped parameter
   via `request()` inside the rebuild closure (the helper detail) — Actions
   keep their public DTO-only signatures. This is the single, scoped use of
   `request()` inside a Domain Action; documented in `CachedRead`.

## Domain layout

```
app/Support/Caching/
├── CachedRead.php                       # read($key, $tags, $ttl, $build): mixed — wraps Cache::tags(...)->flexible(...) + emits log+Sentry tags
├── CacheStoreGuard.php                  # boot-time: throws if cache.default store is not Taggable
└── ClearCacheTagCommand.php             # `php artisan mybible:cache-clear-tag {tag}` — ops + deploy hook

app/Http/Controllers/HealthCheckController.php   # extends `/up` to ping Redis + DB

app/Providers/AppServiceProvider.php             # boot(): CacheStoreGuard::ensureTaggable()

app/Domain/SabbathSchool/
├── Support/SabbathSchoolCacheKeys.php           # lessonsList(...), lesson($id, $lang), tagsForLessonsList(), tagsForLesson($id)
└── Actions/
    ├── ListSabbathSchoolLessonsAction.php       # NEW — cached
    └── ShowSabbathSchoolLessonAction.php        # NEW — cached; takes lesson id (not model) so cache miss is the binding query

app/Domain/Devotional/
├── Support/DevotionalCacheKeys.php
└── Actions/FetchDevotionalAction.php            # MODIFIED — cache wrap

app/Domain/Verses/
├── Support/VersesCacheKeys.php
└── Actions/GetDailyVerseAction.php              # MODIFIED — cache wrap

app/Domain/News/
├── Support/NewsCacheKeys.php
└── Actions/ListNewsAction.php                   # NEW — cached

app/Domain/Bible/
├── Support/BibleCacheKeys.php
├── Support/BibleExportBuilder.php               # NEW — build(BibleVersion): string (was streamed; now buffered)
└── Actions/
    ├── ListBibleVersionsAction.php              # NEW — cached
    └── ExportBibleVersionAction.php             # NEW — cached (string payload)

app/Domain/EducationalResources/
├── Support/EducationalResourcesCacheKeys.php
└── Actions/
    ├── ListResourceCategoriesAction.php         # NEW — cached
    └── ListResourcesByCategoryAction.php        # NEW — cached

app/Domain/Collections/
├── Support/CollectionsCacheKeys.php
└── Actions/
    ├── ListCollectionTopicsAction.php           # NEW — cached
    └── ShowCollectionTopicAction.php            # NEW — cached; wraps existing ResolveCollectionReferencesAction

app/Domain/Olympiad/
├── Support/OlympiadCacheKeys.php
└── Actions/
    ├── ListOlympiadThemesAction.php             # MODIFIED — cache wrap
    └── FetchOlympiadThemeQuestionsAction.php    # MODIFIED — cache the unshuffled set; shuffle outside cache

app/Domain/QrCode/
├── Support/QrCodeCacheKeys.php
└── Actions/ShowQrCodeAction.php                 # NEW — cached

config/cache.php                                 # add 'flexible_grace_seconds' => env(..., 60)

routes/api.php                                   # /up override → HealthCheckController (existing health: '/up' kept as fallback)
.env.example                                     # CACHE_PREFIX=mybible-api, REDIS_USERNAME, REDIS_SCHEME, REDIS_CACHE_DB
docs/runbook/cache.md                            # NEW — hit-rate target ≥ 90%, troubleshooting, ops commands
```

No new directory bases. No new packages.

## Key types

| Type | Role |
|---|---|
| `App\Support\Caching\CachedRead` | Single `read(string $key, array $tags, int $ttl, Closure $build): mixed`. Calls `Cache::tags($tags)->flexible($key, [$ttl, $ttl + $grace], $build)`; sets a `bool $miss` flag in the closure scope; on miss emits `Log::info('cache.miss', ['key' => $key])` and `Sentry\configureScope(fn ($s) => $s->setTag('cache_status', 'miss'))`; on hit sets the same tag to `'hit'`. `$grace` from `config('cache.flexible_grace_seconds')`. Constructor-injected wherever cached Actions are constructed; one process-wide instance. |
| `App\Support\Caching\CacheStoreGuard` | `ensureTaggable(): void` — throws `RuntimeException` at boot if `Cache::store()->getStore() instanceof TaggableStore` is false, message names the store driver so a mis-set `CACHE_STORE` fails fast in CI rather than at the first write-side `flush()`. |
| `App\Support\Caching\ClearCacheTagCommand` | Artisan: `mybible:cache-clear-tag {tag} {--dry-run}`. Calls `Cache::tags([$tag])->flush()`. Logs the operation. Used by ops + deploy hooks. |
| `App\Http\Controllers\HealthCheckController` | `__invoke(): JsonResponse` — pings Redis (`Redis::connection('cache')->ping()`) and DB (`DB::select('SELECT 1')`); returns `200` with `{ok: true, services: {redis, db}}` or `503` on failure. Replaces the framework default at `GET /up`. |
| `App\Domain\<X>\Support\<X>CacheKeys` (9 classes) | Static-method-only const class. One method per cached endpoint returning the key string; one `tagsFor*(...)` per write-shape returning `array<int,string>`. All keys derive from typed primitives — no string interpolation in callers. Examples: `SabbathSchoolCacheKeys::lessonsList(Language $lang, int $page, int $perPage): string`; `SabbathSchoolCacheKeys::tagsForLesson(int $id): array`. |
| `App\Domain\Bible\Support\BibleExportBuilder` | `build(BibleVersion $version): string` — refactor of `BibleVersionExporter::stream()` that emits the full JSON payload as an in-memory string (suitable for cache storage). The streaming variant `stream(BibleVersion)` becomes a thin wrapper that emits `build()`'s string in a `StreamedResponse` body so other callers (none today) stay compatible. |
| `<Domain>Action::execute(...)` (per Action) | All cached Actions: public method takes the same DTO/primitive args as before and returns `array` (the resolved-Resource shape). The cache `build` closure does the query + Resource resolution via `request()` inside the closure. The Controller gets an array, returns `response()->json($array)->header('Cache-Control', ...)`. |

## HTTP endpoints (cached)

Each endpoint's controller becomes a 3-line invokable: build a DTO from the
Form Request, call the Action, return `response()->json($action_array)` with
the existing `Cache-Control` header preserved (per AC 5). Auth/middleware
stacks are unchanged.

| Method + Path | Controller | Action (new or existing) | Resource |
|---|---|---|---|
| `GET /api/v1/sabbath-school/lessons` | `ListSabbathSchoolLessonsController` | `ListSabbathSchoolLessonsAction` (NEW) | `SabbathSchoolLessonSummaryResource` |
| `GET /api/v1/sabbath-school/lessons/{lesson}` | `ShowSabbathSchoolLessonController` | `ShowSabbathSchoolLessonAction` (NEW) | `SabbathSchoolLessonResource` |
| `GET /api/v1/devotionals` | `ShowDevotionalController` | `FetchDevotionalAction` (MOD) | `DevotionalResource` |
| `GET /api/v1/daily-verse` | `GetDailyVerseController` | `GetDailyVerseAction` (MOD) | `DailyVerseResource` |
| `GET /api/v1/news` | `ListNewsController` | `ListNewsAction` (NEW) | `NewsResource` |
| `GET /api/v1/bible-versions` | `ListBibleVersionsController` | `ListBibleVersionsAction` (NEW) | `BibleVersionResource` |
| `GET /api/v1/bible-versions/{version:abbreviation}/export` | `ExportBibleVersionController` | `ExportBibleVersionAction` (NEW) | raw JSON string (no Resource) |
| `GET /api/v1/resource-categories` | `ListResourceCategoriesController` | `ListResourceCategoriesAction` (NEW) | `ResourceCategoryResource` |
| `GET /api/v1/resource-categories/{category}/resources` | `ListResourcesByCategoryController` | `ListResourcesByCategoryAction` (NEW) | `EducationalResourceListResource` |
| `GET /api/v1/collections` | `ListCollectionTopicsController` | `ListCollectionTopicsAction` (NEW) | `CollectionTopicResource` |
| `GET /api/v1/collections/{topic}` | `ShowCollectionTopicController` | `ShowCollectionTopicAction` (NEW) | `CollectionTopicDetailResource` |
| `GET /api/v1/olympiad/themes` | `ListOlympiadThemesController` | `ListOlympiadThemesAction` (MOD) | `OlympiadThemeResource` |
| `GET /api/v1/olympiad/themes/{book}/{chapters}` | `ShowOlympiadThemeController` | `FetchOlympiadThemeQuestionsAction` (MOD) | `OlympiadThemeQuestionsResource` |
| `GET /api/v1/qr-codes` | `ShowQrCodeController` | `ShowQrCodeAction` (NEW) | `QrCodeResource` |

Route-model binding for `ShowSabbathSchoolLessonController`: switch from
implicit `SabbathSchoolLesson $lesson` to taking the integer id from the
route (`{lesson}` already a numeric segment). Reason: with implicit binding,
the published/eager-load lookup runs on every request — including cache
hits, defeating the cache. The Action accepts `int $lessonId`, runs the
binding query inside the rebuild closure, and throws
`ModelNotFoundException` if absent. Per project guidelines §5b: explicit
controller-side resolution chosen because the model has a scope
(`published()`) and cache hits must skip the bind query. The 404 path still
flows through the JSON exception handler.

## Cache key + tag matrix

Keys live in the per-domain `CacheKeys` class; the table is the spec the
classes implement. Page+perPage are added to all paginated keys (story
omits them on some — see Open question 6). All keys are prefixed by
`mybible-api:` via `cache.prefix`.

| Endpoint | Key shape | TTL | Tags |
|---|---|---|---|
| `GET /sabbath-school/lessons` | `ss:lessons:list:{lang}:p{page}:{perPage}` | 3600 | `ss`, `ss:lessons` |
| `GET /sabbath-school/lessons/{lesson}` | `ss:lesson:{id}:{lang}` | 3600 | `ss`, `ss:lesson:{id}` |
| `GET /devotionals` | `dev:{lang}:{type}:{date}` | 3600 | `dev`, `dev:{lang}:{type}` |
| `GET /daily-verse` | `verses:daily:{date}` | 1800 | `verses`, `daily-verse` |
| `GET /news` | `news:{lang}:p{page}:{perPage}` | 600 | `news` |
| `GET /bible-versions` | `bible:versions:list:{lang}:p{page}:{perPage}` | 86400 | `bible`, `bible:versions` |
| `GET /bible-versions/{v}/export` | `bible:export:{abbrev}` | 86400 | `bible`, `bible:export:{abbrev}` |
| `GET /resource-categories` | `edu:categories:{lang}:p{page}:{perPage}` | 3600 | `edu` |
| `GET /resource-categories/{c}/resources` | `edu:cat:{id}:p{page}:{perPage}:{type?}` | 3600 | `edu`, `edu:cat:{id}` |
| `GET /collections` | `col:topics:{lang}:p{page}:{perPage}` | 3600 | `col` |
| `GET /collections/{topic}` | `col:topic:{id}:{lang}` | 3600 | `col`, `col:topic:{id}` |
| `GET /olympiad/themes` | `oly:themes:{lang}:p{page}:{perPage}` | 3600 | `oly` |
| `GET /olympiad/themes/{book}/{chapters}` | `oly:theme:{book}:{chapters}:{lang}` | 3600 | `oly`, `oly:theme:{book}:{chapters}:{lang}` |
| `GET /qr-codes` | `qr:{canonical-reference}` | 86400 | `qr` |

Olympiad theme show: the cached payload is the **unshuffled** question set;
seeded shuffling stays in `FetchOlympiadThemeQuestionsAction` outside the
cache closure (a single cache entry serves any client `?seed=`).

Bible export: cached payload is the **JSON string** built by
`BibleExportBuilder::build()` (typically multi-MB). Eviction policy
`allkeys_lru` evicts these first under memory pressure (acknowledged in
story technical notes). HTTP `Cache-Control` + ETag in
`BibleCacheHeaders::forVersionExport()` remain — Cloudflare absorbs most
repeats; the application cache is the second tier.

## Invalidation map (future-write hooks)

These tag flushes are **named in the per-domain `CacheKeys::tagsFor*()`
helpers** so the future write Actions call them by name; today only the
artisan command exercises them.

| Future write Action | Helper it calls | Tags flushed |
|---|---|---|
| `UpdateSabbathSchoolLessonAction` | `tagsForLesson($id)` + (if `published_at`/`language` changed) `tagsForLessonsList()` | `ss:lesson:{id}` (+ `ss:lessons`) |
| `UpsertDevotionalAction` (import) | `tagsForDevotional($lang, $type)` | `dev:{lang}:{type}` |
| `UpsertDailyVerseAction` (import) | `tagsForDailyVerse()` | `daily-verse` |
| `PublishNewsAction` | `tagsForNews()` | `news` |
| `PublishEducationalResourceAction` | `tagsForCategory($id)` | `edu:cat:{id}` |
| `UpdateCollectionTopicAction` | `tagsForTopic($id)` | `col:topic:{id}` |
| `UpsertOlympiadThemeAction` (import) | `tagsForTheme($book, $chapters, $lang)` | `oly:theme:{book}:{chapters}:{lang}` |
| `UpsertQrCodeAction` (import) | `tagsForQr()` | `qr` |
| `RegisterBibleVersionAction` (import) | `tagsForVersionList()` + `tagsForExport($abbrev)` | `bible:versions` (+ `bible:export:{abbrev}` if existing) |

No write Action exists today for any of the above — confirmed by inspecting
`app/Domain/*/Actions/*`. The `tagsFor*()` helpers ship with this story but
are exercised only by the artisan command and by unit tests until those
write Actions land in a follow-up.

## Configuration changes

- `config/cache.php` — keep `default => env('CACHE_STORE', 'database')` but
  change the project default in `.env.example` to `redis` (already set in
  `.env`); add `flexible_grace_seconds => env('CACHE_FLEXIBLE_GRACE', 60)`.
- `config/cache.php` `prefix` — set the default fallback string to
  `mybible-api` (replacing the auto-slugged `app-name-cache-`) so a missing
  env still gives the documented prefix.
- `.env.example` — append: `CACHE_PREFIX=mybible-api`, `REDIS_USERNAME`,
  `REDIS_SCHEME=tcp` (with `tls` documented for production), `REDIS_CACHE_DB=1`.
- `config/database.php` `redis.cache.scheme` — add the missing `scheme`
  option from `REDIS_SCHEME` so production TLS terminates inside phpredis;
  the existing `cache` connection block already accepts the other
  TLS-relevant params.
- `bootstrap/app.php` — register no new middleware; the existing
  `cache.headers` HTTP cache stays. Health route override goes via
  `routes/api.php` (`Route::get('up', HealthCheckController::class)`).

## Tasks

- [x] 1. Add `App\Support\Caching\CachedRead` with `read(string $key, array $tags, int $ttl, Closure $build): mixed` that calls `Cache::tags($tags)->flexible($key, [$ttl, $ttl + $grace], $build)` where `$grace = config('cache.flexible_grace_seconds', 60)`. Inside, set a `$miss` flag captured by the closure, log `cache.miss` once at info level on miss, tag the current Sentry scope `cache_status: hit|miss`. Unit test: hit, miss, stampede serialised by the underlying lock, log emitted on miss only, Sentry tag set in both branches.
- [x] 2. Add `App\Support\Caching\CacheStoreGuard::ensureTaggable()` and call it from `AppServiceProvider::boot()` in non-testing envs. Unit test: guard throws when `cache.default` resolves to a non-`TaggableStore` (use `Config::set` + the `database` driver to assert the throw message names the offending driver).
- [x] 3. Add `mybible:cache-clear-tag {tag} {--dry-run}` artisan command that calls `Cache::tags([$tag])->flush()`. Unit test: `--dry-run` emits the would-flush count without flushing; real run flushes the matching tag and leaves siblings intact.
- [x] 4. Update `config/cache.php` to add `flexible_grace_seconds` (env `CACHE_FLEXIBLE_GRACE`, default 60); change the `prefix` default fallback string to `mybible-api`. Update `.env.example` with `CACHE_PREFIX=mybible-api`, `REDIS_USERNAME`, `REDIS_SCHEME`, `REDIS_CACHE_DB`. Add `scheme` to `config/database.php` redis `cache` connection block (env `REDIS_SCHEME`, default `tcp`).
- [x] 5. Replace the framework `/up` health route with `App\Http\Controllers\HealthCheckController` that pings `Redis::connection('cache')` and `DB::select('SELECT 1')`. Feature test: 200 when both healthy; 503 + `services` array when Redis is unreachable (use a bogus connection in a `Config::set` setup).
- [x] 6. Add `Tests\Feature\Cache\CacheConnectionTest` (integration) — boots the app against the real `mybible-redis-test` instance and asserts a round-trip set+get on the `cache` connection. Per AC 14, must run as part of `make test-api` (`mybible-redis-test` is already up).
- [x] 7. Create `App\Domain\SabbathSchool\Support\SabbathSchoolCacheKeys`: `lessonsList(Language, int $page, int $perPage): string`, `lesson(int $id, Language): string`, `tagsForLessonsList(): array`, `tagsForLesson(int $id): array`. Unit test: returns documented strings for sample inputs.
- [x] 8. Create `App\Domain\SabbathSchool\Actions\ListSabbathSchoolLessonsAction::execute(Language, int $page, int $perPage): array`. The build closure runs the existing query (`SabbathSchoolLesson::query()->published()->forLanguage()->orderByDesc('published_at')->orderByDesc('id')->paginate($perPage, page: $page)`) and returns `SabbathSchoolLessonSummaryResource::collection($paginator)->response(request())->getData(true)`. Unit test: cache miss runs the query (assert via `DB::enableQueryLog`); hit returns identical array without DB hits; tag flush forces re-miss.
- [x] 9. Refactor `ListSabbathSchoolLessonsController` to call the new Action and return `response()->json($action->execute(...))->header('Cache-Control', 'public, max-age=3600')`. Update the existing feature test file with `it_caches_the_response`, `it_serves_a_cache_hit_without_db_queries`, `it_invalidates_the_lessons_list_tag` assertions.
- [x] 10. Create `App\Domain\SabbathSchool\Actions\ShowSabbathSchoolLessonAction::execute(int $lessonId, Language): array`. Build closure runs `SabbathSchoolLesson::query()->published()->withLessonDetail()->findOrFail($lessonId)` and resolves `SabbathSchoolLessonResource`. Refactor `ShowSabbathSchoolLessonController` from implicit binding to taking `int` from the route param; return JSON via `response()->json()`. Update the existing feature test for cache miss/hit/invalidate; reassert that 404 still renders for an unpublished lesson and the 404 path is **not** cached (cache write must only happen on successful resolve).
- [x] 11. Create `App\Domain\Devotional\Support\DevotionalCacheKeys` (`show(...)`, `tagsForDevotional($lang, $type)`); modify `FetchDevotionalAction::execute()` to wrap the existing `firstOrFail()` query with `CachedRead`. Unit test on the Action: hit/miss/invalidate. Update `ShowDevotionalTest` with cache assertions.
- [x] 12. Create `App\Domain\Verses\Support\VersesCacheKeys` (`dailyVerse($date)`, `tagsForDailyVerse()`); modify `GetDailyVerseAction::handle()` to cache the lookup. Unit test on the Action; update `GetDailyVerseTest`. The existing `ResolveVersesAction` static-array memo stays untouched (per story technical notes).
- [x] 13. Create `App\Domain\News\Support\NewsCacheKeys` and `ListNewsAction::execute(Language, int $page, int $perPage): array`. Refactor `ListNewsController` to use it. Update `ListNewsTest`.
- [x] 14. Create `App\Domain\Bible\Support\BibleCacheKeys` and `App\Domain\Bible\Support\BibleExportBuilder::build(BibleVersion): string` (extracted from `BibleVersionExporter::stream`'s emit logic; the streamed variant becomes a thin wrapper that yields the string). Unit test on the builder: byte-equal output to the previous streamed body for a small fixture version.
- [x] 15. Create `App\Domain\Bible\Actions\ListBibleVersionsAction::execute(?Language, int $page, int $perPage): array` (cache wraps the existing list query + `BibleVersionResource::collection`). Refactor `ListBibleVersionsController` to call it; preserve the existing `BibleCacheHeaders::forVersionList(...)` ETag logic and the `If-None-Match` short-circuit (cache layer sits between the controller and the query, so `getData(true)` of a cached response is what gets re-rendered on a fresh request). Update the existing feature test.
- [x] 16. Create `App\Domain\Bible\Actions\ExportBibleVersionAction::execute(BibleVersion): string` (cached). Refactor `ExportBibleVersionController` to emit the cached string body (via `response($string)->withHeaders([..., 'Content-Type' => 'application/json'])`) instead of the streamed response. Preserve the ETag short-circuit. Update `ExportBibleVersionTest`: cache miss hits MySQL, hit serves identical bytes without query, manual flush forces re-miss.
- [x] 17. Create `App\Domain\EducationalResources\Support\EducationalResourcesCacheKeys`, `ListResourceCategoriesAction`, `ListResourcesByCategoryAction`. Refactor both controllers. Update both feature tests.
- [x] 18. Create `App\Domain\Collections\Support\CollectionsCacheKeys`, `ListCollectionTopicsAction`, `ShowCollectionTopicAction` (the latter wraps the existing `ResolveCollectionReferencesAction` call inside the build closure — references resolution happens once per cache miss, not per request). Refactor both controllers. Update both feature tests.
- [x] 19. Create `App\Domain\Olympiad\Support\OlympiadCacheKeys`. Modify `ListOlympiadThemesAction::execute()` to cache the paginator-resolved array. Modify `FetchOlympiadThemeQuestionsAction::execute()` to extract a `loadThemeQuestions(OlympiadThemeRequest): Collection` private step that is cached, then run the seeded shuffle in the public `execute()` outside the cache. Unit tests on both Actions; update `ShowOlympiadThemeTest` to assert that two requests with different `?seed=` values both hit cache and produce different question orderings.
- [x] 20. Create `App\Domain\QrCode\Support\QrCodeCacheKeys` and `ShowQrCodeAction::execute(string $canonicalReference): array`. Refactor `ShowQrCodeController` to pass the canonical string into the Action. Update `ShowQrCodeTest`.
- [x] 21. Add `docs/runbook/cache.md` documenting: hit-rate target ≥ 90% for Bible/SS/devotional reads after warm-up; how to read `cache.miss` log lines; `cache_status` Sentry tag; `mybible:cache-clear-tag` examples; eviction policy reminder for `bible:export:*`.
- [x] 22. Run `make lint-fix`, `make stan`, then `make test-api filter='Cache|SabbathSchool|Devotional|DailyVerse|News|Bible|EducationalResource|Collection|Olympiad|QrCode'` to exercise the changed surfaces; finally run `make check` from the monorepo root before marking the story ready for review.

## Risks & notes

1. **Optional split if a single PR is too large.** Three slices, each a green-CI ship: **(a)** infra + 1 pilot endpoint (tasks 1–6, 7–9, 21 — `CachedRead`, `CacheStoreGuard`, artisan command, `/up` ping, integration test, runbook draft, SS list/show as the pilot); **(b)** existing-Action endpoints (tasks 11, 12, 19 — devotional, daily-verse, olympiad); **(c)** new-Action endpoints (tasks 13, 15, 16, 17, 18, 20 — news, bible versions/export, edu, collections, qr-codes). Each slice is independently deployable; (a) gives a real hit-rate signal before the fanout.
2. **Streaming → buffering for Bible export.** Largest payload (~tens of MB per version × ~10 versions). Buffering eliminates streaming's memory headroom on cold misses but allows caching. Eviction policy `allkeys_lru` will evict these first under pressure; that is the safety net documented in the story. If memory ceiling on `mybible-valkey` becomes a concern, the follow-up is to drop `bible:export:*` from app cache and lean entirely on Cloudflare ETag — that is a config change in the Action, not a structural revisit.
3. **Cache the resolved Resource array vs the model.** Trade-off resolved per Open question 8. Hit-path skips ORM hydration but the cache value is coupled to the Resource shape — any change to a Resource means a tag flush on deploy. Mitigation: deploy hook calls `mybible:cache-clear-tag` for any Resource that changed in the diff (the runbook lists the mapping).
4. **Implicit-binding → explicit-id refactor on SS lesson show.** The implicit `SabbathSchoolLesson $lesson` binding runs the published+detail query *before* the Action, defeating the cache. Switching to an int param is a small but visible controller-shape change. Project guidelines §5b allow this when the bound model has a scope (`published()`); the 404 path stays JSON via the standard exception handler. No equivalent change needed elsewhere because no other cached endpoint uses route-model binding to a *scoped* model whose lookup we want to skip on hit.
5. **`Cache::flexible` lock contention on cold-start.** The internal lock serialises rebuilds — at 09:00 Saturday when the SS lesson page warms, one request rebuilds and the rest get either a (briefly) stale value or wait briefly for the lock. The 60s grace window means a stampede degenerates to one MySQL query, not N. If the rebuild itself exceeds ~5s, lock-waiters time out and re-acquire — verify the SS lesson detail rebuild stays under 1s on prod-shaped data (existing `withLessonDetail` already eager-loads to one query).
6. **Sentry tag cardinality.** `cache_status: hit|miss` is binary; combined with route-name tagging (Sentry default), the dimensional grid is 28 cells (14 routes × 2). Safe. Avoid extending the tag with the cache key — that would balloon cardinality.
7. **No invalidation hooks wired today.** Verified by reading every Action under `app/Domain/*/Actions/` — every existing write Action is per-user (favorites, notes, profile, reading-plan-subscriptions, answers, highlights). Public-content writes don't exist as Actions yet; the cutover (MBA-020) is the upstream writer. The `tagsFor*()` helpers and the `mybible:cache-clear-tag` command are the integration surface for the future admin/import story. Unit-testing them now ensures they fire correctly when wired.
8. **Bible export streaming behaviour change.** Switching from `StreamedResponse` to a buffered string response changes the byte-for-byte HTTP behaviour: `Transfer-Encoding: chunked` may disappear in favour of `Content-Length`. This is observable but not breaking — Cloudflare and mobile clients accept either. Note in the runbook so an ops-team grep doesn't flag it as a regression.
9. **Deferred Extractions register.** No tripwire entry added or modified by this story. The cache-call pattern lives uniformly in `CachedRead`, so each Action calls a single helper — that *is* the extraction. No further deduplication threshold to track.
10. **Cache-warming follow-up.** If hit-rate after one peak Saturday is below 90% (per AC 16), the next story is `MBA-022-cache-warm` (proposed name): an artisan command primes top-N SS lessons + bible-versions + current devotional at deploy time. Cron-driven prewarming is the second-tier follow-up if deploy-time warming is insufficient.
