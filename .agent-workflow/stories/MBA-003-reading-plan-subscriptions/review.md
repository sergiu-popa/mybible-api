# Code Review — MBA-003-reading-plan-subscriptions

## Summary
The implementation closely tracks the approved architecture: the two new
tables, `ReadingPlanSubscription` / `ReadingPlanSubscriptionDay` models,
`SubscriptionStatus` enum, query builder, DTO, two actions, two form
requests, two resources, two invokable controllers, the `AuthorizationException`
renderer, and the route wiring (with `scopeBindings()` for the nested
`{subscription}/days/{day}` pair) are all in place. The MBA-001 catalog
endpoints flip to `api-key-or-sanctum` and conditionally eager-load the
current user's subscriptions exactly as specified. `make check` passes
(lint, stan level, 110 tests / 286 assertions green). Acceptance criteria
1–12 are each covered by at least one test, including the idempotent
`completed_at` preservation, 403 vs 404 split, draft-plan 404, and the
`$this->when(...)` omission on api-key-only requests. No critical
issues; one minor dead-code observation and a couple of small polish
suggestions below.

## Findings

### Critical (must fix before merge)
_None._

### Warning (should fix)
- [ ] `app/Domain/ReadingPlans/QueryBuilders/ReadingPlanSubscriptionQueryBuilder.php:29` —
  `withDaysOrdered()` is declared but never called (the action uses
  `$plan->days`, which already orders via the `days()` relation
  `orderBy('position')`, and the resource reads the counts via
  `withProgressCounts`). Architecture §QueryBuilder lists it, but nothing
  in MBA-003 needs it. Either drop it or note that MBA-004 is the consumer
  — dead builder methods accumulate quickly. Suggested fix: remove for
  now; re-introduce when a caller lands in MBA-004.

### Suggestion (nice to have)
- [ ] `app/Http/Controllers/Api/V1/ReadingPlans/CompleteReadingPlanSubscriptionDayController.php:17` —
  The `ReadingPlanSubscription $subscription` parameter is injected purely
  to anchor the `scopeBindings()` chain and is never read in the body. That
  is fine, but a one-line comment explaining why it stays in the signature
  would prevent a future maintainer from deleting it and breaking the
  nested scope resolution.
- [ ] `app/Domain/ReadingPlans/Actions/CompleteSubscriptionDayAction.php:9` —
  Naming is inconsistent with its sibling
  `StartReadingPlanSubscriptionAction`. Both act on the
  `ReadingPlanSubscription` aggregate; renaming to
  `CompleteReadingPlanSubscriptionDayAction` would match the controller
  and form-request names. Architecture picked the shorter name, so this
  is a judgment call — leave if you prefer to stay aligned with the
  architecture doc verbatim.
- [ ] `app/Domain/ReadingPlans/Models/ReadingPlan.php:79` — The
  `resolveRouteBinding` override silently swallows an explicit `$field`
  argument and always applies `->published()`. If a later route ever
  binds `ReadingPlan` in an admin context that needs to address drafts,
  the override becomes a footgun. Not actionable for MBA-003, just flag
  for future admin-facing stories.

## Checklist
- [x] All acceptance criteria from story.md are met
- [x] Architecture matches architecture.md
- [x] All tasks in tasks.md are completed
- [x] Tests exist for all new code
- [x] Tests pass (`make check`: lint + phpstan + 110 tests green)
- [x] No security issues found (authorization via FormRequest ownership
      check; scoped bindings prevent cross-subscription day access; mass
      assignment uses existing `$guarded = []` project convention)
- [x] No performance issues found (bulk `DB::table(...)->insert()` for day
      generation; `withCount` aggregates for progress; constrained
      eager-load on the list endpoint is bounded by page size)
- [x] No inline `$request->validate()` — both endpoints use dedicated
      Form Requests
- [x] Controllers delegate all business logic to Actions
- [x] Eloquent models are never returned directly — all wrapped in
      Resources
- [x] Code style matches guidelines (strict types, `final` classes,
      explicit return types, no magic strings, no `else`)
- [x] Public API contract: no constant-valued fields detected
      (subscription `status` varies across Active/Completed/Abandoned;
      day `completed_at` can be null or a timestamp)

## Verdict
APPROVE
