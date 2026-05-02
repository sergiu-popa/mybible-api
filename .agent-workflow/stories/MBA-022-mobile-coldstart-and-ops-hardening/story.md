# Story: MBA-022-mobile-coldstart-and-ops-hardening

## Title
Mobile cold-start optimization + operational hardening

## Status
`draft`

## Description

Findings from the 2026-05-01 → 2026-05-02 Caddy access-log analysis
(`mybible.eu/docs/2026-05-02-caddy-log-analysis.html`) of the legacy
Symfony app surfaced a handful of patterns that the new Laravel API
should not inherit. The same mobile clients (iOS + Android, dominated
by `okhttp/5.3.2` and `MyBible/CFNetwork/*`) will eventually point at
this API, and the access-log shapes shown there carry over verbatim:

- A single mobile cold-start fans out to ~10 sequential requests
  (`/version`, `/bible`, `/news`, `/daily-verse`,
  `/devotional/ro/{type}`×5, `/sabbath-school/{lessonId}`,
  `/hymnal/books`, `/hymnal/version`, …). When a client crash-loops,
  this fan-out happens dozens of times per minute per user. Observed
  on IP `86.122.113.93`: 70 cold-starts in 30 minutes.
- Per-user reads come in tightly grouped: `/api/favorite`,
  `/api/favorite/category`, `/api/note`, `/api/sabbath-school/{id}/answers`.
  Observed on IP `79.116.162.133`: 226 hits in 30 minutes.
- 901 × HTTP 500 from a one-hour window concentrated entirely on
  cached endpoints because a code/container mismatch turned the
  shared cache into a poison source. Single ops mistake → site-wide
  500 spike.
- Several rate-limit-shaped patterns (one IP making >70 of one
  endpoint in 30 min) that we silently absorbed; with a runaway
  client a single user can hammer the whole fleet.
- Health-check on `/up` already wires to DB + Redis. Under steady
  state at 2 droplets × 6 health-checks/min = 720 DB pings/h that
  are not application traffic. At 20 droplets × peak that's 7200/h.

This story addresses these together: a cold-start bootstrap
endpoint to collapse the fan-out, a delta-sync endpoint to
collapse per-user reads, rate limits, granular health checks,
pagination defaults, and observability hooks.

Search is **not** in this story — it lives in `MB-014-search-via-meilisearch`
on the frontend side and bypasses the API entirely.

## Acceptance Criteria

### A. Cold-start bootstrap endpoint

1. `GET /api/v1/app/bootstrap?language={iso2}` returns in one round-trip
   the data that mobile clients fetch one-by-one on cold start. Suggested
   shape:
   ```json
   {
     "data": {
       "version": { "ios": "92", "android": "739", "olympiad": "9", "hymnal": "21" },
       "languages_available": ["ro", "hu", "en"],
       "daily_verse": { "for_date": "2026-05-02", "reference": "JHN.3:16", "image_cdn_url": "..." },
       "news": [ /* up to 7 items */ ],
       "bible_versions": [ /* { id, abbreviation, name, language } per version */ ],
       "devotionals_today": {
         "adults": { "for_date": "2026-05-02", "title": "...", "type_id": 1 },
         "youth":   { /*...*/ }
       },
       "sabbath_school_current_lesson": {
         "id": 1641, "title": "...", "language": "ro", "week_start": "2026-04-26", "week_end": "2026-05-02"
       },
       "qr_codes": [ /* canonical reference → URL pairs, 198 items */ ]
     }
   }
   ```
2. Response cached at app-level (`Cache::flexible('app:bootstrap:{lang}',
   [60, 300], …)`), tagged so daily-verse/news/devotional/SS publish events
   bust the bootstrap key. TTL ≤ 5 min so a same-day correction propagates
   quickly.
3. Public, anonymous-safe (no per-user state). `Cache-Control: public,
   max-age=300` so a future Cloudflare layer caches at edge.
4. Auth-required follow-up (favorites/notes/answers) is **not** in this
   payload — those go through `GET /api/v1/sync` (see B).
5. Backwards compat: the individual endpoints (`/news`, `/daily-verse`,
   `/version`, `/bible/versions`, `/devotionals/{date}/{type}`,
   `/sabbath-school/lessons/{lesson}`) **remain** so old app builds keep
   working. The bootstrap endpoint is opt-in via a new app version.
6. Tests:
   - Feature test asserts the response shape (all top-level keys present).
   - Cache-hit feature test asserts second request issues 0 DB queries.
   - Tag-flush test asserts a daily-verse upsert busts `app:bootstrap:*`.

### B. Sync delta endpoint (authenticated)

