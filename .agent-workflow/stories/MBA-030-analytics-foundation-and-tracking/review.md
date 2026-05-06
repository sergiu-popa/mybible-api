# Code Review — MBA-030

**Verdict:** REQUEST CHANGES (1 Critical, 4 Warnings, 4 Suggestions)

**Engineer follow-up (2026-05-06):** C1, W1, W2, W3, W4, S2, S4 addressed
in commit pass. S1 (DTO rename) and S3 (`period` field decorative) left
for a follow-up analytics story per the reviewer's "out of scope here"
notes.

The architecture matches the plan (polymorphic store + 3 rollup tables +
queued ingest + admin reads + server-side emissions). One real
correctness bug in the per-plan funnel filter blocks approval; the rest
are tightenings, dead-code cleanups, and test gaps.

---

## Critical

### C1. `BuildReadingPlanFunnelAction` plan_id filter is incorrect for 3 of 4 lifecycle events

`app/Domain/Analytics/Actions/BuildReadingPlanFunnelAction.php:36-42`

```php
$base->where(function ($q) use ($planId): void {
    $q->where('subject_id', $planId)
        ->orWhereRaw("JSON_EXTRACT(metadata, '$.plan_id') = ?", [$planId]);
});
```

Both clauses of this OR are wrong for non-`started` events:

1. **`subject_id = planId` is a category error.** Reading-plan events
   use `subject_type='reading_plan_subscription'` and
   `subject_id=subscription_id` (see
   `StartReadingPlanSubscriptionAction:76`,
   `CompleteReadingPlanSubscriptionDayAction:43`,
   `AbandonReadingPlanSubscriptionAction:48`,
   `FinishReadingPlanSubscriptionAction:55` — all pass the `$subscription`
   model as the morph subject). So `subject_id = planId` matches
   subscriptions whose primary key happens to equal the plan id — i.e.
   arbitrary unrelated rows.

2. **`metadata.plan_id` is only emitted on `started`.** Per
   `EventType::metadataRules()`,
   `reading_plan.subscription.started` carries `plan_id` + `plan_slug`
   in metadata, but the three other lifecycle types (`day_completed`,
   `abandoned`, `completed`) carry `day_position` /
   `at_day_position` / nothing — never `plan_id`. The matching emission
   Actions confirm this (e.g. `CompleteReadingPlanSubscriptionDayAction`
   only writes `day_position` + `subscription_age_days`).

Net effect: when an admin requests `?plan_id=<X>`, the funnel returns
`started` events correctly, but `day_completed`, `abandoned`, and
`completed` are filtered out almost entirely (only fluke
`subscription_id == plan_id` collisions slip through). The dashboard's
headline "per-plan dropoff" use case (AC §20) therefore shows nonsense.
Caught in tests: no — `AdminAnalyticsEndpointsTest::test_reading_plan_funnel_returns_shape:135`
exercises the endpoint without `plan_id`, so the bug is invisible.

**Fix.** Two viable paths:

- **Preferred:** add `plan_id` (and ideally `plan_slug`) to the metadata
  emitted by `CompleteReadingPlanSubscriptionDayAction`,
  `AbandonReadingPlanSubscriptionAction`, and
  `FinishReadingPlanSubscriptionAction`. Then the funnel can simply
  filter `WHERE JSON_EXTRACT(metadata, '$.plan_id') = ?` and drop the
  `subject_id` branch entirely. Update
  `EventType::metadataRules()` for the three non-`started` types so
  ingest validation matches what server-side emission produces.
- **Alternative:** join `analytics_events` to
  `reading_plan_subscriptions` via `subject_id` to derive the plan id at
  query time. Heavier read path; keeps the metadata schema unchanged.

Add a test that seeds events for two plans (each with started +
day_completed + abandoned), calls the funnel with `plan_id` set, and
asserts each lifecycle counter is per-plan rather than global.

---

## Warning

### W1. Server-side emission test coverage is short of plan §33

`tests/Feature/Domain/Analytics/ServerSideEmissionsTest.php`

Plan task §33 calls for tests covering "login, start/complete/abandon/finish
reading-plan, QR scan, resource downloads ×3" — eight emission points.
Only three are exercised (login, qr scan,
`resources.downloads.store`). Reading-plan lifecycle (4 events) and the
two other download endpoints (`resource-books.downloads.store`,
`resource-books.chapters.downloads.store`) have no assertion that an
`analytics_events` row is written.

**Fix.** Add tests for each missing emission point. The reading-plan
tests will also exercise the C1 metadata fix once it lands (assert
`metadata.plan_id` is present on every lifecycle event row).

### W2. `IngestAnalyticsEventsRequest::withValidator` has dead `$rules === []` branch

`app/Http/Requests/Analytics/IngestAnalyticsEventsRequest.php:99-102`

