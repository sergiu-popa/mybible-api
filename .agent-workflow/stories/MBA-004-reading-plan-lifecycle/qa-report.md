# QA Report — MBA-004

## Test Suite Results
- Total: 149 | Passed: 149 | Failed: 0 | Skipped: 0
- Duration: 1.41s
- Command: `make test` (inside `mybible-api-app`)
- MBA-004 slice (`--filter=RescheduleReadingPlanSubscription|FinishReadingPlanSubscription|AbandonReadingPlanSubscription`): 35 passed, 106 assertions, 0.77s.

## Acceptance Criteria Verification

### Rescheduling (AC 1–5)
| # | Criterion | Test exists | Status |
|---|-----------|-------------|--------|
| 1 | `PATCH /api/v1/reading-plan-subscriptions/{subscription}/start-date` accepts `start_date` (required, date) | ✅ `RescheduleReadingPlanSubscriptionRequestTest::test_it_fails_when_start_date_is_missing`, `…_is_not_a_date`; route confirmed via `route:list` | PASS |
| 2 | Subscription's `start_date` is updated to the new value | ✅ Feature `…catch_up_walk_through`, `…updates_start_date_when_position_one_is_completed`; Unit `…updates_start_date_even_when_position_one_is_completed` | PASS |
| 3 | First uncompleted day is anchored to new `start_date`; subsequent uncompleted days get consecutive dates | ✅ Feature `…reanchors_uncompleted_days_and_preserves_completed_ones`; Unit `…reanchors_uncompleted_days_consecutively_from_new_start_date`, `…mixed_completed_and_uncompleted_middle_days` | PASS |
| 4 | Completed days retain their original `scheduled_date` | ✅ Feature `…reanchors_uncompleted_days_and_preserves_completed_ones` (day 1 Monday retained); Unit `…preserves_completed_day_dates_and_reanchors_uncompleted_days` | PASS |
| 5 | Non-owner `403`, success `200` with updated resource | ✅ Feature `…returns_403_when_subscription_belongs_to_another_user`, happy-path tests assert `assertOk()` and progress shape | PASS |

### Finishing (AC 6–9)
| # | Criterion | Test exists | Status |
|---|-----------|-------------|--------|
| 6 | `POST /…/finish` marks subscription completed | ✅ Feature `…marks_subscription_completed_when_all_days_are_done`; Unit `…marks_subscription_completed_when_all_days_are_done` | PASS |
| 7 | Only when every day has `completed_at`; otherwise `422` with `{ message, pending_days }` | ✅ Feature `…returns_422_with_pending_days_when_days_remain` asserts exact JSON `{message, pending_days: [2,4,5]}`; Unit `…throws_with_pending_positions_when_days_remain` | PASS |
| 8 | On success: `status = completed`, `completed_at = now()`, `200` with resource | ✅ Feature asserts `status`, `completed_at` ISO 8601 with frozen `Carbon::setTestNow`; Unit ditto | PASS |
| 9 | Already-completed subscription returns `200`, `completed_at` preserved | ✅ Feature `…is_idempotent_for_already_completed_subscription` asserts original `completed_at` survives; Unit mirrors | PASS |

### Abandoning (AC 10–13)
| # | Criterion | Test exists | Status |
|---|-----------|-------------|--------|
| 10 | `POST /…/abandon` sets `status = abandoned`, row not soft-deleted | ✅ Feature `…flips_active_subscription_to_abandoned` asserts `deleted_at` null; Unit `…does_not_soft_delete_the_row` | PASS |
| 11 | Returns `200` with resource | ✅ Feature `…flips_active_subscription_to_abandoned` asserts `assertOk()` + JSON path | PASS |
| 12 | Already-`abandoned` is a no-op `200` | ✅ Feature `…is_idempotent_for_already_abandoned_subscription`; Unit `…is_idempotent_for_already_abandoned` asserts `updated_at` unchanged | PASS |
| 13 | Abandoning a `completed` subscription is rejected with `422` | ✅ Feature `…returns_422_when_subscription_is_completed` asserts message; Unit `…throws_when_subscription_is_completed` | PASS |

