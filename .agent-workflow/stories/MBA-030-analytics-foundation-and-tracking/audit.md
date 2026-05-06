# Audit — MBA-030

**Verdict:** PASS — full suite green at 1394/1394 (5204 assertions, 47.24s)
on the audited HEAD (`416baee` + audit pass). No Critical or Warning
issues remain after a holistic re-read of architecture, code quality,
API design, security, performance, and test coverage. Suggestions are
listed below with their disposition.

The prior review (`review.md`) and QA (`qa.md`) were thorough. The
re-review correctly fixed C1 (funnel `plan_id`), W1–W4 (emission tests,
dead `default` arm, ingest-comment, FQCN-fallback), and S2/S4 (sentinel
translation owner + factory enums). Acknowledged S1/S3 deferrals are
faithfully recorded in code/comments.

## Issue table

| # | Issue | Location | Severity | Status | Resolution |
|---|---|---|---|---|---|
| 1 | `app_version` written from request body, but story description says it should be "derived from the User-Agent header server-side rather than trusting client claim" (alongside `source`). Today only `source` is server-derived; `app_version` is body-trusted with `max:32`. | `app/Domain/Analytics/Support/ClientContextResolver.php`, `app/Http/Requests/Analytics/IngestAnalyticsEventsRequest.php:45` | Suggestion | Deferred | Read intent in the story is ambiguous (AC §10 explicitly enriches only `source`; AC §1's "derived from User-Agent" describes the column purpose, not the wiring). Acceptable given Sanctum-token + api-key + 600/min throttle bound the abuse surface. Pointer: pull `app_version` from a dedicated UA token (`MyBibleMobile/<ver> ios`) once the partner agreement turns this into a hard requirement. |
| 2 | `ComputeDauMauAction` runs 4 queries per day in the requested range (DAU users + MAU users + DAU devices + MAU devices). For a 90-day window that's 360 queries. | `app/Domain/Analytics/Actions/ComputeDauMauAction.php:43-74` | Suggestion | Deferred | Super-admin-only endpoint, low traffic. Each query hits the `(date, …)` PK and is sub-millisecond on the rollup tables. Pointer: collapse to one rolling-window `SELECT … FROM analytics_user_active_daily WHERE date BETWEEN … GROUP BY date` and compute MAU client-side over 28-day suffixes if/when the dashboard adds wide ranges. |
| 3 | `SummariseBibleVersionUsageAction` reads `analytics_events` directly with `JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.version_abbreviation'))` — no rollup column; no `LIMIT`. With indefinite retention, broad ranges grow expensive. | `app/Domain/Analytics/Actions/SummariseBibleVersionUsageAction.php:29-38` | Suggestion | Deferred | Already documented in the action's own PHPDoc as the first "metadata cut not in rollup" case and flagged as a follow-up trigger. Index `(event_type, occurred_at)` keeps the table-side cost bounded for narrow ranges. Pointer: promote `metadata_key/metadata_value` to a rollup dimension once a second metadata cut lands. |
| 4 | `BuildReadingPlanFunnelAction` issues 5 queries against `analytics_events` (started, completed, abandoned, completed-per-day, abandoned-at-day) by cloning the base. Could be one query with `CASE WHEN`. | `app/Domain/Analytics/Actions/BuildReadingPlanFunnelAction.php:45-69` | Suggestion | Deferred | Each query is filtered on `(event_type, occurred_at)` index + JSON predicate. Five queries at admin scale is acceptable; folding into one MultiCASE aggregation trades clarity for negligible wall-clock savings. Pointer: revisit if the funnel adds more event-type dimensions. |
| 5 | Schedule entries in `routes/console.php` (`RollupAnalyticsDailyJob` daily 01:00, `RollupAnalyticsTodayJob` every 30 minutes) have no test that asserts they are registered. A typo or accidental removal would only surface in production. | `routes/console.php:17-26` | Suggestion | Deferred | The action they invoke is fully covered (`RollupAnalyticsForDateActionTest`); only the cadence wiring is unverified. Other scheduled jobs in this repo are similarly unverified — adding an assertion just for these would set a new precedent. Pointer: a single "schedule integrity" test (asserts `Schedule::events()` contains both job names) would cover this and future scheduled work. |
| 6 | Rate-limit feature test couples to Laravel's internal `RateLimiter` key hashing (`md5('analytics-ingest127.0.0.1|d1')`). A future change to `ThrottleRequests` key derivation would silently weaken the test. | `tests/Feature/Api/V1/Analytics/IngestAnalyticsEventsTest.php:211` | Suggestion | Deferred | Pragmatic given there's no public API to "pre-warm a named limiter to its threshold". The test still asserts the 429 outcome; only the path to that outcome is fragile. Pointer: switch to a 601-iteration loop or a `RateLimiter` shim if Laravel changes the key scheme. |
| 7 | `RecordResourceDownloadAction` silently no-ops the analytics emission when `EventType::tryFrom($eventType)` returns null — a typo in a caller's hard-coded event type string vanishes without trace. | `app/Domain/Analytics/Actions/RecordResourceDownloadAction.php:55-62` | Suggestion | Deferred | The caller already passes only the three resource-download EventType strings, all of which currently parse. The defensive `tryFrom` is reasonable for a domain Action that accepts a string contract. Pointer: tighten to accept `EventType` directly when the next download-emitting path lands. |
| 8 | `ResourceDownloadContextData` is now also the analytics ingest context (login, QR, reading-plan, ingest) — the name no longer reflects scope. Carried forward from review S1. | `app/Domain/Analytics/DataTransferObjects/ResourceDownloadContextData.php` | Suggestion | Deferred (review.md S1) | Already acknowledged in `review.md` and pinned for the next analytics story. No new copies were added by this audit. |
| 9 | `AnalyticsRangeQueryData::period` is echoed in `meta` on every admin endpoint but never drives grouping inside the read actions. Carried forward from review S3. | `app/Domain/Analytics/DataTransferObjects/AnalyticsRangeQueryData.php:14`, all read actions | Suggestion | Deferred (review.md S3) | Already acknowledged in `review.md`. Either drop or wire into a grouping switch in a follow-up. |
| 10 | `AnalyticsEventQueryBuilder` and `AnalyticsDailyRollupQueryBuilder` are wired via `newEloquentBuilder` but every read action uses `DB::table(...)` directly — the QueryBuilder scopes are dead code as shipped. Carried forward from review notes. | `app/Domain/Analytics/QueryBuilders/*` | Suggestion | Deferred (review.md notes) | Already noted in `review.md`. Either route actions through Eloquent or trim unused scopes in a follow-up. |
| 11 | Sentinels (`''` for nullable strings, `0` for nullable `subject_id`) are written by the rollup but the contract is documented only in the migration PHPDoc and the action header. Future readers querying `analytics_daily_rollups` directly outside this story may double-count NULL vs sentinel rows. | `database/migrations/2026_05_06_000002_create_analytics_daily_rollups_table.php:9-23` | Suggestion | Skipped | Documented in migration PHPDoc and `RollupAnalyticsForDateAction` PHPDoc (the two natural read points). Adding a third doc copy would not improve discoverability. |

## Resolved (carried over from prior gates)

- `review.md` C1 — funnel `plan_id` filter via `JSON_EXTRACT(metadata,'$.plan_id')`, all four lifecycle Actions write matching metadata, ingest validation enforces the same shape. Verified at `app/Domain/Analytics/Actions/BuildReadingPlanFunnelAction.php:42` and matching factories/tests.
- `review.md` W1 — server-side emission tests cover all 8 emission points (`tests/Feature/Domain/Analytics/ServerSideEmissionsTest.php`).
- `review.md` W2 — `EventType::metadataRules()` `default => []` arm is reachable; `IngestAnalyticsEventsRequest:104-107` becomes a real fast-path.
- `review.md` W3 — ingest `subject_id` documented as deliberate trade-off in code at `IngestAnalyticsEventsRequest.php:33-37`.
- `review.md` W4 — `RecordAnalyticsEventAction` throws `InvalidArgumentException` on unmapped morph aliases.
- `review.md` S2 — sentinel translation owned by `ListEventCountsAction`; `AnalyticsEventCountRowResource` is a flat passthrough.
- `review.md` S4 — `AnalyticsDailyRollupFactory` uses enum `->value`, no string magic.

## Test run

```
make test-api
→ Tests: 1394 passed (5204 assertions)
→ Duration: 47.24s
```

No regressions. Analytics suite (`tests/Feature/Api/V1/Analytics/`,
`tests/Feature/Api/V1/Admin/Analytics/`,
`tests/Feature/Domain/Analytics/`) covers every AC item per the matrix
in `qa.md`.

## Tripwire watch (`.agent-workflow/CLAUDE.md` §7)

- Owner-`authorize()` block counter remains at 4/5. This story added no
  owner-gated endpoints (admin endpoints are super-admin gated, ingest
  is api-key-or-sanctum + throttle). No counter change.
- `withProgressCounts()` helper count remains at 2/3. The reading-plan
  lifecycle Actions touched in this story (`Start*`, `Complete*Day`,
  `Abandon*`, `Finish*`) did not introduce a new copy — `Abandon*` and
  `Finish*` keep their existing helper, `Start*` and `Complete*Day`
  use raw `loadCount` for distinct shapes.

## Verdict

**PASS** — move story to `done`.
