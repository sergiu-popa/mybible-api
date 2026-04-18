# Improvements — MBA-003-reading-plan-subscriptions

## Part 1 — Code Fixes

### Issues Addressed

| # | Issue (from audit) | Severity | Status | What was done |
|---|---|---|---|---|
| 1 | `ReadingPlanSubscriptionQueryBuilder::withDaysOrdered()` has no caller | Should Fix | Fixed | Removed the method from `app/Domain/ReadingPlans/QueryBuilders/ReadingPlanSubscriptionQueryBuilder.php`. MBA-004 can reintroduce it when an actual consumer lands. |
| 2 | `CompleteReadingPlanSubscriptionDayController::$subscription` param is unused and its purpose is not documented | Minor | Fixed | Added a three-line comment on the parameter explaining that it anchors the nested `scopeBindings()` chain and that removing it breaks the 404-on-cross-subscription guarantee. |
| 3 | `StartReadingPlanSubscriptionAction` assumes the caller loaded `plan.days` | Minor | Fixed | Added `$data->plan->loadMissing('days');` inside the transaction before the loop. Defensive, cost-free when the relation is already loaded. |
| 4 | `CompleteSubscriptionDayAction` name is inconsistent with its sibling `StartReadingPlanSubscriptionAction` (and with the matching controller + form request) | Minor | Fixed | Renamed to `CompleteReadingPlanSubscriptionDayAction` (`git mv` both the class and its unit test) and updated the controller import. Now all three write-side class names share the `ReadingPlanSubscription` prefix. |
| 5 | `ReadingPlan::resolveRouteBinding` published rule is a security primitive with only end-to-end coverage | Audit recommendation | Fixed | Added `tests/Unit/Domain/ReadingPlans/Models/ReadingPlanTest.php` with four cases: published slug resolves, draft returns null, published-status-without-`published_at` returns null, unknown slug returns null. A future `Route::model` swap or override regression will now trip a unit-level signal. |
| 6 | `ReadingPlan::resolveRouteBinding` always applies `->published()` (admin context would need drafts) | Minor | Deferred | Not MBA-003 concern — no admin route exists yet. Revisit when an admin CRUD story lands; at that point the override may need a `$context` hint or the admin routes bypass the binding. Flagged in audit notes; no tracker ticket created since the triggering story doesn't exist. |

### Test Suite Results (after fixes)

- Total: 114 | Passed: 114 | Failed: 0 (previously 110 — added 4 `ReadingPlanTest` cases).
- 291 assertions.
- `make check` (lint + stan + tests): all green.
- No regressions in MBA-001 / MBA-002 / MBA-003 existing tests.

### Additional Changes

None beyond the audit findings. No refactors, no new features, no doc updates outside the story directory.

---

## Part 2 — Workflow Improvement Proposals

### High Impact

| # | File to change | Proposal | Reason |
|---|---|---|---|
| 1 | `~/.claude/agents/architect.md` | Add a rule: _QueryBuilder, Action, and DTO helpers listed in `architecture.md` must have at least one named consumer in `tasks.md`. If a helper is "for a future story," it belongs in that story's architecture, not this one._ | Architecture listed `withDaysOrdered()` in the QueryBuilder section. No MBA-003 task called it. Code Reviewer flagged it as dead (suggestion), QA noted it, Auditor re-flagged it as Should Fix, and Improver finally dropped it. That's four agent passes on the same dead method. Planning helpers for unspecified future stories is the upstream cause. |
| 2 | `~/.claude/docs/workflow.md` | Add a short "Post-APPROVE cleanup" note before the Review → QA transition: _If a Code Review verdict is APPROVE but the review contains unchecked Suggestions, the Engineer may opt to address them before QA runs. Otherwise, those items will land in the Auditor's "Minor" list._ Either direction is fine — the point is that Suggestion items today have no owner between APPROVE and Audit, so they consistently survive QA and resurface in the audit report. | Reviewer flagged three Suggestion items (dead method, unused param, naming). QA passed without addressing them. Auditor re-flagged all three. Improver fixed all three. The same signal travelled through four artifacts. Naming an owner avoids that. |

