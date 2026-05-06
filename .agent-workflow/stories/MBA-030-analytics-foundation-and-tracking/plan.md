# Plan — MBA-030

> Design, don't implement. No code blocks, no method bodies, no SQL.
> Every helper listed here must be referenced by a task below.

## Approach

Build a single polymorphic `analytics_events` write path fronted by a queued job so HTTP threads never block on inserts; clients (and server-side emission points) hand events to a `RecordEventAction` that enqueues `RecordAnalyticsEventJob` (queue=database, default connection — the existing `mybible-api-worker` drains it). A daily `RollupAnalyticsDailyJob` (scheduled 01:00 + a partial pass every 30 min for "today") materialises three rollup tables (`analytics_daily_rollups`, `analytics_user_active_daily`, `analytics_device_active_daily`) under a delete-then-insert transaction so reruns are idempotent. Admin read endpoints query rollups exclusively except the reading-plan funnel, which joins back to raw events for per-user dropoff. Storage risk: raw events grow unbounded — call this out for ops; mitigation is rollup coverage and a future partition story.

## Domain

| Item | Role |
|---|---|
| `App\Domain\Analytics\Models\AnalyticsEvent` | Eloquent model for `analytics_events`; morph alias map seeded in `AppServiceProvider`. |
| `App\Domain\Analytics\Models\AnalyticsDailyRollup` | Read model for `analytics_daily_rollups`. |
| `App\Domain\Analytics\Models\AnalyticsUserActiveDaily` | DAU/MAU user-day model. |
| `App\Domain\Analytics\Models\AnalyticsDeviceActiveDaily` | DAU/MAU device-day model. |
| `App\Domain\Analytics\Enums\EventType` | Backed enum of every allowed `event_type` string + `expectedSubjectType()` + `metadataRules()`. |
| `App\Domain\Analytics\Enums\EventSubjectType` | Backed enum of allowed `subject_type` morph aliases (`bible_chapter`, `devotional`, `sabbath_school_lesson`, `educational_resource`, `resource_book`, `resource_book_chapter`, `news`, `hymnal_song`, `commentary`, `daily_verse`, `qr_code`, `reading_plan_subscription`). |
| `App\Domain\Analytics\Enums\EventSource` | Backed enum (`Ios`, `Android`, `Web`) shared by ingest + emission. |
| `App\Domain\Analytics\QueryBuilders\AnalyticsEventQueryBuilder` | `inWindow()`, `ofType()`, `forSubject()`, `forUser()`, `forDevice()` scopes; consumed by rollup job + funnel action. |
| `App\Domain\Analytics\QueryBuilders\AnalyticsDailyRollupQueryBuilder` | `between()`, `ofType()`, `groupedBy(language\|subject)`, `topByVolume()`; consumed by summary + event-counts + bible-version actions. |

Reuse: extend the existing `App\Domain\Analytics\Support\ClientContextResolver` rather than introducing a second resolver — it already pulls user/device/language/source the same way the ingest endpoint needs.

## Actions / DTOs

