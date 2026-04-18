# Audit Report — MBA-003-reading-plan-subscriptions

## Summary
The implementation hews tightly to the approved architecture and to the
project's Beyond CRUD + JSON-API conventions. The two new tables, the
`ReadingPlanSubscription` / `ReadingPlanSubscriptionDay` aggregate, the
`SubscriptionStatus` enum, the query builder, the DTO, both Actions, both
Form Requests, both Resources, both invokable controllers, the
`AuthorizationException` renderer, and the route wiring (with
`scopeBindings()` and the `published`-scoped `{plan:slug}` binding) are all
in place and exercised by 110 passing tests. Acceptance criteria 1–12 are
each backed by at least one automated test, including the idempotent
`completed_at` preservation, the 403 vs 404 split on day completion, the
draft-plan 404, and the `$this->when(...)`-based omission of `subscriptions`
on api-key-only requests. No critical findings. One previously-flagged dead
method (`withDaysOrdered()`) remains and is the single Should-Fix item.
Overall confidence: **HIGH**.

## Scores

| Dimension | Score (1–5) | Notes |
|---|---|---|
| Architecture Compliance | 4 | Domain layout, Action pattern, DTO, QueryBuilder, and resource/route bindings all match `architecture.md`. Dead `withDaysOrdered()` method carried over from review; no caller in this story. |
| Code Quality | 4 | `declare(strict_types=1)`, `final` classes, explicit return types, PHPDoc, no `else`, no magic strings. Slight coupling: `StartReadingPlanSubscriptionAction` assumes the caller loaded `plan.days` (controller does); a `loadMissing('days')` inside the Action would reduce that assumption. |
| API Design | 5 | Correct verbs (POST for state-changing ops), correct codes (201/200/401/403/404/422), consistent JSON envelope, `/api/v1` respected, day-completion is idempotent, nested `scopeBindings()` correctly distinguishes "not mine" (403) from "not nested" (404). |
| Security | 5 | `auth:sanctum` enforced on both write endpoints; ownership enforced in Form Request `authorize()`; `{plan:slug}` binding hides drafts; `scopeBindings()` prevents cross-subscription day access; no SQL-injection vectors (bulk insert uses bound parameters); `AuthorizationException` renderer correctly surfaces as 403 and falls back to a canned message when `$e->getMessage()` is empty. |
| Performance | 5 | `withProgressCounts()` uses SQL aggregates; single-round-trip bulk insert for subscription days; constrained eager-load bounded by page size on the list endpoint; supporting indexes (`(user_id, status)` on subscriptions, `(subscription_id, scheduled_date)` + unique `(subscription_id, day_id)` on subscription days). |
| Test Coverage | 5 | Action (happy + rollback), Action (idempotent), QueryBuilder (forUser + withProgressCounts), Form Request (validation + `startDate()` helper), Resource (aggregate-vs-collection progress). Feature tests cover 201/200/401/403/404/422, idempotency, multiple subscriptions, cross-subscription 404, Sanctum-aware resource inclusion on list + show, api-key-only omission. MBA-001 regressions confirmed green. |

## Issues Found

### Must Fix
_None._

### Should Fix
- [ ] `app/Domain/ReadingPlans/QueryBuilders/ReadingPlanSubscriptionQueryBuilder.php:29` —
      `withDaysOrdered()` is declared but has no in-story caller. The code
      review flagged the same thing; it's still here. Either drop it now
      (preferred — reintroduce in MBA-004 when the consumer lands) or add a
      one-line PHPDoc that names MBA-004 as the intended consumer so future
      maintainers don't think it's orphaned.

### Minor
- [ ] `app/Http/Controllers/Api/V1/ReadingPlans/CompleteReadingPlanSubscriptionDayController.php:17` —
      The `ReadingPlanSubscription $subscription` parameter is unused in the
      body; it exists to anchor the `scopeBindings()` chain. A one-line
      comment on the parameter would prevent a future maintainer from
      deleting it and silently breaking nested scope resolution. Flagged in
      the code review; intentional per QA.
- [ ] `app/Domain/ReadingPlans/Models/ReadingPlan.php:79` — The
      `resolveRouteBinding` override always applies `->published()`,
      swallowing any future admin-context binding requirements. Not a
      MBA-003 concern (no admin route exists yet); worth revisiting if
      an admin-facing CRUD story ever needs to bind by slug in a context
      that must see drafts.
- [ ] `app/Domain/ReadingPlans/Actions/CompleteSubscriptionDayAction.php:9` —
      Name is inconsistent with its sibling
      `StartReadingPlanSubscriptionAction`. Architecture picked the
      shorter form, so this stays as a judgment call — leave as-is or
      rename to `CompleteReadingPlanSubscriptionDayAction` for
      symmetry with the matching controller and Form Request names.
- [ ] `app/Domain/ReadingPlans/Actions/StartReadingPlanSubscriptionAction.php:26` —
      The Action iterates `$data->plan->days` assuming the caller loaded
      it. That assumption holds today (the controller calls
      `$plan->load('days')` before building the DTO), but a defensive
      `$data->plan->loadMissing('days')` inside the Action would make
      the unit clearer without a measurable cost.

## Recommendations
- **Drop unused builder methods at the author's site.** If `withDaysOrdered()`
  is for MBA-004, land it with MBA-004. Dead methods accumulate faster
  than they're pruned, and the lint stack won't flag them.
- **Park the route-binding publish rule in a test.** The `resolveRouteBinding`
  override is a quiet security primitive (drafts invisible to write
  endpoints). A regression test that binds the route and asserts draft
  slugs 404 would catch a future change that bypasses the override (e.g.
  someone switching back to `Route::model` binding). Current coverage
  asserts the endpoint's 404 end-to-end — good — but a model-level test
  would localise the signal.
- **Consider an assertion on `$guarded = []` model safety.** The project
  convention is clear, but auditing downstream, it's worth adding a
  phpstan-level rule (or a code review checklist entry) that flags any
  controller/action that passes user input into `::create($request->all())`
  straight through. Current code doesn't do that — every field into
  `ReadingPlanSubscription::query()->create([...])` is an Action-side
  literal — so the posture is sound; the recommendation is preventative.

## Verdict
**AUDIT PASSED** — 0 Must-Fix, 1 Should-Fix (dead method cleanup), 4 Minor.
The story is ready to be marked `audited`.
