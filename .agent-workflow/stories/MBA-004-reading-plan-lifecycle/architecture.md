# Architecture: MBA-004 Reading Plan Lifecycle

## Overview

Round out the `ReadingPlanSubscription` aggregate shipped in MBA-003 with three
lifecycle endpoints — **reschedule**, **finish**, and **abandon** — by adding
one Action / Form Request / Controller trio per operation, one DTO, and two
small domain exceptions (one of which needs a custom renderer because it
carries a structured `pending_days` payload). No schema changes: every column
this story writes to (`start_date`, `status`, `completed_at`, subscription
days' `scheduled_date`) already exists.

## Domain placement

All new artifacts live under the existing `App\Domain\ReadingPlans\*` tree. No
new domain is introduced — reschedule/finish/abandon are state transitions on
the existing aggregate, not a new bounded context.

---

## Domain changes

### Migrations

**None.** The MBA-003 `reading_plan_subscriptions` migration already provides
`start_date`, `status`, and `completed_at nullable`. The
`reading_plan_subscription_days` migration already provides `scheduled_date`
and `completed_at`. MBA-004 writes to existing columns only.

### Models

**Unchanged.** `ReadingPlanSubscription` already casts `status` to
`SubscriptionStatus` and `completed_at` to `datetime`. `ReadingPlanSubscriptionDay`
already casts `scheduled_date` and `completed_at`. Relations (`days`,
`readingPlanDay`) are in place.

### Enum

**`SubscriptionStatus`** already exposes `Active`, `Completed`, and `Abandoned`.
No changes.

### QueryBuilder

**No new methods.** Each of the three lifecycle actions runs a focused,
single-use query against the subscription's `days` relation (or a fresh
builder) — extracting them to `ReadingPlanSubscriptionQueryBuilder` would
violate the Architect rule *"Every QueryBuilder method must be called by
more than one consumer"*; one caller per scope doesn't earn the abstraction.

### Factories

**Unchanged.** `ReadingPlanSubscriptionFactory` already has `active()`,
`completed()`, `abandoned()`; `ReadingPlanSubscriptionDayFactory` has
`pending()`, `completed()`. Sufficient for the new tests.

### Exceptions (new)

**`App\Domain\ReadingPlans\Exceptions\SubscriptionNotCompletableException`**
(`final`):

- Constructor: `public function __construct(public readonly array $pendingPositions)`.
- `getMessage()` returns `"Subscription cannot be finished while days are pending."`.
- Does **not** extend `HttpException` — its payload (`pending_days`) is
  structured and is rendered by a dedicated block in `bootstrap/app.php`.

**`App\Domain\ReadingPlans\Exceptions\SubscriptionAlreadyCompletedException`**
(`final`):

- Extends `Symfony\Component\HttpKernel\Exception\HttpException`.
- Constructor: `public function __construct() { parent::__construct(422, 'Cannot abandon a completed subscription.'); }`.
- Rendered automatically by the existing catch-all `Throwable` renderer in
  `bootstrap/app.php` (it already inspects `HttpExceptionInterface` and uses
  the status code + message).

---

## Actions & DTOs

### DTOs

**`RescheduleReadingPlanSubscriptionData`** (`readonly final`):

```php
public function __construct(
    public ReadingPlanSubscription $subscription,
    public CarbonImmutable $startDate,
) {}
```

Built by `RescheduleReadingPlanSubscriptionRequest::toData()` from the
route-bound subscription and the validated `start_date`.

**Finish and Abandon** take no DTO — their only domain input is the
`ReadingPlanSubscription` resolved by the route binding. Following the same
judgement as MBA-003's day-completion action, a DTO would be ceremony without
value.

### Actions

**`RescheduleReadingPlanSubscriptionAction`**:

- Signature: `execute(RescheduleReadingPlanSubscriptionData $data): ReadingPlanSubscription`.
- Inside a `DB::transaction(...)`:
  1. Update `$subscription->start_date = $data->startDate` and save.
  2. Query uncompleted days with `readingPlanDay` joined/eager-loaded,
     ordered by `reading_plan_days.position` ASC. Use a
     `$subscription->days()->whereNull('completed_at')->with('readingPlanDay')->get()`
     then sort in PHP by `readingPlanDay.position`, OR a
     `join('reading_plan_days', …)->orderBy('reading_plan_days.position')`
     — the former is simpler and the N is small (≤ ~365). Use the
     `get()+sort()` approach.
  3. For each uncompleted day at index `$i`, set
     `$day->scheduled_date = $data->startDate->addDays($i)` and save.