### Authorization (AC 14–15)
| # | Criterion | Test exists | Status |
|---|-----------|-------------|--------|
| 14 | All three endpoints require Sanctum | ✅ `…rejects_missing_sanctum_token` on each of the three feature files | PASS |
| 15 | Ownership enforced via Form Request `authorize()` | ✅ `…returns_403…`/`…returns_403_for_non_owner` on each of the three feature files; request classes inspected — owner check in `authorize()` | PASS |

## Edge Cases Tested

| Case | Expected | Actual | Status |
|------|----------|--------|--------|
| Reschedule — missing `start_date` | 422 validation error | 422 | PASS |
| Reschedule — non-date `start_date` ("not-a-date") | 422 validation error | 422 | PASS |
| Reschedule — past `start_date` (yesterday, with `Carbon::setTestNow`) | 422 validation error (R2 `after_or_equal:today`) | 422 | PASS |
| Reschedule — `start_date = today` | Passes (boundary case of `after_or_equal`) | Passes | PASS |
| Reschedule — soft-deleted subscription | 404 (route binding excludes soft-deleted) | 404 | PASS |
| Reschedule — missing Sanctum token | 401 | 401 | PASS |
| Reschedule — non-owner subscription | 403 | 403 | PASS |
| Reschedule — `position 1` completed | `start_date` updated, first **uncompleted** day (position 2) re-anchored | Correct | PASS |
| Reschedule — mixed completed/uncompleted middle days | Only uncompleted get contiguous new dates; completed untouched | Correct | PASS |
| Reschedule — catch-up walk-through (Mon start, day 1 done Mon, today Thu, new start = Thu) | Day 1 stays Monday; day 2 = Thu; day 3 = Fri; … | Correct | PASS |
| Finish — pending positions returned sorted by `reading_plan_days.position` | `[2, 4, 5]` for sparse completions | `[2, 4, 5]` | PASS |
| Finish — idempotent `completed_at` preservation | Original timestamp survives | Original timestamp survives | PASS |
| Finish — missing Sanctum | 401 | 401 | PASS |
| Finish — non-owner | 403 | 403 | PASS |
| Abandon — `deleted_at` stays null after abandon | `deleted_at` null, `status = abandoned` | Correct | PASS |
| Abandon — idempotent on already-`Abandoned` leaves `updated_at` untouched | `updated_at` preserved (no DB write) | Correct | PASS |
| Abandon — on `Completed` | 422 `{ message: "Cannot abandon a completed subscription." }` | 422 with matching message | PASS |
| `SubscriptionNotCompletableException` renderer wired in `bootstrap/app.php` | Renders `{ message, pending_days }` with 422 | Confirmed (bootstrap/app.php:60) | PASS |

### Observations (not AC failures)

- **Finish on `Abandoned`**: the story's ACs explicitly handle the `Active` (success/pending) and `Completed` (idempotent) cases. The implementation, if all days happen to be `completed_at`, will flip an `Abandoned` subscription to `Completed`. The ACs are silent on this transition, so it is not a failure — flagging as a latent ambiguity for a future "resume" or "transition guards" story.
- **Reschedule on `Completed` / `Abandoned`**: ACs are silent; the implementation does not reject these statuses. Consistent with the existing posture and not a failure — flag for a future follow-up if product wants these locked.
- **Architecture R1** (concurrent complete during reschedule): accepted by the architect, no new tests required.

## Regressions

None found. All 149 tests in the full suite pass, including MBA-001 (catalog), MBA-002 (auth), and MBA-003 (start/complete-day) coverage. The new exception renderer is additive and does not affect existing error paths.

## Verdict

**QA PASSED** — all 15 acceptance criteria have dedicated feature and/or unit coverage and pass. No critical issues. Two minor behavioural ambiguities (finish-on-abandoned; reschedule-on-terminal-status) are flagged for product input but are outside MBA-004's explicit AC set.
