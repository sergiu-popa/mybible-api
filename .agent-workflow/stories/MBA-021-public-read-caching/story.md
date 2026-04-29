# Story: MBA-021-public-read-caching

## Title
Application-level caching (Valkey) for public read-heavy endpoints

## Status
`in-review`

## Description

Today the API uses **only HTTP caching directives** (`Cache-Control: public,
max-age=3600`) on public read endpoints. The `cache.default` is configured
as `redis` (`CACHE_STORE=redis` → managed Valkey cluster `mybible-valkey` in
`fra1`), but no domain code calls `Cache::remember()` or any other Cache
facade method. Result: every request reaches MySQL even for content that is
identical for every anonymous user and changes hourly at most.

This is the dominant load source on `mybible-mysql` (`db-s-1vcpu-1gb`) on
peak mornings. The 2026-04-25 incident — captured in
`mybible.eu/docs/2026-04-25-incident-oom-overload.md` — shows the pattern:
~110 req/s on Saturday morning, 1 vCPU saturated to load avg 19, OOM cascade
on the application droplets. Bible/SS/Devotional content listings drove most
of the traffic, all of which is identical across users in a given hour.

The legacy Symfony service (`mybible.eu/src/Service/SabbathSchool.php`)
already caches `thisTrimester()` and `getLesson()` JSON in Valkey for 60
minutes; this story brings the same idea to the new API with Laravel's
Cache facade and tag-based invalidation, and **extends** it to the
endpoints Symfony left uncached.

## Acceptance Criteria

### Cache layer wiring

1. Production `cache.default` resolves to the `redis` store backed by the
   managed Valkey cluster (env: `CACHE_STORE=redis`, `REDIS_HOST` /
   `REDIS_PORT` / `REDIS_PASSWORD` from secrets).
2. A dedicated cache prefix `mybible-api:` is set so keys do not collide
   with any session/queue keys that may share the connection.
3. Cache misses log a single structured line at `info` level with the cache
   key (no payload) — to confirm hit-rate during the rollout.
4. Health check endpoint (`/up`) confirms the Valkey connection responds.

### Cached endpoints (read-only, anonymous-safe)

For each endpoint below, the controller wraps the existing query with
`Cache::tags([...])->remember($key, $ttl, fn() => ...)`. The TTL and tag
list per endpoint:

| Endpoint | Key shape | TTL | Tags |
|---|---|---|---|
| `GET /api/v1/sabbath-school/lessons` | `ss:lessons:list:{lang}:p{page}:{perPage}` | 3600 | `ss`, `ss:lessons` |
| `GET /api/v1/sabbath-school/lessons/{lesson}` | `ss:lesson:{id}:{lang}` | 3600 | `ss`, `ss:lesson:{id}` |
| `GET /api/v1/devotionals/{date}/{type}` | `dev:{lang}:{type}:{date}` | 3600 | `dev`, `dev:{lang}:{type}` |
| `GET /api/v1/daily-verse` | `verses:daily:{date}` | 1800 | `verses`, `daily-verse` |
| `GET /api/v1/news` | `news:{lang}:p{page}` | 600 | `news` |
| `GET /api/v1/bible/versions` | `bible:versions:list` | 86400 | `bible`, `bible:versions` |
| `GET /api/v1/bible/versions/{version}/export` | `bible:export:{version}` | 86400 | `bible`, `bible:export:{version}` |
| `GET /api/v1/educational-resources/categories` | `edu:categories:{lang}` | 3600 | `edu` |
| `GET /api/v1/educational-resources/categories/{cat}` | `edu:cat:{id}:p{page}` | 3600 | `edu`, `edu:cat:{id}` |
| `GET /api/v1/collections/topics` | `col:topics:{lang}` | 3600 | `col` |
| `GET /api/v1/collections/topics/{topic}` | `col:topic:{id}:{lang}` | 3600 | `col`, `col:topic:{id}` |
| `GET /api/v1/olympiad/themes` | `oly:themes:{lang}` | 3600 | `oly` |
| `GET /api/v1/olympiad/themes/{theme}` | `oly:theme:{id}` | 3600 | `oly`, `oly:theme:{id}` |
| `GET /api/v1/qr-codes/{reference}` | `qr:{reference}` | 86400 | `qr` |