- Returns the subscription with progress counts loaded (see **Response shape**
  below). Does **not** load `days`.

Completed days are never touched — they retain their original
`scheduled_date`.

**`FinishReadingPlanSubscriptionAction`**:

- Signature: `execute(ReadingPlanSubscription $subscription): ReadingPlanSubscription`.
- Idempotent for `Completed`: if `$subscription->status === Completed`,
  return the subscription unchanged (progress counts loaded) — no DB write,
  no overwrite of `completed_at`.
- Otherwise, fetch pending day positions (single query:
  `$subscription->days()->whereNull('completed_at')->join('reading_plan_days', …)->orderBy('reading_plan_days.position')->pluck('reading_plan_days.position')->all()`).
- If any pending positions remain, throw
  `SubscriptionNotCompletableException($pendingPositions)`.
- Otherwise update `status = Completed` and `completed_at = now()`, save,
  return with progress counts loaded.

**`AbandonReadingPlanSubscriptionAction`**:

- Signature: `execute(ReadingPlanSubscription $subscription): ReadingPlanSubscription`.
- If `$subscription->status === Completed`, throw
  `SubscriptionAlreadyCompletedException`.
- If `$subscription->status === Abandoned`, return unchanged (idempotent no-op,
  progress counts loaded).
- Otherwise set `status = Abandoned`, save, return with progress counts loaded.
- Does **not** soft-delete and does **not** touch `completed_at`.

### Response shape (progress-counts-only)

All three actions finish with:

```php
return $subscription->loadCount([
    'days',
    'days as completed_days_count' => fn ($q) => $q->whereNotNull('completed_at'),
]);
```