```php
$rules = $type->metadataRules();
if ($rules === []) {
    continue;
}
```

`EventType::metadataRules()` always returns at least
`['metadata' => ['nullable', 'array']]` via the `default` arm — there
is no enum case that returns `[]`. The early-`continue` cannot fire.

**Fix.** Either delete the dead check or change `default` to return
`[]` (and document the intent: "free-form metadata, skip per-row
validation"). Mild preference for the latter — running a no-op
sub-validator on every event in a 100-event batch is wasted work.

### W3. `subject_id` validation does not bound to a real model

`app/Http/Requests/Analytics/IngestAnalyticsEventsRequest.php:33`

`'events.*.subject_id' => ['nullable', 'integer', 'min:1']` — anything
≥ 1 passes. Per AC §13 events are typed against an allowlist subject,
but a client can post `subject_id: 999999999` for a nonexistent
devotional and that lands in the event store. Acceptable for ingest
performance, but it means rollup rows can carry phantom subject_ids
that never resolve through the morph map.

**Fix.** Either accept this trade-off explicitly with a comment in
`metadataRules()` or the request, OR add a soft existence check (deferred
to a Listener so the HTTP path stays cheap). Not blocking — but the
project guidelines call out "constant fields that force churn" and a
phantom subject_id is the usability dual of that. Acknowledge in
`review.md` if you keep the loose check.

### W4. `RecordAnalyticsEventAction` falls back to FQCN when alias is missing

`app/Domain/Analytics/Actions/RecordAnalyticsEventAction.php:38-44`

```php
if ($subject !== null) {
    $alias = Relation::getMorphAlias($subject::class);
    $subjectType = is_string($alias) && $alias !== $subject::class
        ? $alias
        : $subject::class;
    ...
}
```

If a future Action passes a model whose class is not registered in
`EventSubjectType::morphMap()`, this silently writes
`App\Domain\…\Foo` (the FQCN) into `analytics_events.subject_type`.
The rollup then groups by that FQCN, the read endpoints surface it as
a sentinel mismatch, and admin dashboards display garbage. The ingest
endpoint cannot produce this state (its validation pins `subject_type`
to the enum), but the server-side emission paths can.

**Fix.** Throw an `InvalidArgumentException` on FQCN fallback, the
same way `RecordResourceDownloadAction:29-34` already does. That keeps
the morph-map invariant load-bearing. (The existing precedent is the
right pattern to copy.)

---

## Suggestion

### S1. `ResourceDownloadContextData` name is misleading after this story

`app/Domain/Analytics/DataTransferObjects/ResourceDownloadContextData.php`

The DTO is now passed everywhere (`LoginUserAction`, `RecordQrCodeScanAction`,
all four reading-plan Actions, the ingest controller, the rollup
emission path). Its name still suggests a download-only payload. A
follow-up rename to `ClientContextData` or `AnalyticsClientContext` would
match the actual scope. Out of scope here; flag for the next analytics
story so it doesn't keep accreting awkward call-sites.

### S2. `ListEventCountsAction` duplicates sentinel translation that lives in the resource layer

`app/Domain/Analytics/Actions/ListEventCountsAction.php:53-63`

The action translates `''` / `0` sentinels to `null` before returning.
`AnalyticsEventCountRowResource` then re-passes the value through
`array_key_exists`. Pick one layer for the translation; doing it in
both is harmless today but the next reader will assume the sentinel
contract is enforced somewhere it isn't.

**Fix.** Translate only in the action (it owns the rollup-storage
contract); keep the resource a flat passthrough.

### S3. Redundant `period` echoed in meta is decorative

`AnalyticsRangeQueryData::period` is not consumed by any action — every
read endpoint groups per day regardless of the requested period. The
field is purely an echo to the caller. Either drop the parameter
(rename `AnalyticsRangeQueryData` to two-field) or wire it into the
actions so the day/week/month grouping is real. Not a defect; just
noting that the contract overpromises.

### S4. `AnalyticsDailyRollupFactory` uses the literal `'devotional.viewed'` rather than `EventType::DevotionalViewed->value`

`database/factories/AnalyticsDailyRollupFactory.php:24-26`

Same string magic the project guidelines flag. The sister factory
`AnalyticsEventFactory` correctly references the enum. Match it.

---

## Acknowledged

- The `Tripwire watch` register in `.agent-workflow/CLAUDE.md` §7 is
  unaffected — admin endpoints are super-admin gated, not owner-gated;
  no new copies of the owner-`authorize()` block. Counter stays at 4/5.

## Verdict

**REQUEST CHANGES** — C1 must be fixed (and tested) before approval. W1
must also be addressed since plan §33 explicitly enumerates the missing
emission tests. W2-W4 and the suggestions can be batched into the same
re-engineer pass.