| Class | Role |
|---|---|
| `App\Domain\Analytics\DataTransferObjects\IngestEventData` | Per-event payload (event_type, subject_type, subject_id, language, metadata, occurred_at). |
| `App\Domain\Analytics\DataTransferObjects\IngestBatchData` | Batch wrapper carrying `IngestEventData[]` + resolved `ClientContext`. |
| `App\Domain\Analytics\DataTransferObjects\AnalyticsRangeQueryData` | from/to/period for read endpoints. |
| `App\Domain\Analytics\DataTransferObjects\EventCountsQueryData` | range + event_type + group_by (`language`/`subject_id`). |
| `App\Domain\Analytics\DataTransferObjects\ReadingPlanFunnelQueryData` | range + plan_id. |
| `App\Domain\Analytics\Actions\RecordAnalyticsEventAction` | Sole write entry point; takes `EventType`, optional subject Model, metadata, `ResourceDownloadContextData`, `occurred_at`; dispatches `RecordAnalyticsEventJob`. Used by ingest controller, `EmitAnalyticsEventListener`, and direct callers in reading-plan/QR/auth lifecycle Actions. |
| `App\Domain\Analytics\Actions\RecordAnalyticsEventBatchAction` | Same as above but loops a batch into a single job dispatch (one job per event keeps retries granular). |
| `App\Domain\Analytics\Actions\SummariseAnalyticsAction` | Powers `summary` endpoint; reads rollups + active-daily tables. |
| `App\Domain\Analytics\Actions\ListEventCountsAction` | Powers `event-counts`; pivots rollups by language or subject_id. |
| `App\Domain\Analytics\Actions\ComputeDauMauAction` | Computes DAU per day and 28-day rolling MAU for both user and device tables across the requested range. |
| `App\Domain\Analytics\Actions\BuildReadingPlanFunnelAction` | Joins raw `analytics_events` (filtered to the four `reading_plan.subscription.*` types) to compute per-day-N completion + abandoned-at distribution. |
| `App\Domain\Analytics\Actions\SummariseBibleVersionUsageAction` | Groups `bible.chapter.viewed` + `bible.passage.viewed` rollups by `metadata.version_abbreviation` (raw events fallback because rollups don't carry the version). Mark in code comment that this is the first known case of "metadata cut not in rollup" and may motivate adding a `metadata_key` rollup column later. |
| `App\Domain\Analytics\Actions\RollupAnalyticsForDateAction` | Idempotent per-date aggregator; deletes-then-inserts the three rollup tables for a given calendar date inside a transaction; reused by both the daily job and the partial-today job. |

## Events / Listeners / Jobs

| Class | Role |
|---|---|
| `App\Application\Jobs\RecordAnalyticsEventJob` (ShouldQueue, queue=default→database) | Persists one event row from a serialised `IngestEventData` + context; tries=3 with backoff; logs+swallows on final failure so a poison event can never block downstream events. |
| `App\Application\Jobs\RollupAnalyticsDailyJob` (ShouldQueue) | Scheduled daily 01:00; rolls up the prior calendar date by calling `RollupAnalyticsForDateAction`. |
| `App\Application\Jobs\RollupAnalyticsTodayJob` (ShouldQueue) | Scheduled every 30 minutes; rolls up the current calendar date (partial). |
| `App\Domain\Analytics\Events\AnalyticsEventOccurred` | Lightweight payload (event_type, subject?, metadata, context). Optional — used only if a domain wants to react in-process; the canonical write path is the Action. |
| Login emission | Inside `LoginUserAction` after token mint, call `RecordAnalyticsEventAction` with `EventType::AuthLogin`. |
| Reading-plan emissions | Inside `StartReadingPlanSubscriptionAction`, `CompleteReadingPlanSubscriptionDayAction`, `AbandonReadingPlanSubscriptionAction`, `FinishReadingPlanSubscriptionAction` — emit the matching event after the lifecycle commit. |
| Resource-download emissions | Replace the in-place `DownloadOccurred` dispatch in `RecordResourceDownloadAction` with a call to `RecordAnalyticsEventAction` carrying the same eventType (`resource.downloaded` / `resource_book.downloaded` / `resource_book.chapter.downloaded`). Keep `DownloadOccurred` event class for any other listeners; update `SummariseResourceDownloadsAction` once rollups can serve it (out of scope here — leave the BadRequest fallback in place). |
| QR scan emission | Inside `RecordQrCodeScanAction`, emit `EventType::QrCodeScanned`. |

## HTTP / Endpoints

| Method | Path | Controller | Form Request | Resource | Auth |
|---|---|---|---|---|---|
| POST | `/api/v1/analytics/events` | `App\Http\Controllers\Api\V1\Analytics\IngestAnalyticsEventsController` | `IngestAnalyticsEventsRequest` | none (204) | `api-key-or-sanctum`, `throttle:analytics-ingest` |
| GET | `/api/v1/admin/analytics/summary` | `Admin\Analytics\ShowAnalyticsSummaryController` | `ShowAnalyticsSummaryRequest` | `AnalyticsSummaryResource` | `auth:sanctum`, `super-admin` |
| GET | `/api/v1/admin/analytics/event-counts` | `Admin\Analytics\ListAnalyticsEventCountsController` | `ListAnalyticsEventCountsRequest` | `AnalyticsEventCountsResource` | `auth:sanctum`, `super-admin` |
| GET | `/api/v1/admin/analytics/dau-mau` | `Admin\Analytics\ShowDauMauController` | `ShowDauMauRequest` | `DauMauSeriesResource` | `auth:sanctum`, `super-admin` |
| GET | `/api/v1/admin/analytics/reading-plans/funnel` | `Admin\Analytics\ShowReadingPlanFunnelController` | `ShowReadingPlanFunnelRequest` | `ReadingPlanFunnelResource` | `auth:sanctum`, `super-admin` |
| GET | `/api/v1/admin/analytics/bible/version-usage` | `Admin\Analytics\ShowBibleVersionUsageController` | `ShowBibleVersionUsageRequest` | `BibleVersionUsageResource` | `auth:sanctum`, `super-admin` |

- Ingest validation rules live in `IngestAnalyticsEventsRequest`: `events` array max 100; each row's `event_type` must be in `EventType`, `subject_type` must match `EventType::expectedSubjectType()` (or be null when the type is subjectless), `metadata` is normalised through `EventType::metadataRules()` per row (use a `Validator::make()` inside `withValidator()` to apply the per-event-type rules).
- Throttle: register a new `analytics-ingest` limiter in `AppServiceProvider` keyed by `ip|device_id` at 600/minute (existing `downloads` limiter is the precedent).
- Source attribution: `IngestAnalyticsEventsController` reuses `ClientContextResolver::fromRequest`. Per stakeholder F, the resolver is the source of truth — body-supplied `source` is accepted only as a hint when User-Agent is unparseable. Audit risk: tighten the resolver if the partner agreement turns this into a hard rule.

## Tasks

- [x] 1. Create migration `create_analytics_events_table` matching the AC schema and indexes.
- [x] 2. Create migration `create_analytics_daily_rollups_table` with the composite primary key from AC §4.
- [x] 3. Create migration `create_analytics_user_active_daily_table` per AC §5.
- [x] 4. Create migration `create_analytics_device_active_daily_table` per AC §6.
- [x] 5. Add `AnalyticsEvent`, `AnalyticsDailyRollup`, `AnalyticsUserActiveDaily`, `AnalyticsDeviceActiveDaily` models with factories.
- [x] 6. Add `AnalyticsEventQueryBuilder` (scopes per Domain table); wire via `newEloquentBuilder`.
- [x] 7. Add `AnalyticsDailyRollupQueryBuilder`; wire via `newEloquentBuilder`.
- [x] 8. Add `EventType` enum with `expectedSubjectType()` + `metadataRules()`.
- [x] 9. Add `EventSubjectType` enum and register every alias in `AppServiceProvider`'s `Relation::morphMap` (extend the existing block; do not duplicate it).
- [x] 10. Add `EventSource` enum and refactor `ClientContextResolver` to return it instead of a raw string.
- [x] 11. Add `IngestEventData`, `IngestBatchData`, `AnalyticsRangeQueryData`, `EventCountsQueryData`, `ReadingPlanFunnelQueryData` DTOs.
- [x] 12. Add `RecordAnalyticsEventJob` (database queue, tries+backoff) that writes one event row.
- [x] 13. Add `RecordAnalyticsEventAction` (single dispatch) and `RecordAnalyticsEventBatchAction` (loop dispatch).
- [x] 14. Add `IngestAnalyticsEventsRequest` with batch shape, allowlist enforcement, and per-event metadata-rule application.
- [x] 15. Add `IngestAnalyticsEventsController` returning 204; wire to `RecordAnalyticsEventBatchAction`.
- [x] 16. Register `analytics-ingest` rate-limiter in `AppServiceProvider` (600/min per `ip|device_id`).
- [x] 17. Add `RollupAnalyticsForDateAction` covering all three rollup tables idempotently in one transaction.
- [x] 18. Add `RollupAnalyticsDailyJob` and `RollupAnalyticsTodayJob` invoking the action with the right date.
- [x] 19. Register both rollup jobs in `routes/console.php` (`Schedule::job(...)->dailyAt('01:00')` and `->everyThirtyMinutes()`).
- [x] 20. Add `SummariseAnalyticsAction` + `ShowAnalyticsSummaryRequest` + `ShowAnalyticsSummaryController` + `AnalyticsSummaryResource`; wire route under the existing `admin` prefix group with `super-admin`.
- [x] 21. Add `ListEventCountsAction` + `ListAnalyticsEventCountsRequest` + controller + `AnalyticsEventCountsResource`; route same group.
- [x] 22. Add `ComputeDauMauAction` + `ShowDauMauRequest` + controller + `DauMauSeriesResource`; route same group.
- [x] 23. Add `BuildReadingPlanFunnelAction` + `ShowReadingPlanFunnelRequest` + controller + `ReadingPlanFunnelResource`; uses raw events; route same group.
- [x] 24. Add `SummariseBibleVersionUsageAction` + `ShowBibleVersionUsageRequest` + controller + `BibleVersionUsageResource`; route same group.
- [x] 25. Emit `auth.login` from `LoginUserAction` immediately after `createToken`.
- [x] 26. Emit lifecycle events from each of `StartReadingPlanSubscriptionAction`, `CompleteReadingPlanSubscriptionDayAction`, `AbandonReadingPlanSubscriptionAction`, `FinishReadingPlanSubscriptionAction` (one event each).
- [x] 27. Migrate `RecordResourceDownloadAction` to also emit through `RecordAnalyticsEventAction` (preserving the existing `DownloadOccurred` dispatch).
- [x] 28. Emit `qr_code.scanned` from `RecordQrCodeScanAction`.
- [x] 29. Feature test the ingest endpoint: anonymous accept, authed user_id capture, source inference (web/ios/android UA), batch (100 events) accept, validation failures, rate-limit triggering at 601st event.
- [x] 30. Feature test each of the five admin endpoints: `super-admin` gate (403 for non-super), valid range query, period switch (day/week/month), JSON shape via `assertJsonStructure`.
- [x] 31. Feature test the rollup job: 100 events / 5 users / 1 day → expected `event_count=100`, `unique_users=5`, `unique_devices=N`; rerun is idempotent.
- [x] 32. Feature test DAU/MAU: seed events across April 2026, assert 28-day rolling MAU equals the union over the trailing 28 days.
- [x] 33. Feature test server-side emissions: hitting each lifecycle endpoint (login, start/complete/abandon/finish reading-plan, QR scan, resource downloads ×3) inserts the matching `analytics_events` row (run jobs synchronously via `Queue::fake` + assert dispatched, or `Bus::fake` then dispatch).

## Risks

- **Raw-event growth.** Indefinite retention plus high-traffic endpoints (Bible chapter views) will inflate `analytics_events` quickly. This story does not partition or archive — call it out in the migration's PHPDoc and flag a follow-up story when the table crosses ~100M rows; the rollup tables remain queryable regardless.
- **Queue back-pressure.** Every event becomes a database-queue job. If event volume spikes faster than the worker drains, the `jobs` table grows. The story's reference to MBA-031 (Horizon) is the medium-term fix; for now keep the job lightweight (single insert, no joins) and add a worker concurrency note in the migration PR description.
- **Bible-version cut not in rollups.** `version-usage` reads raw events because `metadata.version_abbreviation` isn't a rollup dimension. Acceptable for now; if the dashboard adds more metadata cuts, promote `metadata_key/metadata_value` to rollup columns.
- **Source attribution best-effort.** Unparseable User-Agents yield `source = NULL`; alerting on the NULL ratio is out of scope here (would need an Ops dashboard) but worth noting in story handoff.
- **Tripwire watch.** The owner-`authorize()` block (currently 4/5 in the deferred-extractions register) is unaffected by this story — admin endpoints are super-admin gated, not owner-gated. No new copies added; do not reset the counter.
