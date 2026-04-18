# Story: MBA-001-reading-plan-feature

## Title
Reading Plans — browse, subscribe, track daily progress

## Status
`draft`

## Description
A **Reading Plan** is a structured, multi-day guide that combines rich text content and Bible passage references. Plans are multilingual (name, description, image, and HTML fragments are translated per language), but Bible references are translation-agnostic and stored as global reference strings.

Users interact with plans through **subscriptions**. Starting a plan creates a subscription tied to a start date, and each day in the plan gets a calculated `scheduled_date`. A user can subscribe to the same plan multiple times — each attempt is a separate, independent subscription.

Completion is non-sequential: any day can be marked complete in any order. When all days are completed, the user can explicitly finish the plan. Users may also abandon a subscription without deleting it, preserving data for metrics.

## Acceptance Criteria

### Browsing
1. An unauthenticated client (API key) can list published reading plans with pagination, filtered by language.
2. An unauthenticated client can view a single reading plan with all its days and fragments, filtered by language.
3. Multilingual fields (`name`, `description`, `image`, `html` fragments) return the requested language; if unavailable, fall back to `en`.
4. `references`-type fragments are returned as-is regardless of the requested language.

### Subscriptions (JWT required)
5. An authenticated user can start a plan, optionally providing a `start_date` (defaults to today).
6. Starting a plan creates a subscription with status `active` and generates `subscription_days` with `scheduled_date = start_date + position - 1` for each day.
7. A user can have multiple active subscriptions to the same plan.
8. When listing or viewing a plan, authenticated users see their subscriptions (active, completed, abandoned) with progress (`completed_days / total_days`).

### Day completion
9. An authenticated user can mark any day in their subscription as completed, in any order.
10. Completing an already-completed day is idempotent — returns `200 OK` with existing data.

### Rescheduling
11. An authenticated user can change the start date of their subscription.
12. Rescheduling anchors the new date to the first uncompleted day (by position), then recalculates all subsequent uncompleted days sequentially.
13. Already completed days retain their original `scheduled_date`.

### Finishing & abandoning
14. An authenticated user can finish a subscription only when all days are completed; otherwise the API returns `422` listing the pending days.
15. Finishing sets `status = completed` and `completed_at = now()`.
16. An authenticated user can abandon a subscription, setting `status = abandoned` without soft-deleting it.

### Authorization
17. Write endpoints (start, complete, reschedule, finish, abandon) require JWT authentication.
18. A user can only modify their own subscriptions (ownership check).
19. Read endpoints accept both API key and JWT. Subscription data is only included in responses when authenticated via JWT.

## Scope

### In Scope
- All five database tables: `reading_plans`, `reading_plan_days`, `reading_plan_day_fragments`, `reading_plan_subscriptions`, `reading_plan_subscription_days`.
- API endpoints: list plans, get plan, start plan, complete day, change start date, finish plan, abandon subscription.
- Language resolution with fallback.
- Soft deletes on `reading_plans` and `reading_plan_subscriptions`.
- Seeders with a 7-day example plan for development and testing.

### Out of Scope
- Admin CRUD for creating/editing plans (separate story).
- Push notifications or reminders.
- Metrics dashboards (the data model supports metrics, but reporting is a separate story).
- Caching layer.

## Technical Notes

### Bible reference format
References are stored as JSON arrays of strings following the format `BOOK.CHAPTER[-CHAPTER_END][:VERSE_START[-VERSE_END]]`. Examples: `GEN.1-2`, `MAT.5:27-48`, `PRO.1:1-6`. Book codes follow an existing predefined set in the system. References are parsed at read time for the API response but stored as plain strings.

### Data model highlights
- `reading_plan_day_fragments` has a `type` enum (`html` or `references`) and a polymorphic `content` JSON column: multilingual HTML when type is `html`, an array of reference strings when type is `references`.
- `reading_plan_subscription_days` tracks per-day completion within a subscription. `scheduled_date` is derived from the subscription's `start_date` and the day's `position`.
- Unique constraints: `(reading_plan_id, position)` on days, `(reading_plan_day_id, position)` on fragments, `(subscription_id, day_id)` on subscription days.

### Authorization model
- Two auth mechanisms: API key (public/unauthenticated) and JWT (authenticated user).
- API key requests get read-only access without subscription data.
- JWT requests get full access; write operations require ownership of the subscription.

### Rescheduling logic
When a user changes `start_date`, the system finds the first uncompleted day by position, anchors it to the new date, and recalculates all subsequent uncompleted days sequentially. Completed days are never modified.

### Metrics considerations
The data model supports future metrics by preserving subscription lifecycle data: starts (subscription count), completions (status = completed), abandonment rate, drop-off by day (last completed day in abandoned subscriptions), and repeat readers (multiple completed subscriptions per user per plan).

## Mockups / References
- Full API request/response examples are documented in the original feature spec (`reading-plan-feature-spec.md`).
- Bible book codes follow the existing system convention (e.g. `GEN`, `EXO`, `MAT`, `PSA`, `PRO`).
