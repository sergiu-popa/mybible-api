# Code Review — MBA-004

## Summary
The implementation delivers reschedule, finish, and abandon lifecycle
endpoints that match the architecture and the story acceptance criteria
exactly: three Actions under `App\Domain\ReadingPlans\Actions`, the single
`RescheduleReadingPlanSubscriptionData` DTO, the two new exceptions
(one `HttpException` for the 422 "already completed" case, one plain
`RuntimeException` carrying `pending_days` for the finish-blocked case),
three invokable controllers, three Form Requests with owner
`authorize()`, and the exception renderer wired in `bootstrap/app.php`.
Strict types, `final` classes, explicit return types, and the "no else"
guideline are applied everywhere. Feature and unit tests cover the
documented scenarios — 61 ReadingPlanSubscription tests pass, and the
full gate (`make lint` + `make stan`) is green.

## Findings

### Critical (must fix before merge)
_None._

### Warning (should fix)
_None._

### Suggestion (nice to have)
- [ ] `app/Domain/ReadingPlans/Actions/RescheduleReadingPlanSubscriptionAction.php:18,29` — `Carbon::parse($data->startDate->toDateString())` and `Carbon::parse($data->startDate->addDays($index)->toDateString())` reparse the immutable date through a string round-trip. Laravel's `date` cast already normalises `CarbonImmutable` (and `Carbon`) on assignment, so the parse is redundant. Simpler: `$subscription->start_date = $data->startDate;` and `$day->scheduled_date = $data->startDate->addDays($index);`. Pure readability — no behavioural change.
- [ ] `app/Http/Requests/ReadingPlans/RescheduleReadingPlanSubscriptionRequest.php`, `FinishReadingPlanSubscriptionRequest.php`, `AbandonReadingPlanSubscriptionRequest.php` — the owner-authorization block is duplicated four times now (these three plus the MBA-003 `CompleteReadingPlanSubscriptionDayRequest`). The architecture explicitly defers extraction ("If a fifth owner-gated endpoint arrives, extract then.") so no action is required here, but it is worth flagging as the first eligible follow-up the next time a subscription endpoint lands.

## Checklist
- [x] All acceptance criteria from story.md are met
- [x] Architecture matches architecture.md
- [x] All tasks in tasks.md are completed
- [x] Tests exist for all new code
- [x] Tests pass (61 passed, 166 assertions on `filter=ReadingPlanSubscription`; lint + stan clean)
- [x] No security issues found (ownership enforced server-side in every Form Request, route binding excludes soft-deleted subscriptions, no mass-assignment surface beyond the existing `$guarded = []` + Form-Request-validated payloads)
- [x] No performance issues found (uncompleted-day save loop is bounded by one subscription's days — ≤ ~365 rows — and wrapped in a single transaction; `loadCount` uses aggregate subqueries, not `days` hydration; pending-positions join uses the existing `reading_plan_subscription_id` index as documented in R3)
- [x] API design (JSON-only envelope, correct verbs/status codes: PATCH 200 for reschedule, POST 200 for finish/abandon, 422 carries structured `pending_days`, 403/401 via the existing exception handlers)
- [x] Code style matches guidelines (strict types, final classes, explicit return types, no else, no inline `$request->validate()`, controllers delegate to Actions, models wrapped in Resources)

## Verdict
APPROVE
