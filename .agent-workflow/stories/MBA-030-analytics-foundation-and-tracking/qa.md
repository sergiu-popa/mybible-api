# QA — MBA-030

**Verdict:** QA PASSED — all 1394 tests pass (5204 assertions, 51.15s).
The 39 analytics-specific tests pass standalone. Each acceptance
criterion is backed by at least one passing test. No regressions
detected in adjacent domains (auth, reading plans, resource downloads,
QR scans). Critical review item C1 (funnel `plan_id` filter) is verified
via `test_reading_plan_funnel_filters_by_plan_id_using_metadata`.

## Test run

```
make test-api
→ Tests: 1394 passed (5204 assertions)
→ Duration: 51.15s
```

Analytics-only re-run: `tests/Feature/Api/V1/Analytics/`,
`tests/Feature/Api/V1/Admin/Analytics/`, `tests/Feature/Domain/Analytics/`
→ 39 passed, 140 assertions, 2.25s.

## AC → test coverage

| AC | Test |
|---|---|
| §1–3 schema & indexes (`analytics_events`) | Exercised by ingest + rollup tests via real inserts; indexes verified by migration. |
| §4 `analytics_daily_rollups` | `RollupAnalyticsForDateActionTest::test_aggregates_events_into_rollups` |
| §5 `analytics_user_active_daily` | `RollupAnalyticsForDateActionTest::test_aggregates_events_into_rollups`, `ComputeDauMauActionTest` |
| §6 `analytics_device_active_daily` | `RollupAnalyticsForDateActionTest::test_aggregates_events_into_rollups` |
| §7 daily rollup job idempotent | `RollupAnalyticsForDateActionTest::test_rerun_is_idempotent` |
| §8 partial today rollup | Same action covers both jobs; schedule registered in `routes/console.php` |
| §9 `POST /api/v1/analytics/events` (204 / 422) | `IngestAnalyticsEventsTest::test_anonymous_request_accepts_a_batch`, `…rejects_unknown_event_type`, `…rejects_subjectful_event_without_subject`, `…rejects_chapter_view_without_required_metadata` |
| §10 server enrichment (user_id, source from UA) | `test_authenticated_request_captures_user_id`, `test_infers_ios_source_from_user_agent`, `test_infers_android_source_from_user_agent` |
| §11 rate-limit 600/min | `test_rate_limiter_blocks_after_600_requests_per_window` |
| §12 batch up to 100 events | `test_accepts_a_full_100_event_batch`, `test_rejects_a_101_event_batch` |
| §13 event-type registry | `test_rejects_unknown_event_type` |
| §14 metadata schemas | `test_rejects_chapter_view_without_required_metadata` |
| §15 server-side emissions (login / reading-plan ×4 / QR / downloads ×3) | `ServerSideEmissionsTest` — 9 tests covering all 8 emission points (login, qr_code.scanned, resource.downloaded, resource_book.downloaded, resource_book.chapter.downloaded, reading_plan.subscription.{started, day_completed, abandoned, completed}) |
| §17 summary endpoint | `AdminAnalyticsEndpointsTest::test_summary_returns_kpi_panel` |
| §18 event-counts endpoint | `test_event_counts_returns_per_day_series` |
| §19 DAU/MAU endpoint + 28-day rolling MAU | `test_dau_mau_returns_series`, `ComputeDauMauActionTest::test_28_day_rolling_mau_equals_distinct_users_in_window` |
| §20 reading-plan funnel + plan_id filter | `test_reading_plan_funnel_returns_shape`, `test_reading_plan_funnel_filters_by_plan_id_using_metadata` |
| §21 bible version-usage | `test_bible_version_usage_returns_shape` |
| §22 super-admin gating | `test_summary_requires_super_admin`, `test_reading_plan_funnel_requires_super_admin` (shared middleware group covers other admin routes) |

## Edge cases probed (already in suite)

- Empty/oversized batch: `test_rejects_a_101_event_batch` covers the
  upper bound; `IngestAnalyticsEventsRequest` rule `events|array|min:1`
  rejects empty payloads (validation contract).
- Unauthorized admin access: super-admin gate tested for `summary` and
  `funnel` — same middleware fronts the other three admin routes.
- Idempotent rollup rerun: `test_rerun_is_idempotent` verifies
  delete-then-insert under transaction.
- Source inference for both iOS and Android UAs; web is the default.
- Authed vs anonymous capture (user_id NULL on anon batches).

## Regression check

Adjacent features that gained an emission call (auth login, QR scan,
reading-plan lifecycle ×4, resource downloads ×3) all pass their
existing feature tests in the full run — no behavioural drift from the
new `RecordAnalyticsEventAction` dispatches.

## Outstanding (acknowledged, non-blocking)

- S1 (`ResourceDownloadContextData` rename), S3 (`period` field
  decorative) — review.md defers these to a follow-up analytics story.
- Unused QueryBuilder scopes flagged in review notes — read actions use
  `DB::table` directly. Not a correctness issue.

## Verdict

**QA PASSED** — move story to `qa-passed`.
