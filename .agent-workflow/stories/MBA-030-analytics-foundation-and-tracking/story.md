# Story: MBA-030-analytics-foundation-and-tracking

## Title

Analytics foundation: event ingest endpoint, generic event store, daily
roll-ups, DAU/MAU computation, tracking instrumentation across all
content domains, admin read endpoints for the dashboard.

## Status

`in-review`

## Description

The PM has explicitly asked for usage metrics — accesses per devotional
type, per Sabbath School lesson, per resource book, per Bible
translation — and partner agreements (NTR Bible) require usage reporting.
The product owner additionally wants funnel metrics on reading plans
(started, daily completion, abandoned at which day) and general
engagement signals (DAU/MAU).

This story builds the analytics pipeline as **one** large story per the
agreed granularity: a generic event store with polymorphic
(`subject_type`, `subject_id`) events, plus pre-aggregated daily roll-up
tables that the admin dashboard queries. It also lands the per-feature
event emissions in the API endpoints so events flow as soon as
front-end/mobile clients call them.

Tracking is anonymous-friendly: anonymous users carry a `device_id` (web
cookie or mobile-generated UUID); authenticated users add their
`user_id`. No IP storage. Client also sends `source` (`ios|android|web`)
and `app_version`, derived from the User-Agent header server-side rather
than trusting client claim (per stakeholder decision F).

## Acceptance Criteria

### Schema — events and rollups

1. `analytics_events` table:
   - `id BIGINT UNSIGNED PRIMARY KEY`
   - `event_type VARCHAR(64) NOT NULL` — e.g. `bible.chapter.viewed`
   - `subject_type VARCHAR(64) NULL` — `bible_chapter`,
     `devotional`, `sabbath_school_lesson`, `educational_resource`,
     etc. NULL for events without a subject (`auth.login`).
   - `subject_id BIGINT UNSIGNED NULL`
   - `user_id INT UNSIGNED NULL` — set when request has a Sanctum token
   - `device_id VARCHAR(64) NULL` — anonymous attribution
   - `language CHAR(2) NULL` — request language
   - `source VARCHAR(16) NULL` — `ios | android | web`
   - `app_version VARCHAR(32) NULL` — derived from User-Agent
   - `metadata JSON NULL` — event-specific payload
     (e.g. `{ "version_abbreviation": "VDC" }` on a chapter-viewed
     event)
   - `occurred_at TIMESTAMP NOT NULL` — wall-clock time the client
     emitted the event (allows retroactive ingest)
   - `created_at TIMESTAMP NOT NULL` — server-side receipt time
2. Indexes:
   - `(event_type, occurred_at)` — for "events of type X in window"
   - `(subject_type, subject_id, occurred_at)` — for per-subject metrics
   - `(user_id, occurred_at)` — for per-user history (DAU/MAU)
   - `(device_id, occurred_at)` — for unique-device counts
3. Retention: indefinite (per stakeholder decision D). The table is
   expected to grow; rollup tables ensure dashboard queries don't scan
   the raw store.

### Daily rollup tables

4. `analytics_daily_rollups` — pre-aggregated per
   `(date, event_type, subject_type, subject_id, language)`:
   - `date DATE NOT NULL`
   - `event_type VARCHAR(64) NOT NULL`
   - `subject_type VARCHAR(64) NULL`
   - `subject_id BIGINT UNSIGNED NULL`
   - `language CHAR(2) NULL`
   - `event_count INT UNSIGNED NOT NULL`
   - `unique_users INT UNSIGNED NOT NULL` — distinct `user_id` (NULL
     excluded)
   - `unique_devices INT UNSIGNED NOT NULL` — distinct `device_id`
     (NULL excluded)
   - PRIMARY KEY `(date, event_type, subject_type, subject_id, language)`
5. `analytics_user_active_daily` — for DAU/MAU:
   - `date DATE NOT NULL`
   - `user_id INT UNSIGNED NOT NULL`
   - PRIMARY KEY `(date, user_id)`
   - Populated by the rollup job: any user with at least one event on
     a given date gets a row.
6. `analytics_device_active_daily` — same shape but on `device_id`,
   for anonymous DAU/MAU.

### Roll-up scheduled job

7. `App\Application\Jobs\RollupAnalyticsDailyJob` runs every day at
   01:00 server time (registered in `routes/console.php`):
   - Aggregates the prior day's `analytics_events` into
     `analytics_daily_rollups`, `analytics_user_active_daily`,
     `analytics_device_active_daily`.
   - Idempotent: re-running for the same date replaces existing rows
     (delete-then-insert in a transaction).