7. `GET /api/v1/sync?since={iso8601_timestamp}` returns the caller's
   favorites + notes + sabbath_school_answers + sabbath_school_highlights
   + sabbath_school_favorites + devotional_favorites + hymnal_favorites
   that were inserted/updated/deleted **after** `since`. Default `since`
   = epoch (full sync) — clients persist the server-returned `synced_at`
   and use it next time.
8. Response shape:
   ```json
   {
     "data": {
       "synced_at": "2026-05-02T08:42:13Z",
       "favorites":            { "upserted": [...], "deleted": [12, 34] },
       "notes":                { "upserted": [...], "deleted": [] },
       "sabbath_school_answers":     { "upserted": [...], "deleted": [...] },
       "sabbath_school_highlights":  { "upserted": [...], "deleted": [...] },
       "sabbath_school_favorites":   { "upserted": [...], "deleted": [...] },
       "devotional_favorites":       { "upserted": [...], "deleted": [...] },
       "hymnal_favorites":           { "upserted": [...], "deleted": [...] }
     }
   }
   ```
9. To support `deleted: [...]`, switch the affected models to soft delete
   (Eloquent `SoftDeletes`) or maintain a `tombstones` table per model.
   Pick one approach across all models in scope; document the choice in
   `plan.md`. Existing migrations need to add `deleted_at TIMESTAMP NULL`
   and an index on `(user_id, deleted_at)` and `(user_id, updated_at)`.
10. Sanctum auth required. The query never accepts a `user_id` from the
    request — always `auth()->id()`.
11. Cap response size: hard-limit 5 000 rows per type; if a user has more
    than that, return `next_since` cursor that the client uses for the
    next call.
12. Tests:
    - Feature: full sync returns all data; subsequent sync with `since`
      returns only deltas.
    - Soft-delete propagation: deleting a favorite shows up under `deleted`
      in the next sync.
    - Auth: cross-user request returns 0 rows for the other user's data.

### C. Per-IP rate limiting

13. Public read endpoints get a `throttle:public-anon` named limiter:
    180 req/min/IP (3 req/s sustained, ~6 burst). Configured in
    `app/Providers/AppServiceProvider.php` via `RateLimiter::for(...)`.
14. Authenticated routes get `throttle:per-user` (auth()->id() key):
    300 req/min.
15. The `/up` health-check is **excluded** from any rate limit (LBs
    must be able to poll without quota).
16. 429 response includes `Retry-After` header and the standard
    `X-RateLimit-Remaining` headers Laravel emits by default.
17. Tests:
    - Hit a public route 200 times in <1 min from a single IP → expect
      200 OK for the first 180, then 429.
    - Same with auth: 350 hits → first 300 ok, then 429.
    - `/up` not affected.

### D. Granular health checks

18. `/up` becomes the **liveness** probe — pure "process alive". No DB,
    no Redis. Returns 200 with `{ "status": "alive", "ts": ... }`. This
    is what the LB polls every 10s.
19. New `/ready` endpoint = readiness probe: pings DB + Redis + writes
    a known key to Redis with TTL=5 round-trip. Returns 200 if all
    deps respond within 1 s, 503 with the offending dependency named
    otherwise.
20. Liveness is unauthenticated (no API key). Readiness is gated by
    `internal-ops` middleware that allows VPC private IPs only — so
    public callers can't probe internals.
21. Tests:
    - `/up` returns 200 even when Redis is down (mock `Cache::store`
      to throw).
    - `/ready` returns 503 with `dependency: redis` when Redis is
      unreachable.

### E. Pagination defaults

22. Every controller currently returning `findAll()`-shaped arrays
    enforces `paginate($perPage)` with `default = 30`, `max = 100`.
    Affected (audit before plan):
    - `ListNewsController`
    - `ListResourceCategoriesController`
    - `GetDailyVerseController` (already date-range, document the cap)
    - `ListBibleVersionsController`
    - `ListSabbathSchoolLessonsController` (already paginated — confirm)
    - `ListOlympiadThemesController`
    - `ListCollectionTopicsController`
    - `ShowQrCodeController` (singular — N/A, but `ListQrCodes` if added)
23. Form Requests validate `per_page` in `[1, 100]` range. Out-of-range
    → 422 with explicit error message.