5. Cached responses keep their existing `Cache-Control` header — the
   intermediate cache (Cloudflare / DO LB) layer is unaffected.
6. Authenticated user state never enters a cached payload. Endpoints that
   embed per-user state (e.g. "is this lesson favorited?") **are not in
   scope** — they remain uncached, OR the per-user portion is layered on
   after the cache read.

### Authenticated / per-user endpoints

7. The following endpoints **MUST NOT** be cached at the response level:
   - `GET /api/v1/sabbath-school/highlights`, favorites, answers
   - `GET /api/v1/notes`, `GET /api/v1/favorites`
   - `GET /api/v1/devotional-favorites`, hymnal favorites
   - Any `POST/PATCH/DELETE` route
8. Per-user lookups MAY use a short request-scope memo (`array` cache
   driver) where helpful, but no shared cache.

### Invalidation

9. Each write path that mutates a cached entity invalidates the affected
   tag. Exhaustive map:
   - Lesson admin update → `Cache::tags(['ss:lesson:{id}'])->flush()`
     (and `['ss:lessons']` if `published_at` or `language` changed).
   - Devotional admin update → `Cache::tags(['dev:{lang}:{type}'])->flush()`.
   - Daily verse seed/upsert → `Cache::tags(['daily-verse'])->flush()`.
   - News publish/update → `Cache::tags(['news'])->flush()`.
   - Educational resource publish/update → `Cache::tags(['edu:cat:{id}'])->flush()`.
   - Collection topic update → `Cache::tags(['col:topic:{id}'])->flush()`.
   - Olympiad theme update → `Cache::tags(['oly:theme:{id}'])->flush()`.
   - QR code admin upsert → `Cache::tags(['qr'])->flush()`.
   - Bible version add/remove → `Cache::tags(['bible:versions'])->flush()`.
10. There is no admin UI in this API today — invalidation hooks live in
    Domain Actions called by future admin/import jobs and by the existing
    Symfony cutover (MBA-020). For now: a `php artisan cache:clear-tag {tag}`
    Artisan command exists for manual flushing (deploy hook + ops use).

### Stampede protection

11. Cache reads use Laravel's atomic lock helpers (`Cache::lock` or
    `flexible` cache pattern via `Cache::flexible($key, [$stale, $fresh],
    fn() => ...)` if the version supports it) so that a cold miss on a hot
    key (e.g. SS lesson at 09:00 Saturday) cannot stampede MySQL with N
    parallel rebuilds.

### Tests

12. **Unit**: a `CachedRepository` (or per-Action) helper has tests for
    cache hit / cache miss / stampede / tag flush.
13. **Feature** per cached endpoint:
    - First request → cache miss (assert log line).
    - Second request → cache hit (assert no DB query via
      `DB::enableQueryLog()` + count).
    - After a write that should invalidate → next read is a miss again.
14. **Integration** — `CacheConnectionTest` boots the app against a real
    Redis instance (CI service container) and asserts a round-trip set+get.

### Observability

15. A counter (or simple log) emits `cache.hit` / `cache.miss` per cached
    key prefix; the existing Sentry integration tags transactions with
    `cache_status: hit|miss` so we can see hit-rate per route in Sentry
    Performance.
16. Document the expected hit-rate target in the runbook (`docs/`):
    >= 90% for Bible/SS/devotional public reads after warm-up.

## Scope

### In Scope
- Wiring the existing `redis` cache store to the managed Valkey cluster.
- Adding `Cache::tags(...)->remember(...)` to the 14 endpoints listed.
- Invalidation hooks in the relevant Domain Actions (write side).
- Stampede protection on hot keys.
- A small `CacheKeys` value object (or per-domain const class) so keys live
  in one place — never hard-coded in controllers.
- Tests + observability.

### Out of Scope
- Per-user response caching (auth caching, ETag-per-user, etc.) — separate
  story if needed.
- Edge / Cloudflare cache configuration (network-side, infra repo).
- FrankenPHP / Octane migration (separate pilot).
- Replacing `Cache-Control` headers — they stay as-is.
- Bible verse search caching — search is not in this API yet (planned for
  Meilisearch in a future story).
- Pre-warming / schedule-driven cache hydration. If hit-rate falls short
  after observability lands, a follow-up story can add `php artisan
  cache:warm` invoked by a cron.