8. Also runs a "current day" partial roll-up every 30 minutes so the
   dashboard can show today's running totals without scanning raw
   events for the whole day.

### Ingest endpoint

9. `POST /api/v1/analytics/events` — anonymous-friendly. Body:
   ```json
   {
     "events": [
       {
         "event_type": "bible.chapter.viewed",
         "subject_type": "bible_chapter",
         "subject_id": 123,
         "language": "ro",
         "metadata": { "version_abbreviation": "VDC" },
         "occurred_at": "2026-05-02T16:30:00Z"
       }
     ],
     "device_id": "uuid-from-client",
     "source": "web",
     "app_version": "1.4.2"
   }
   ```
   Response: `204 No Content` on accept; `422` on schema violation.
   Validates each event (event_type allowlist, subject_type allowlist,
   metadata schema where one is registered).
10. Server enriches each event with:
    - `user_id` from Sanctum token if present.
    - `created_at` = now.
    - `source` from User-Agent if not in body (browser → web; iOS
      app UA → ios; etc.).
11. Rate-limit: 600 events / minute per `(ip + device_id)` — high
    enough for normal use, low enough to deflect abuse. Bursts beyond
    that get `429`.
12. Batched ingest: clients can send up to 100 events per call to
    reduce request volume on mobile.

### Event-type registry

13. `App\Domain\Analytics\Events\EventType` — enum of allowed event
    types. Initial set:
    - `bible.chapter.viewed` (subject = bible_chapter; metadata:
      version)
    - `bible.passage.viewed` (subject = none; metadata: passage
      reference, version) — fired from cross-reference modals and
      content links
    - `devotional.viewed` (subject = devotional)
    - `sabbath_school.lesson.viewed` (subject = sabbath_school_lesson;
      metadata: age_group)
    - `resource.viewed` (subject = educational_resource)
    - `resource.downloaded` (subject = educational_resource)
    - `resource_book.viewed` (subject = resource_book)
    - `resource_book.chapter.viewed` (subject = resource_book_chapter)
    - `resource_book.downloaded` (subject = resource_book)
    - `resource_book.chapter.downloaded` (subject =
      resource_book_chapter)
    - `news.viewed` (subject = news)
    - `hymnal.song.viewed` (subject = hymnal_song)
    - `commentary.viewed` (subject = commentary; metadata:
      book, chapter)
    - `daily_verse.viewed` (subject = daily_verse)
    - `qr_code.scanned` (subject = qr_code)
    - `auth.login` (subject = none)
    - `reading_plan.subscription.started` (subject =
      reading_plan_subscription; metadata: plan_id, plan_slug)
    - `reading_plan.subscription.day_completed` (subject =
      reading_plan_subscription; metadata: day_position,
      subscription_age_days)
    - `reading_plan.subscription.abandoned` (subject =
      reading_plan_subscription; metadata: at_day_position,
      total_days)
    - `reading_plan.subscription.completed` (subject =
      reading_plan_subscription)
14. Each event type registers expected metadata shape so the ingest
    validation can reject malformed events early.

### Server-side event emissions

15. The following endpoints emit events server-side (not requiring
    client cooperation):
    - Reading plans: `started` / `day_completed` / `abandoned` /
      `completed` lifecycle endpoints (already exist in MBA-004);
      they fire the corresponding event after the lifecycle action.
    - Resource downloads (MBA-026): `resource.downloaded`,
      `resource_book.downloaded`,
      `resource_book.chapter.downloaded`.
    - QR Code scan (MBA-027): `qr_code.scanned`.
    - Auth login: `auth.login` event on every successful login (Sanctum
      token issued).
16. Client-emitted events (sent via the ingest endpoint by mobile/web)
    are everything else (`bible.chapter.viewed`, `devotional.viewed`,
    etc.). Client emission is preferred over server-side derivation
    because the same endpoint may be called for multiple semantic
    reasons; only the client knows whether the user actually viewed
    the content vs prefetched it.

### Admin dashboard read endpoints

17. `GET /api/v1/admin/analytics/summary?from=&to=&period=day|week|month`
    — high-level KPI panel: total events, DAU, MAU, top 5 event types
    by volume.
18. `GET /api/v1/admin/analytics/event-counts?event_type=&from=&to=&period=day|week|month&group_by=language|subject_id`
    — time-series for one event type, grouped by language or by
    subject. Drives the per-feature charts.
19. `GET /api/v1/admin/analytics/dau-mau?from=&to=&period=day|week|month`
    — DAU and MAU series. MAU is computed as 28-day rolling unique
    users ending at each `date` in the range.
