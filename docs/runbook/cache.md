# Cache runbook

This document describes the application-level cache that wraps the public
read-heavy endpoints (Sabbath School, Devotional, Daily Verse, News, Bible
versions / export, Educational Resources, Collections, Olympiad themes,
QR codes). Story: **MBA-021**.

## Topology

- **Backend:** managed Valkey cluster `mybible-valkey` (DigitalOcean,
  `fra1`), `eviction_policy = allkeys_lru`.
- **Driver:** `CACHE_STORE=redis` → `redis` cache store, `cache` Redis
  connection. Set via env in production; default is `redis`.
- **Prefix:** every key is namespaced with `cache.prefix` =
  `mybible-api`, so the cache connection can be shared with sessions /
  queue without collisions.
- **Stale-while-revalidate:** all cached reads use
  `Cache::tags(...)->flexible($key, [$ttl, $ttl + grace], $build)`. The
  grace window (`CACHE_FLEXIBLE_GRACE`, default `60s`) lets one warm
  request rebuild while the rest serve a slightly stale value, behind an
  atomic lock. A cold miss on a hot key cannot stampede MySQL.

## Hit-rate target

After a single peak warm-up window, expect:

| Family | Target |
|---|---|
| Bible / Sabbath School / Devotional reads | **≥ 90 %** |
| Other public reads (News, Olympiad, QR, Collections, Educational) | ≥ 80 % |

If the Bible/SS/Devotional families fall below 90 % for two consecutive
peaks, queue the deploy-time cache-warming follow-up (story
`MBA-022-cache-warm`).

## Reading hit-rate

Two signals are emitted per cached read:

- **Structured log line** at `info` level: `cache.miss` with a `key`
  field (no payload). Misses only — hits are silent.
- **Sentry transaction tag** `cache_status: hit | miss`. Group by route
  in Sentry Performance to break out the rate per endpoint. Cardinality
  is bounded (14 routes × 2 states = 28 cells).

### Quick local check

```bash
docker exec mybible-api php artisan tinker --execute \
  "Cache::store('redis')->getStore()->getRedis()->info('keyspace');"
```

## Invalidation

Each domain owns a `tagsFor*()` helper in
`App\\Domain\\<X>\\Support\\<X>CacheKeys`. The future write Actions (admin
update, import jobs) MUST call `Cache::tags(<helper>)->flush()` after
mutating the underlying entity.

| Tag | Owners |
|---|---|
| `ss:lessons`, `ss:lesson:{id}` | SS lesson updates |
| `dev:{lang}:{type}` | Devotional imports |
| `daily-verse` | Daily-verse seed/upsert |
| `news` | News publish |
| `bible:versions`, `bible:export:{abbrev}` | Bible version add/remove |
| `edu:cat:{id}` | Educational resource publish |
| `col:topic:{id}` | Collection topic update |
| `oly:theme:{book}:{from}-{to}:{lang}` | Olympiad theme imports |
| `qr` | QR code upserts |

### Manual flush (ops + deploy hook)

```bash
docker exec mybible-api php artisan mybible:cache-clear-tag bible:versions
# Dry-run first when uncertain:
docker exec mybible-api php artisan mybible:cache-clear-tag ss:lessons --dry-run
```

The artisan command is the right tool when:

1. Deploying a Resource shape change — flush the relevant tag(s).
2. Manually fixing bad data after a hot-fix.
3. Operations escalation: clear a specific entity's cache without
   restarting the cluster.

## Eviction policy

`allkeys_lru` evicts least-recently-used keys when the cluster nears
its memory ceiling. Bible export payloads (`bible:export:*`) are the
biggest entries (multi-MB per version × ~10 versions). Under memory
pressure they are the first to go. This is acceptable: Cloudflare
absorbs most repeat traffic via the existing ETag, and the application
cache is the second tier.

If the cluster sustained eviction rate exceeds a few keys/min, the
follow-up is to drop `bible:export:*` from the application cache and
serve exclusively through Cloudflare ETag.

## Diagnosing a cold cache

Symptoms: spike in `cache.miss` log lines, MySQL CPU climbing, latency
on read endpoints.

1. Confirm Redis is healthy:
   ```bash
   curl -s http://api.mybible.local/up | jq .
   ```
   Expect `{"ok": true, "services": {"redis": true, "db": true}}`.
2. Check for an unrelated config change that might have flipped
   `CACHE_STORE` away from `redis`. The `CacheStoreGuard` boot check
   refuses to start the app on a non-tag-capable driver, so a
   bad config would surface as a deploy failure rather than a silent
   no-op.
3. Check the `cache.miss` log line rate. Steady-state should be a few
   per minute (one per page warm-up). Sustained high rate → either a
   tag was accidentally flushed, or an upstream import is invalidating
   too coarsely.

## Stream → buffer change for Bible export

Before MBA-021: `GET /bible-versions/{v}/export` returned a
`StreamedResponse` (chunked transfer encoding). After MBA-021: a
buffered string, so the payload can be cached. The HTTP-level change is
observable (`Content-Length` may now be present where
`Transfer-Encoding: chunked` was) but is not breaking — Cloudflare and
mobile clients accept either.
