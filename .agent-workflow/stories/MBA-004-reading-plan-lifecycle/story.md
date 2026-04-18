# Story: MBA-004-reading-plan-lifecycle

## Title
Reading Plans — reschedule, finish, abandon

## Status
`in-review`

## Description
Round out the subscription model from MBA-003 with the three lifecycle
operations a user needs to manage their progress: change the start date,
declare the plan finished, and walk away from a plan they no longer want to
continue.

Rescheduling is non-trivial: it must respect days the user has already
completed (those keep their original `scheduled_date`) and re-anchor only the
remaining uncompleted days. Finishing is gated on every day being completed.
Abandoning is a soft state change — the row is preserved for metrics rather
than deleted.

## Acceptance Criteria

### Rescheduling (Sanctum required, owner only)
1. `PATCH /api/v1/reading-plan-subscriptions/{subscription}/start-date`
   accepts a new `start_date` (`required|date`).
2. The subscription's `start_date` is updated to the new value.
3. The first **uncompleted** day (by `position`) is anchored to the new
   `start_date`. Subsequent uncompleted days (in `position` order) are
   assigned consecutive `scheduled_date` values from that anchor.
4. Already completed days **retain** their original `scheduled_date` — they
   are never modified.
5. Non-owner returns `403`. Returns `200` with the updated subscription
   resource.

### Finishing (Sanctum required, owner only)
6. `POST /api/v1/reading-plan-subscriptions/{subscription}/finish` marks the
   subscription as completed.
7. Finish is allowed only when **every** subscription day has `completed_at`
   set. Otherwise the API returns `422` with body
   `{ message, pending_days: [<positions>] }`.
8. On success: `status = completed`, `completed_at = now()`, returns `200`
   with the subscription resource.
9. Finishing an already-`completed` subscription returns `200` (idempotent,
   no `completed_at` overwrite).

### Abandoning (Sanctum required, owner only)
10. `POST /api/v1/reading-plan-subscriptions/{subscription}/abandon`
    sets `status = abandoned`. The row is **not** soft-deleted.
11. Returns `200` with the subscription resource.
12. Abandoning an already-`abandoned` subscription is a no-op `200`.
13. Abandoning a `completed` subscription is rejected with `422` (cannot
    abandon a finished plan).

### Authorization
14. All three endpoints require Sanctum (`auth:sanctum` middleware from MBA-002).
15. Ownership is enforced via Form Request `authorize()`.

## Scope

### In Scope
- Three Actions: `RescheduleSubscriptionAction`, `FinishSubscriptionAction`,
  `AbandonSubscriptionAction`.
- `RescheduleSubscriptionData` DTO.
- `SubscriptionNotCompletableException` mapped to `422` with `pending_days`.
- Three HTTP endpoints with Form Requests and Resources.
- Unit tests for each action and feature tests for each endpoint.

### Out of Scope
- Restarting an abandoned subscription (would be a separate "resume" story
  if the product wants it).
- Bulk operations.
- Admin overrides.

## Technical Notes

### Rescheduling algorithm
```
input: subscription with N subscription_days
output: updated scheduled_dates, completed days untouched

uncompleted = subscription_days
    .where(completed_at IS NULL)
    .orderBy(reading_plan_days.position)

for index, day in enumerate(uncompleted):
    day.scheduled_date = newStartDate + index days
```

`subscription.start_date` is updated to `newStartDate` even if the first
position day is already completed (the `start_date` is the user's stated
intent; the schedule mapping handles the completed/uncompleted split).

### Finish exception payload
`SubscriptionNotCompletableException` carries `array $pendingPositions`. The
exception handler renders:
```json
{
  "message": "Subscription cannot be finished while days are pending.",
  "pending_days": [3, 5, 7]
}
```
Status `422`. Wire the renderer in `bootstrap/app.php`.

### Abandon vs. soft-delete
Abandoning is a status flip only. Soft-delete on
`reading_plan_subscriptions` is reserved for hard-removal scenarios (user
account deletion, GDPR erasure) — separate concern.

## Dependencies
- **MBA-003** (subscriptions and subscription days must exist; the
  `Active` status must be the starting state).
- Transitively: MBA-001, MBA-002.

## Open Questions for Architect
1. When rescheduling, do we constrain the new `start_date` (e.g. cannot be
   in the past)? Today: no constraint beyond `date`.
2. After a finish/abandon, should the response include the list of all days
   (potentially large) or just the summary? Recommendation: summary only,
   matches MBA-003 default.