20. `GET /api/v1/admin/analytics/reading-plans/funnel?plan_id=&from=&to=`
    — reading plan funnel: started count, day-N completion (1..30
    days), abandoned (with at_day distribution), completed.
21. `GET /api/v1/admin/analytics/bible/version-usage?from=&to=&period=`
    — per-Bible-version event count of `bible.chapter.viewed` and
    `bible.passage.viewed`, grouped by `metadata.version_abbreviation`.
22. All admin endpoints super-admin gated.

### Tests

23. Feature tests for the ingest endpoint covering: anonymous accept,
    authenticated user_id capture, source inference from User-Agent,
    rate-limit triggering, batch acceptance, validation failures.
24. Feature tests for each admin endpoint: super-admin gate, query
    shape, range filters honoured.
25. Roll-up job tests:
    - 100 events for 5 distinct users on day D → rollup row for
      `(D, event_type, …)` has correct counts, `unique_users = 5`.
    - Re-running the job for D produces the same result (idempotent).
    - DAU/MAU computation: insert events on 2026-04-01 through
      2026-04-30 for varying users → 28-day MAU on 2026-04-30 equals
      the union of users active in `[2026-04-03, 2026-04-30]`.
26. Server-side emission tests for each lifecycle endpoint asserting
    the appropriate event row appears.

## Scope

### In Scope

- `analytics_events` table + ingest endpoint + validation.
- Daily rollup tables + scheduled job.
- DAU/MAU tracking tables + rollup logic.
- Event-type registry with metadata schemas.
- Server-side emission on reading plans, resource downloads, QR scans,
  auth login.
- Admin read endpoints powering the dashboard.

### Out of Scope

- Admin dashboard UI (charts, period switch, language filter) — admin
  MB-018.
- Frontend tracking SDK (the helper that batches events and POSTs to
  the ingest endpoint) — frontend MB-020.
- Mobile SDK changes — out of API repo scope (handed off to mobile team).
- Geographic / IP-based analytics — explicitly excluded per stakeholder.
- Real-time streaming / websocket dashboards — daily rollup is the
  surface; today's running total is partial-rollup every 30 min, no
  push.

## API Contract Required

- `POST /api/v1/analytics/events` — described in AC §9. Returns 204.
- All admin read endpoints from AC §17–21. Each returns
  `{ data: [...], meta: { from, to, period } }`.

## Technical Notes

- The decision to use a generic `analytics_events` polymorphic table
  rather than per-domain tables (e.g. `devotional_views`,
  `chapter_reads`) is deliberate — the alternative would multiply
  ingest endpoints and rollup logic by domain count and would prevent
  cross-domain queries (e.g. "users active across any feature").
  Performance is bounded by the daily rollup, not by the raw event
  scan rate.
- Rollups are pre-computed at coarse granularity
  (`date, event_type, subject_id, language`); finer cuts (e.g. by
  age_group) are server-computed at query time from the raw events
  table for the requested window. If a particular cut becomes
  expensive, we add a rollup column for it later.
- DAU/MAU is computed from `analytics_user_active_daily` (a row per
  active user per day), not from `analytics_daily_rollups`. Two
  separate roll-ups because the cardinality is different — the
  user-day table grows linearly with users; the rollup table grows
  with cardinality of subject ids.
- Source attribution from User-Agent is best-effort: a custom UA
  header (`MyBibleMobile/1.4.2 ios`) is the cleanest signal; browsers
  default to `web`. We log unparseable UAs as `source = NULL` and
  alert if the NULL ratio exceeds 5% of events (suggests UA spoofing
  or an unrecognised mobile build).
- The reading-plan funnel endpoint deliberately joins back to the raw
  `analytics_events` table (filtered to the four lifecycle event
  types) rather than to the rollup, because the funnel needs per-
  user dropoff distribution that the aggregated rollup loses.

## References

- MBA-004 reading plan lifecycle endpoints (existing emission points).
- MBA-026 resource downloads (existing emission for download events).
- MBA-027 Olympiad attempts (server-side persistence; can join to
  analytics later for "olympiad attempt funnel" if desired).
- MBA-031 Horizon (the ingest endpoint enqueues a `RecordEvent` job
  rather than inserting synchronously, so spikes don't pressure the
  request path; this requires Horizon).
- Admin MB-018 (dashboard UI).
- Frontend MB-020 (tracking SDK).
- PM ask thread, 2026-04-18 (devotional types, study types per lesson,
  resource books, Bible translations).
