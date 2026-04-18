# Story: MBA-003-reading-plan-subscriptions

## Title
Reading Plans — start a subscription and complete days

## Status
`done`

## Description
Build on the public catalog (MBA-001) and the auth foundation (MBA-002) to
let an authenticated user **subscribe** to a reading plan and **mark days as
completed** in any order.

A subscription captures the user's intent to read a plan starting from a
specific date. When created, the system materialises one row per plan day in
`reading_plan_subscription_days` with a derived `scheduled_date`. Day
completion is non-sequential and idempotent.

A user may subscribe to the same plan multiple times — each attempt is an
independent subscription, supporting future metrics on repeat readers.

This story delivers the **start** and **complete day** flows, plus surfacing
the user's subscriptions on plan responses. The lifecycle endpoints
(reschedule, finish, abandon) are split into MBA-004.

## Acceptance Criteria

### Subscriptions schema
1. New tables `reading_plan_subscriptions` and `reading_plan_subscription_days`
   exist with the schema described in Technical Notes. Soft deletes on
   `reading_plan_subscriptions`.

### Starting a plan (Sanctum required)
2. `POST /api/v1/reading-plans/{plan:slug}/subscriptions` starts a subscription
   for the authenticated user, optionally providing `start_date` (defaults to
   today). Past dates are rejected with `422`.
3. Starting a plan creates a subscription with `status = active` and generates
   one `reading_plan_subscription_day` per plan day with
   `scheduled_date = start_date + position - 1`.
4. A user may have multiple `active` subscriptions to the same plan.
5. Returns `201` with the subscription resource (id, plan_id, status,
   start_date, progress, days).

### Completing a day (Sanctum required, owner only)
6. `POST /api/v1/reading-plan-subscriptions/{subscription}/days/{day}/complete`
   marks the day as completed.
7. Completing an already-completed day is idempotent — returns `200 OK` with
   the existing payload (no `completed_at` overwrite).
8. Non-owner returns `403`. Day not belonging to the subscription returns `404`.

### Reading plan responses (auth-aware)
9. When the show or list endpoint from MBA-001 is called with a Sanctum
   token, the response includes a `subscriptions` array per plan containing
   only the current user's subscriptions (any status), each with progress
   (`completed_days / total_days`).
10. When called with API key only (no Sanctum token), `subscriptions` is
    omitted.

### Authorization
11. Both write endpoints require Sanctum (the `auth:sanctum` middleware from MBA-002).
12. Day-completion enforces ownership via the Form Request `authorize()`.

## Scope

### In Scope
- Two new tables (`reading_plan_subscriptions`, `reading_plan_subscription_days`).
- `SubscriptionStatus` enum (`active`, `completed`, `abandoned`).
- DTO + Action: `StartReadingPlanSubscriptionAction`, `CompleteSubscriptionDayAction`.
- `ReadingPlanSubscriptionQueryBuilder`.
- Two HTTP endpoints (start, complete day).
- Updates to `ReadingPlanResource` to conditionally include the user's
  subscriptions.
- Factory + state coverage; feature + unit tests.

### Out of Scope
- Reschedule, finish, abandon (MBA-004).
- Notifications/reminders for upcoming days.
- Aggregated metrics endpoints.

## Technical Notes

### `reading_plan_subscriptions`
| Column | Type | Notes |
|---|---|---|
| `id` | bigIncrements | |
| `user_id` | foreignId | cascade on user delete |
| `reading_plan_id` | foreignId | restrict |
| `start_date` | date | |
| `status` | string | enum, default `active` |
| `completed_at` | timestamp | nullable (set by MBA-004 finish) |
| `timestamps` | | |
| `softDeletes` | | |

### `reading_plan_subscription_days`
| Column | Type | Notes |
|---|---|---|
| `id` | bigIncrements | |
| `reading_plan_subscription_id` | foreignId | cascade |
| `reading_plan_day_id` | foreignId | restrict |
| `scheduled_date` | date | |
| `completed_at` | timestamp | nullable |
| Unique | `(subscription_id, day_id)` | |
| Index | `(subscription_id, scheduled_date)` | |

### Progress shape
`progress: { "completed_days": 3, "total_days": 7 }`. Computed in the resource
or via a withCount on the QueryBuilder; prefer eager aggregation to avoid N+1.

### Past-date policy
`start_date` validation rule: `nullable|date|after_or_equal:today` (in the
caller's timezone — for v1, server timezone is fine; the open question can
land in a later i18n story).

## Dependencies
- **MBA-001** (catalog tables and reading-plan model must exist).
- **MBA-002** (the `auth:sanctum` middleware and `$request->user()` must be available).

## Open Questions for Architect
1. Should the `subscriptions` payload on `ReadingPlanResource` be paginated
   or capped if a user has dozens of historical subscriptions?
2. Are subscription rows hard-purged when a user deletes their account, or
   anonymised for metrics? (Today: cascade — re-confirm.)
