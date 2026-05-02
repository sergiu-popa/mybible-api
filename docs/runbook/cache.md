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
| `app:bootstrap` | Mobile bootstrap aggregator (MBA-022) |

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

## Bootstrap aggregator (MBA-022)

The mobile cold-start aggregator at `GET /api/v1/app/bootstrap` returns
the union of `version`, `daily_verse`, `news`, `bible_versions`,
`devotionals_today`, `sabbath_school_current_lesson`, and `qr_codes` in
one round-trip.

| Property | Value |
|---|---|
| Cache key | `app:bootstrap:{language}` (one entry per `Language::cases()`) |
| TTL | `BOOTSTRAP_CACHE_TTL` env (default `300s`) |
| Tag union | `app:bootstrap`, `news`, `daily-verse`, `dev`, `ss`, `ss:lessons`, `bible`, `bible:versions`, `qr` |

Any constituent flush (e.g. publishing news, upserting the daily verse,
republishing an SS lesson) busts the bootstrap key automatically because
the bootstrap entry is tagged with the union above. To clear the
aggregator on its own without touching constituents:

```bash
docker exec mybible-api php artisan mybible:cache-clear-tag app:bootstrap
```

Cold-miss cost is ~9 query bursts in one HTTP request (one per
constituent Action's build closure). Each constituent is itself cached,
so most run as Redis hits even on a bootstrap miss. Stampede is bounded
by `Cache::flexible`'s atomic lock per key.

## Health checks: liveness vs readiness (MBA-022)

`/up` and `/ready` are now distinct probes. **The deploy/LB readiness
probe MUST point at `/ready`** — leaving it on `/up` after this story
ships means the LB no longer detects DB or Redis outages.

| Endpoint | Purpose | Auth | Pings |
|---|---|---|---|
| `GET /up` | Liveness — process alive | none | none (always 200) |
| `GET /ready` | Readiness — deps responding | `internal-ops` middleware (VPC CIDR allow-list, `INTERNAL_OPS_CIDR` env, default `10.114.0.0/20`) | DB `SELECT 1` + Redis round-trip, total budget 1 s |

`/ready` returns `200 {status:'ready'}` when both deps respond inside
the budget; `503 {status:'unready', dependency:'<first-failing>'}`
otherwise. Local dev needs `INTERNAL_OPS_CIDR=127.0.0.1/32,172.16.0.0/12`
in `.env` to reach `/ready` from the host.

## Per-IP and per-user rate limits (MBA-022)

| Limiter | Key | Limit | Applied to |
|---|---|---|---|
| `public-anon` | `request->ip()` | 180/min | Public read groups in `routes/api.php` |
| `per-user` | `auth()->id()` (falls back to IP) | 300/min | Authenticated route groups |

Excluded: `/up`, `/ready`, admin routes. 429 responses include
`Retry-After` and the standard `X-RateLimit-Limit` /
`X-RateLimit-Remaining` headers. Behind the LB, set `TRUSTED_PROXIES`
to the LB CIDR so `request->ip()` resolves to the real client via
`X-Forwarded-For` (default `'*'` is fine for Docker dev only).

## Slow-query log channel (MBA-022)

Queries slower than **500 ms** are written to a dedicated daily-rolled
log channel and emit a Sentry breadcrumb on the active transaction.

| Property | Value |
|---|---|
| Log file | `storage/logs/slow_query.log` (rotated daily) |
| Retention | 14 days |
| Level | `warning` |
| Disabled in | `local`, `testing` envs |

Tail it during incident triage:

```bash
docker exec mybible-api tail -f storage/logs/slow_query.log
```

## Stream → buffer change for Bible export

Before MBA-021: `GET /bible-versions/{v}/export` returned a
`StreamedResponse` (chunked transfer encoding). After MBA-021: a
buffered string, so the payload can be cached. The HTTP-level change is
observable (`Content-Length` may now be present where
`Transfer-Encoding: chunked` was) but is not breaking — Cloudflare and
mobile clients accept either.