They do **not** load the `days` relation. The existing
`ReadingPlanSubscriptionResource::whenLoaded('days')` check therefore omits
the per-day array entirely — matching the story author's "summary only"
recommendation (story open question #2). The `progress` block stays accurate
because the `*_count` aggregates populate.

---

## Events & Listeners

**None.** MBA-003 deliberately didn't introduce events; MBA-004 holds the
same line. Downstream needs (notifications, streak tracking) will motivate
`SubscriptionFinished` / `SubscriptionAbandoned` / `SubscriptionRescheduled`
events in their own stories, not speculatively here.

---

## HTTP endpoints

All three live inside the existing authenticated group in `routes/api.php`:

```php
Route::middleware('auth:sanctum')
    ->prefix('reading-plan-subscriptions')
    ->name('reading-plan-subscriptions.')
    ->scopeBindings()
    ->group(function (): void {
        // existing: days.complete …
        Route::patch('{subscription}/start-date',  RescheduleReadingPlanSubscriptionController::class)
            ->name('reschedule');
        Route::post('{subscription}/finish',       FinishReadingPlanSubscriptionController::class)
            ->name('finish');
        Route::post('{subscription}/abandon',      AbandonReadingPlanSubscriptionController::class)
            ->name('abandon');
    });
```

| Method | Path | Controller | Form Request | Resource | Middleware |
|---|---|---|---|---|---|
| PATCH | `/api/v1/reading-plan-subscriptions/{subscription}/start-date` | `RescheduleReadingPlanSubscriptionController` | `RescheduleReadingPlanSubscriptionRequest` | `ReadingPlanSubscriptionResource` | `auth:sanctum` |
| POST  | `/api/v1/reading-plan-subscriptions/{subscription}/finish`       | `FinishReadingPlanSubscriptionController`     | `FinishReadingPlanSubscriptionRequest`     | `ReadingPlanSubscriptionResource` | `auth:sanctum` |
| POST  | `/api/v1/reading-plan-subscriptions/{subscription}/abandon`      | `AbandonReadingPlanSubscriptionController`    | `AbandonReadingPlanSubscriptionRequest`    | `ReadingPlanSubscriptionResource` | `auth:sanctum` |

### Route bindings (scope-aware)

- **`{subscription}`:** resolved by default Laravel binding. Soft-deleted
  subscriptions are already excluded. Ownership is **not** part of the binding
  — same reason as MBA-003: we want 403 (not 404) for "not your subscription".
  Ownership is enforced in each Form Request's `authorize()`.
- The `->scopeBindings()` call on the group is inherited from MBA-003's
  day-completion route. For the three new routes there is no nested child
  binding to scope, so the call is harmless on them.

### Form Requests

Each Form Request owns authorization + validation for one endpoint. The
owner check (`$this->route('subscription')->user_id === $this->user()->id`)
is duplicated across the three new requests and the existing MBA-003
`CompleteReadingPlanSubscriptionDayRequest` — four copies of ~10 lines. A
trait would be possible but crosses a shipped story's code and saves little;
keep inline for now. If a fifth owner-gated endpoint arrives, extract then.

**`RescheduleReadingPlanSubscriptionRequest`**:

- Rules: `start_date` → `required|date|after_or_equal:today` — see R2.
- `authorize()` → owner check.
- `toData(ReadingPlanSubscription $subscription): RescheduleReadingPlanSubscriptionData`
  returns the DTO using `CarbonImmutable::parse($this->validated('start_date'))`.

**`FinishReadingPlanSubscriptionRequest`**:

- Rules: `[]` (empty body).
- `authorize()` → owner check.

**`AbandonReadingPlanSubscriptionRequest`**:

- Rules: `[]` (empty body).
- `authorize()` → owner check.

### Resources

**`ReadingPlanSubscriptionResource`** — reused as-is from MBA-003. The
`progress` block fills from `*_count` aggregates; `days` is omitted whenever
the relation is not loaded. No changes needed.

### Controllers

All three are single-action invokable controllers following the MBA-003
pattern (`final`, no business logic, DTO/route-bound model straight into the
Action, wrap the model in a Resource, return the Resource).

- `RescheduleReadingPlanSubscriptionController` — `PATCH`, calls `$request->toData($subscription)`, returns `ReadingPlanSubscriptionResource::make(...)`.
- `FinishReadingPlanSubscriptionController` — `POST`, calls the action with the subscription directly.
- `AbandonReadingPlanSubscriptionController` — same shape as `Finish`.

---

## Exception handler wiring

One new block in `bootstrap/app.php`, placed before the catch-all `Throwable`
renderer:

```php
$exceptions->render(function (SubscriptionNotCompletableException $e, Request $request) {
    return response()->json([
        'message' => $e->getMessage(),
        'pending_days' => $e->pendingPositions,
    ], 422);
});
```

`SubscriptionAlreadyCompletedException` needs **no** dedicated renderer — it
implements `HttpExceptionInterface` via Symfony `HttpException`, so the
existing catch-all emits `{ "message": "Cannot abandon a completed subscription." }`
with status 422.

---

## Risks & open questions

### R1 — Reschedule race condition between action steps is tolerable

The action updates `subscription.start_date`, then updates each uncompleted
day's `scheduled_date` inside the same `DB::transaction(...)`. A concurrent
`CompleteReadingPlanSubscriptionDay` call could, in principle, complete a
day while the reschedule is mid-flight. Because we operate on the loaded
snapshot of uncompleted days, a day completed after we read but before we
write would briefly get a new `scheduled_date` on top of its completion —
but the very next assertion the client makes against that row (via the
day-complete flow) is idempotent and `completed_at` wins semantically, so
the end state is consistent. The transaction keeps the per-row updates
atomic. No row-level locking is needed at this scale. **Decision:** do not
introduce `lockForUpdate()` or an advisory lock for v1; revisit if product
surfaces a real concurrency complaint.

### R2 — Reschedule `start_date` is constrained to `after_or_equal:today`

Story open question #1 asks whether `start_date` should be constrained.
**Decision (confirmed with story author during architecture review):** add
`after_or_equal:today`, matching the Start endpoint's rule.

Rationale: the catch-up scenario does **not** need a past date as input.
Example walk-through (Monday start, day 1 completed, days 2–3 skipped,
today is Thursday):

- Client sends `new_start_date = today = Thursday`.
- `subscription.start_date` is updated to Thursday (AC #2).
- Day 1 (completed) retains its original `scheduled_date = Monday` (AC #4).
- Day 2 — first uncompleted — re-anchors to Thursday.
- Day 3 — next uncompleted — re-anchors to Friday.
- Day 4 — Saturday, and so on.

A past `new_start_date` would imply "I was supposed to be on day N
yesterday"; the client can express the same intent with today's date (as
above) and the algorithm produces the correct forward schedule. Allowing a
past anchor would also let a client retroactively rewrite uncompleted days
into the past, which is meaningless (those days were never done) and could
confuse downstream reporting. Reject it.

Note: AC #1 in the story phrases the rule as `required|date`; adding
`after_or_equal:today` is a strict superset. Call this out in the PR
description so QA/Audit can trace the tightened rule back to this R2
resolution.

### R3 — Finish's pending-positions query touches a non-covered index

Pending positions come from a `WHERE reading_plan_subscription_id = ? AND
completed_at IS NULL` scan joined to `reading_plan_days` on `id` and
ordered by `position`. The existing index on
`(reading_plan_subscription_id, scheduled_date)` is not ideal for this
query, but the scan is bounded by one subscription's days (≤ ~365 rows) and
runs once per finish call. **Decision:** accept the cost; no new index in
this story. Revisit only if a "bulk auto-finish" feature ships.

### R4 — `SubscriptionNotCompletableException` is a reserved 422 channel

Wiring a dedicated renderer for this exception sets a precedent: any future
structured 422 (e.g. "cannot reschedule into the past for a streak-based
plan") will want the same pattern. The renderer is small and explicit; if
the pattern repeats, extract a `Render422WithData` trait/interface at that
time — not speculatively.

### R5 — Summary-only response means clients cannot see the new dates without a GET endpoint

The three endpoints return progress counts but not the per-day list. MBA-003
did not ship a `GET /reading-plan-subscriptions/{subscription}` detail
endpoint. A client that needs to render "see your new schedule" after a
reschedule has no way to fetch the re-anchored `scheduled_date`s without
re-subscribing (destructive). **Decision:** respect the story author's
explicit "summary only" recommendation; flag that a GET detail endpoint is
likely a follow-up story. Do **not** add it here.

### Open questions answered (from story)

1. **Past-date rule on reschedule:** `after_or_equal:today`, matching Start
   — see R2.
2. **Days in response:** summary only — see R5.

---

## Testing strategy

| Layer | Tool | Suite | Notes |
|---|---|---|---|
| New migrations | — | — | None — no schema changes. |
| Domain exceptions | — | — | Covered implicitly by Action unit tests asserting `instanceof` and `->pendingPositions`. A standalone exception-shape unit test is not worth the file. |
| `RescheduleReadingPlanSubscriptionAction` | PHPUnit unit | unit | Locks the reschedule algorithm: completed days keep their date, uncompleted days get consecutive dates from the new anchor in `position` order, `start_date` is updated even when `position 1` is completed. |
| `FinishReadingPlanSubscriptionAction` | PHPUnit unit | unit | Throws with correct pending positions; succeeds and sets `completed_at` + status when all days are done; idempotent on already-`Completed`. |
| `AbandonReadingPlanSubscriptionAction` | PHPUnit unit | unit | Rejects `Completed` with the 422 exception; idempotent for already-`Abandoned`; status flips correctly from `Active`. |
| `RescheduleReadingPlanSubscriptionRequest` | PHPUnit unit | unit | `start_date` required, must be a date, must be today or later (`after_or_equal:today`). Covers the pure validation surface in isolation. |
| `FinishReadingPlanSubscriptionRequest`, `AbandonReadingPlanSubscriptionRequest` | (no unit test) | — | Empty rules; the single-line `authorize()` is covered by the feature tests' 403 case, matching the decision for `CompleteReadingPlanSubscriptionDayRequest` in MBA-003. |
| `ReadingPlanSubscriptionResource` | (no new tests) | — | Unchanged; MBA-003's unit test still covers the progress fallback. |
| `PATCH …/start-date` | Feature | feature | Happy path (200 + status quo on completed days + new dates on uncompleted days + `start_date` updated); catch-up walk-through (Monday start, day 1 completed, today = Thursday, new_start_date = today → day 1 stays Monday, day 2 = Thursday, day 3 = Friday); completed-`position-1` case (still re-anchors from new `start_date` for the first **uncompleted** day); 422 on past `start_date`; non-owner 403; missing Sanctum 401; missing `start_date` 422; soft-deleted `{subscription}` 404. |
| `POST …/finish` | Feature | feature | Happy path (200, `status=completed`, `completed_at` set); 422 with `pending_days` body when days remain; idempotent 200 on already-`Completed` with original `completed_at` preserved; non-owner 403; missing Sanctum 401. |
| `POST …/abandon` | Feature | feature | Happy path (200, `status=abandoned`, NOT soft-deleted row preserved); idempotent 200 on already-`Abandoned`; 422 on `Completed` subscription; non-owner 403; missing Sanctum 401. |

All feature tests use `Sanctum::actingAs($user)` — the same pattern MBA-003
established. `Carbon::setTestNow(...)` freezes time for the finish test to
assert exact `completed_at` round-tripping.