## Technical Notes

### Where the Cache::remember calls live

Per `CLAUDE.md` ("Controllers do not contain business logic"), the cache
layer belongs in the **Domain Action** the controller already calls — not
in the controller itself. Pattern:

```php
final class ShowSabbathSchoolLesson
{
    public function __construct(private readonly SabbathSchoolLessonRepository $repo) {}

    public function execute(int $lessonId, Language $language): SabbathSchoolLessonData
    {
        return Cache::tags(['ss', "ss:lesson:{$lessonId}"])->remember(
            CacheKeys::ssLesson($lessonId, $language),
            now()->addHour(),
            fn () => $this->repo->findDetailOrFail($lessonId, $language),
        );
    }
}
```

### Tag support

`tags()` requires the `redis` (or `memcached`) store. Document a deploy
guard: a boot check that fails fast if `cache.default` does not support
tags, so a misconfigured environment fails at startup rather than at first
write-side invalidation.

### Serialization

Eloquent collections serialize fine through Laravel's default cache
serializer, but **API Resource arrays** are preferable as the cached
payload (smaller, no ORM hydration on cache hit). Cache the array returned
by `Resource::collection($items)->resolve($request)` rather than the
Eloquent collection.

### Connection

Production env vars (set via DO managed cluster connection details):
- `REDIS_HOST` — `mybible-valkey-...-fra1.k.db.ondigitalocean.com`
- `REDIS_PORT` — `25061`
- `REDIS_USERNAME` — `default`
- `REDIS_PASSWORD` — from secrets manager
- `REDIS_SCHEME` — `tls`

Confirm `phpredis` build supports TLS in the base image used by the
production droplets / FrankenPHP image.

### Eviction

`mybible-valkey` is provisioned with `eviction_policy = allkeys_lru`
(`mybible-terraform/database.tf:23`). Hot keys stay; cold/large export
payloads (`bible:export:*`) get evicted first if memory pressures build.
Keep `bible:export:*` TTL high but acknowledge eviction as the safety net.

### Known existing cache code to reconcile

- `App\Domain\Verses\Actions\ResolveVersesAction` already uses a static
  `$cache = []` for chapter-verse-count lookups (request scope). Leave
  as-is; that pattern is correct for hot-loop request memoization and not
  what this story replaces.
- `App\Domain\Bible\Support\BibleCacheHeaders` only emits HTTP headers; it
  does not store anything. Untouched by this story.

## Dependencies
- **MBA-005** (auth + DB foundations).
- **MBA-007 .. MBA-019** (the routes that get caching applied).
- **Infrastructure**: Valkey cluster reachable from the API droplets — the
  Terraform firewall (`mybible-terraform/database.tf:49`) already allows
  the `mybible` tag.

## Open Questions for Architect
1. **`Cache::flexible` vs lock+remember**. Laravel 13 ships `flexible`
   for stale-while-revalidate. Confirm version + behavior and pick one.
2. **Per-language fanout**. Some keys include `{lang}`. For `daily-verse`
   the existing endpoint resolves a single language — verify the route
   signature before fixing the key shape.
3. **Tag flush vs explicit forget**. `Cache::tags(...)->flush()` evicts
   *all* keys in that tag. For cases where we only need to invalidate
   `ss:lesson:{id}`, a per-tag flush is fine. For coarser tags (`ss`)
   weigh the cost of mass eviction during a daily import job — the
   Architect should specify per route.
4. **Sentry hit-rate metric vs StatsD**. We have Sentry today; Prometheus
   will land in a separate observability story. Confirm Sentry custom
   metrics are sufficient for the rollout signal.
5. **Cache-warming on deploy**. Out of scope here, but flag in the plan
   whether a follow-up `cache:warm` Artisan command (priming the top-N
   SS lessons + bible-versions list at deploy time) should be the next
   story.

## Notes for Engineer
- Do not introduce a new `Cacheable` middleware. The cache call lives in
  the Action because the cache key depends on resolved DTO inputs, not
  raw URL.
- Resist the urge to cache per-controller. Cache lives in the Action for
  reuse and for testing without HTTP plumbing.
- Add the `CacheConnectionTest` first: catching a misconfigured Valkey
  endpoint on CI is far cheaper than catching it after deploy.