### Medium Impact

| # | File to change | Proposal | Reason |
|---|---|---|---|
| 3 | `~/.claude/docs/laravel-beyond-crud-guidelines.md` (Naming Conventions table) | Extend the Action row: _Action class names should share a prefix with their matching Controller and Form Request when one exists (e.g. `StartReadingPlanSubscriptionAction` pairs with `StartReadingPlanSubscriptionController` / `StartReadingPlanSubscriptionRequest`). Avoid shortened sibling names._ | Architecture picked `CompleteSubscriptionDayAction` (short) while its sibling was `StartReadingPlanSubscriptionAction` (long). Review and Audit both flagged the asymmetry; the rule was implicit and judgment-call, so the Architect could justifiably pick either. Encoding the convention ends the debate. |
| 4 | `/Users/sergiu/Code/api/.agent-workflow/CLAUDE.md` (Engineer section) | Add: _Any controller parameter that exists purely to anchor Laravel's `scopeBindings()` chain must carry a short comment on the parameter explaining this — otherwise a future maintainer will delete it and silently break the nested 404 guarantee._ | The `$subscription` param was an obvious deletion target for three reviewers across Review, QA, and Audit. All three flagged it as "intentional, please comment." Engineer landed the pattern but not the comment. Encoding the "comment-on-anchor" rule in the project CLAUDE.md closes the loop at authoring time. |
| 5 | `~/.claude/agents/engineer.md` | Add to the list of deliverables for routing overrides: _When a model overrides `resolveRouteBinding` (or any route-model-binding hook), add a unit test on the model that covers the positive and negative path._ | The `published()`-only override is a security primitive (drafts invisible to write endpoints). End-to-end tests assert the 404 but don't localise the signal to the override itself. Auditor recommended a model-level test, Improver added one. Building this into Engineer's checklist catches it one stage earlier and prevents silent regressions if someone swaps back to `Route::model()` binding. |

### Low Impact

| # | File to change | Proposal | Reason |
|---|---|---|---|
| 6 | `~/.claude/docs/laravel-beyond-crud-guidelines.md` (DTO section) | Add a one-liner: _Skip the DTO when the Action has fewer than two non-model inputs (or when the only inputs are a resolved model + `now()`). Prefer passing the model directly over wrapping it._ | Architecture made an ad-hoc call to skip a DTO for `CompleteReadingPlanSubscriptionDayAction` ("Adding a DTO would be ceremony without value"). The call was correct but reader-unfriendly without a codified rule. Making the rule explicit lets future Architects skip ceremony without re-justifying each time. |

### Observations

- **Three agents flagged the same three items** (dead `withDaysOrdered`, unused `$subscription` param, Action naming). The workflow's feedback loop is Review → Engineer → Review for `REQUEST CHANGES` / `BLOCK` only; `APPROVE + Suggestions` has no loop. This story is a clean example: Suggestions turned into Minor audit findings, which the Improver then addressed. Proposal #2 above is the structural fix; worth tracking whether it repeats in MBA-004.
- **Architecture-only speculation is a recurring risk.** Besides `withDaysOrdered()`, Architecture mentioned "MBA-004 is the likely consumer" — fine as context, but the helper should ship with the consumer, not ahead of it. Proposal #1 addresses this.
- **`ReadingPlan::resolveRouteBinding` default-arg footgun** (`$field ??= $this->getRouteKeyName()` defaults to `id`) — surfaced only when unit-testing. The route-level binding never hits that default because `{plan:slug}` passes `'slug'` explicitly. Keeping this in mind when other models adopt the pattern would avoid a confusing test failure for the next author.
- **No QA or Audit regressions on MBA-001.** The middleware swap from `api-key` to `api-key-or-sanctum` went cleanly. The `WithApiKeyClient` trait plus the fall-through in `EnsureApiKeyOrSanctum` is a pattern worth repeating for future protected-catalog endpoints.
