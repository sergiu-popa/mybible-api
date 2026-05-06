# Code Review — MBA-030 (Re-Review)

**Verdict:** APPROVE — all blocking items from the prior pass are
correctly addressed in commit `416baee`. S1 and S3 are explicitly
deferred to a follow-up analytics story (acknowledged below). Status
moves to `qa-ready`.

The architecture matches the plan (polymorphic store + 3 rollup tables +
queued ingest + admin reads + server-side emissions). The reading-plan
funnel is now correctly per-plan; emission test coverage matches plan
§33; and the morph-map invariant is load-bearing again.

---

## Resolved from prior review

- [x] **C1 — funnel `plan_id` filter.** Fixed in
      `app/Domain/Analytics/Actions/BuildReadingPlanFunnelAction.php:36-43`:
      filter is now `JSON_EXTRACT(metadata, '$.plan_id') = ?` exclusively,
      with the bogus `subject_id = planId` branch removed. The four
      reading-plan lifecycle Actions
      (`StartReadingPlanSubscriptionAction.php:77-80`,
      `CompleteReadingPlanSubscriptionDayAction.php:46-47`,
      `AbandonReadingPlanSubscriptionAction.php:53-54`,
      `FinishReadingPlanSubscriptionAction.php:60-61`) now uniformly
      write `metadata.plan_id` + `metadata.plan_slug`.
      `EventType::metadataRules()` enforces the same shape so ingest-side
      events match server-side emission. Coverage:
      `AdminAnalyticsEndpointsTest::test_reading_plan_funnel_filters_by_plan_id_using_metadata:150-204`
      seeds two plans with interleaved subscription ids and asserts the
      per-plan counts are correct.

- [x] **W1 — server-side emission test coverage.** Fixed in
      `tests/Feature/Domain/Analytics/ServerSideEmissionsTest.php`:
      all eight emission points from plan §33 are now exercised
      (`login`, `qr_code.scanned`, `resource.downloaded`,
      `resource_book.downloaded`, `resource_book.chapter.downloaded`,
      and the four `reading_plan.subscription.*` lifecycle events).
      The reading-plan tests double as proof of the C1 metadata fix
      because they assert `metadata.plan_id` / `metadata.plan_slug` on
      every lifecycle row.

- [x] **W2 — dead `$rules === []` branch.** Fixed in
      `app/Domain/Analytics/Enums/EventType.php:112` — the `default`
      arm now returns `[]`, making
      `IngestAnalyticsEventsRequest.php:104-107` a real fast-path that
      skips the per-event sub-validator for free-form metadata events.

- [x] **W3 — `subject_id` not bound to a real model.** Acknowledged
      in code at `IngestAnalyticsEventsRequest.php:33-37` with a
      comment explaining the deliberate trade-off (no N domain queries
      on the hot ingest path; phantom subject_ids are an acceptable
      cost). Reasonable given the queue-backed write path.

- [x] **W4 — FQCN fallback in `RecordAnalyticsEventAction`.** Fixed in
      `app/Domain/Analytics/Actions/RecordAnalyticsEventAction.php:42-47`:
      now throws `InvalidArgumentException` when a model class is not
      registered in the morph map. Mirrors the precedent at
      `RecordResourceDownloadAction.php:29-34`.

- [x] **S2 — duplicated sentinel translation.** Fixed:
      `AnalyticsEventCountRowResource.php:22` is now a flat passthrough,
      and the `''` / `0` → `null` translation lives only in
      `ListEventCountsAction.php:53-63` (where the rollup-storage
      contract is owned).

- [x] **S4 — string magic in factory.** Fixed in
      `database/factories/AnalyticsDailyRollupFactory.php:26-27` — uses
      `EventType::DevotionalViewed->value` and
      `EventSubjectType::Devotional->value` to match
      `AnalyticsEventFactory`.

---

## Acknowledged

- [x] **S1 — `ResourceDownloadContextData` rename.** — acknowledged:
      DTO is now used across login/QR/reading-plan/ingest, name no
      longer reflects scope. Engineer flagged for the next analytics
      story; out of scope here per the prior reviewer's note.
- [x] **S3 — `period` field is decorative.** — acknowledged:
      `AnalyticsRangeQueryData::period` is echoed in `meta` but never
      drives grouping in any read action. Either drop the field or
      wire it into the actions in a follow-up; not blocking the
      ingest+rollup landing.
- [x] **Tripwire watch** (`.agent-workflow/CLAUDE.md` §7) — unchanged:
      admin endpoints are super-admin gated, not owner-gated. Owner-
      `authorize()` counter stays at 4/5.

---

## Notes (non-blocking)

- `AnalyticsEventQueryBuilder` and `AnalyticsDailyRollupQueryBuilder`
  are wired via `newEloquentBuilder` but the read actions all use
  `DB::table(...)` directly. The QueryBuilder scopes are dead code as
  shipped. Not blocking — the actions ship working SQL — but worth
  noting for the next analytics story: either route the actions
  through the QueryBuilders or delete the unused scopes.
- `RollupAnalyticsForDateAction` writes sentinels (`''` / `0`) for
  nullable rollup dimensions. The migration PHPDoc explains this
  correctly. Read endpoints translate sentinels → `null` (one place
  now, after S2). Future readers of the rollup tables outside this
  story should be aware.

---

## Verdict

**APPROVE** — move to `qa-ready`.