24. Resource collections include `meta: { current_page, last_page, total,
    per_page }` (Laravel's default pagination meta).

### F. Observability

25. The cache hit/miss counter from MBA-021 already emits structured
    logs and Sentry tags. Extend it to include the route name in the
    Sentry transaction tag (`route_name=api_v1_sabbath_school_show`)
    so we can filter cache hit-rate per route in Sentry.
26. Add a custom log channel `slow_query` writing to a separate file
    (rolled daily, kept 14 days) and a Sentry breadcrumb for any
    Eloquent query >500 ms. Use a `DB::listen()` hook in
    `AppServiceProvider`. Production sample rate 100%; dev disabled.
27. The bootstrap endpoint emits a Sentry transaction tagged
    `cold_start=true` so we can chart cold-start volume directly.

## Scope

### In Scope
- New `GET /api/v1/app/bootstrap` endpoint (Action + Resource + Form Request + tests).
- New `GET /api/v1/sync` endpoint with the cursor + tombstone strategy.
- Migration adding `deleted_at` + indexes to favorites/notes/sb_*/devotional_favorites/hymnal_favorites.
- Soft-delete trait wiring on the affected models.
- `RateLimiter::for('public-anon', …)` and `for('per-user', …)` plus
  `throttle:` middleware on the corresponding route groups in
  `routes/api.php`.
- `/up` (liveness) replacement — keep the route, drop the DB/Redis
  ping. New `/ready` endpoint with `internal-ops` middleware.
- `internal-ops` middleware that allows the VPC private CIDR
  (`10.114.0.0/20`) only.
- Pagination defaults in the controllers listed above + Form Request
  rules.
- Sentry transaction tagging hook (route + cache_status + cold_start).
- Slow-query log channel.

### Out of Scope
- **Search** — bypassed entirely; MB-014 ships frontend → Meilisearch
  direct.
- **HTTP/3 / Brotli** — Caddyfile/FrankenPHP infrastructure change,
  separate ops PR (track in `mybible-terraform` or runbook).
- **Octane / FrankenPHP migration** — separate pilot story.
- **Cross-cloud DB backup** — separate ops decision.
- **Edge cache** (Cloudflare) — track in infra; the headers this
  story emits are CDN-friendly so it just works when the proxy
  lands.

## Technical Notes

### Bootstrap endpoint composition

The Action calls the existing per-domain Actions
(`FetchDevotionalAction`, `ListNewsAction`, `ListBibleVersionsAction`,
`GetDailyVerseAction`, `ShowSabbathSchoolLessonAction`, …) so the
caching done in MBA-021 is reused naturally — bootstrap just stitches
the cached results together. No duplicated query logic.

For `sabbath_school_current_lesson`, resolve the lesson whose
(week_start, week_end) brackets `now()` for the caller's language;
fall back to `latest published_at` if none.

For `qr_codes`, return the full set — it's small (198 rows ~62 KB
gzipped) and clients use it as a lookup table that doesn't change
between cold starts.

### Sync deltas — soft-delete vs tombstones

Recommend SoftDeletes — fewer moving parts, idiomatic Laravel.
Cost: existing queries on the affected models need to be audited
to ensure they don't accidentally include trashed rows. The Action
that returns deletions queries `withTrashed()->whereNotNull('deleted_at')->where('deleted_at', '>', $since)`.

### Health-check stability

The current `/up` doing a DB+Redis ping is the right shape for
**readiness**, not liveness. With LB polling every 10 s × interval
healthy_threshold=3, the readiness model means a 30-second Redis
hiccup drops the droplet from rotation. Liveness on PHP-FPM only
makes the LB more tolerant of transient dep blips.

### Rate-limit storage

Use the same Valkey cluster (configured in `config/cache.php`) for
the rate-limit counters. Laravel's `RateLimiter` defaults to the
`cache.default` store; verify in `boot()`.

## Dependencies
- **MBA-005** (auth + foundation).
- **MBA-021** (cache wiring) — bootstrap reuses MBA-021's cached
  Actions.
- **MBA-020** (Symfony cutover) — sync endpoint is moot until
  mobile clients point at this API. Until then bootstrap+sync
  ship "behind a flag" / parallel to legacy.

## Open Questions for Architect
1. **Soft-delete vs tombstones** — confirm SoftDeletes is the chosen
   strategy across all 7 in-scope models. Any model that already has
   a different deletion semantic (e.g. cascade-on-user-delete)?
2. **Bootstrap TTL** — 300s is the recommendation; if news edits
   need to land faster, propose either a manual flush hook or
   shortened TTL on news-only.
3. **Rate-limit per-IP behind LB** — `request->ip()` returns the LB
   internal IP unless `TrustProxies` is configured for the LB CIDR.
   Confirm `App\Http\Middleware\TrustProxies::$proxies = '*'` (or
   the specific LB CIDR) so rate limiter sees real client IP from
   `X-Forwarded-For`.
4. **`/up` cache-control** — health check responses go through Caddy
   logging by default; confirm we want to keep them in Caddy access
   logs (current behavior) or filter them out at the Vector source.
5. **Bootstrap shape** — should `qr_codes` be in the bootstrap
   payload, or fetched lazily? Mobile reports show ~36 hits/30min
   on `/qr-codes`, suggesting it IS already cold-start fetched.
   Including it adds ~62 KB to the bootstrap response.
