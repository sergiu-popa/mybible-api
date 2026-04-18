# QA Report — MBA-003-reading-plan-subscriptions

## Test Suite Results
- Total: 110 | Passed: 110 | Failed: 0 | Skipped: 0
- Duration: ~1.14s
- Command: `make test` (`docker exec mybible-api-app php artisan test --compact`)

ReadingPlans-scoped subset (`--filter=ReadingPlan`): 53 passed, 144 assertions.

## Acceptance Criteria Verification

| # | Criterion | Test(s) | Status |
|---|-----------|---------|--------|
| 1 | Schema: `reading_plan_subscriptions` + `reading_plan_subscription_days` exist with correct FKs, unique `(subscription_id, day_id)`, composite indexes, soft deletes | Verified via `db:table` schema dump and `RefreshDatabase` in every feature/unit test | PASS |
| 2 | `POST /api/v1/reading-plans/{plan:slug}/subscriptions` accepts optional `start_date`, rejects past with 422 | `StartReadingPlanSubscriptionTest::test_it_creates_a_subscription_with_explicit_future_start_date`, `::test_it_rejects_a_past_start_date`, `StartReadingPlanSubscriptionRequestTest` (unit) | PASS |
| 3 | On start: `status = active`, one `subscription_day` per plan day, `scheduled_date = start_date + position - 1` | `StartReadingPlanSubscriptionTest::test_it_creates_a_subscription_with_days_defaulting_to_today`, `::test_it_creates_a_subscription_with_explicit_future_start_date`, `StartReadingPlanSubscriptionActionTest::test_it_creates_an_active_subscription_with_one_day_per_plan_day` | PASS |
| 4 | Multiple active subscriptions to the same plan allowed | `StartReadingPlanSubscriptionTest::test_it_allows_multiple_active_subscriptions_to_the_same_plan` | PASS |
| 5 | Returns `201` with subscription resource (id, plan_id, status, start_date, progress, days) | All `StartReadingPlanSubscriptionTest` cases assert `201` and resource shape | PASS |
| 6 | `POST /api/v1/reading-plan-subscriptions/{subscription}/days/{day}/complete` marks day complete | `CompleteReadingPlanSubscriptionDayTest::test_it_marks_the_day_as_completed`, `CompleteSubscriptionDayActionTest::test_it_sets_completed_at_when_pending` | PASS |
| 7 | Idempotent re-call returns `200` without overwriting `completed_at` | `CompleteReadingPlanSubscriptionDayTest::test_it_is_idempotent_and_preserves_original_completed_at`, `CompleteSubscriptionDayActionTest::test_it_preserves_the_original_completed_at_on_repeated_calls` | PASS |
| 8 | Non-owner → `403`; day from another subscription → `404` | `CompleteReadingPlanSubscriptionDayTest::test_it_returns_403_when_subscription_belongs_to_another_user`, `::test_it_returns_404_when_the_day_belongs_to_another_subscription` | PASS |
| 9 | Sanctum-authenticated catalog responses include `subscriptions` scoped to the current user with progress | `ListReadingPlansTest::test_it_returns_only_the_authenticated_users_subscriptions_with_progress`, `ShowReadingPlanTest::test_it_surfaces_only_the_authenticated_users_subscriptions` | PASS |
| 10 | API-key-only requests omit `subscriptions` entirely | `ListReadingPlansTest::test_it_omits_subscriptions_on_api_key_only_requests`, `ShowReadingPlanTest::test_it_omits_subscriptions_on_api_key_only_requests` | PASS |
| 11 | Both write endpoints require Sanctum (`auth:sanctum`) | `StartReadingPlanSubscriptionTest::test_it_rejects_missing_sanctum_token`, `CompleteReadingPlanSubscriptionDayTest::test_it_rejects_missing_sanctum_token` | PASS |
| 12 | Day-completion ownership enforced via Form Request `authorize()` | Covered by the 403 test in AC #8 (non-owner → 403 via `AuthorizationException` renderer) | PASS |

## Edge Cases Tested

| Case | Expected | Actual | Status |
|------|----------|--------|--------|
| Missing body on start (no `start_date`) | Defaults to today, 201 | Defaults to today, 201 | PASS |
| Explicit future `start_date` | 201, scheduled dates anchor to new start | 201, dates `+0/+1/+2` | PASS |
| `start_date` in the past | 422 with `errors.start_date` | 422 validation error | PASS |
| Unknown plan slug | 404 JSON envelope | 404 | PASS |
| Draft (unpublished) plan slug on start | 404 (published scope on route binding) | 404 | PASS |
| Draft plan on show | 404 | 404 | PASS |
| Missing Sanctum token on write endpoints | 401 | 401 | PASS |
| Cross-subscription day id (foreign `{day}` under `{subscription}`) | 404 via `scopeBindings()` | 404 | PASS |
| Non-owner subscription on day completion | 403 via FormRequest `authorize()` + `AuthorizationException` renderer | 403 | PASS |
| Completing an already-completed day | 200, original `completed_at` preserved | 200, preserved | PASS |
| Repeated starts of the same plan by same user | Two independent `active` subscriptions | 2 rows created | PASS |
| Catalog list with Sanctum filters per-user subscriptions | Only Alice's subscription visible to Alice | Only Alice's returned | PASS |
| Catalog list with API key only | `subscriptions` key absent from payload | Absent | PASS |
| Schema unique `(subscription_id, day_id)` | Compound unique index present | Confirmed via `db:table` | PASS |
| Soft-delete on `reading_plan_subscriptions` | `deleted_at` column present | Confirmed | PASS |
| FK `user_id` cascade / `reading_plan_id` restrict | Cascade + restrict | Confirmed via `db:table` | PASS |

## Regressions

None found. All MBA-001 catalog tests (`ListReadingPlansTest`, `ShowReadingPlanTest`) continue to pass after the middleware swap from `api-key` to `api-key-or-sanctum`. All MBA-002 auth tests remain green. Full suite: 110/110.

## Notes (carried from code review, not QA blockers)

- `ReadingPlanSubscriptionQueryBuilder::withDaysOrdered()` is defined but has no
  caller in MBA-003 (warning in `review.md`). Doesn't affect correctness; left
  for MBA-004 to consume or drop.
- `CompleteReadingPlanSubscriptionDayController` keeps an unused `ReadingPlanSubscription $subscription`
  parameter to anchor `scopeBindings()`. Intentional; a comment would help
  future maintainers.

## Verdict
QA PASSED — all 12 acceptance criteria covered and passing, no regressions, no critical findings carried over from code review.
