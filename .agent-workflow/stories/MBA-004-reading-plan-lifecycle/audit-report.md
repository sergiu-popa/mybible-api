# Audit Report — MBA-004

## Summary
MBA-004 ships the three subscription-lifecycle endpoints (reschedule, finish,
abandon) faithfully against its architecture doc: three domain Actions, one
DTO, two domain exceptions (one pure `RuntimeException` carrying the
structured `pending_days` payload, one Symfony `HttpException` for the
already-completed 422), three Form Requests with owner `authorize()`, three
invokable controllers, a dedicated renderer in `bootstrap/app.php`, and 35
new unit + feature tests. Strict types, `final` classes, explicit return
types, the "no else" rule, and the JSON error envelope are applied
consistently. The 15 acceptance criteria all have dedicated coverage, the
full suite (149 tests) is green, and the only findings are small cosmetic
points already raised by Code Review. **Confidence: HIGH.**

## Scores

| Dimension | Score (1–5) | Notes |
|---|---|---|
| Architecture Compliance | 5 | Mirrors `architecture.md` exactly — Actions/DTO/exceptions placed under `App\Domain\ReadingPlans\*`, no unearned abstractions (QueryBuilder methods intentionally skipped per the one-caller rule), renderer wired where R4 expected, R2's `after_or_equal:today` enforced. |
| Code Quality | 4 | Strict types, `final`, promoted readonly props, explicit return types, early returns. Two small smells: the `Carbon::parse($data->startDate->toDateString())` string round-trip in `RescheduleReadingPlanSubscriptionAction` (review.md suggestion, unaddressed) and `withProgressCounts()` duplicated between `FinishReadingPlanSubscriptionAction` and `AbandonReadingPlanSubscriptionAction`. Neither is behavioural. |
| API Design | 5 | `PATCH` for reschedule (state mutation on a child resource), `POST` for finish/abandon (non-idempotent-by-URL commands), `200` on success, `422` with structured `pending_days` body, `403` on non-owner, `401` on missing Sanctum, `404` on soft-deleted binding. All three live under `/api/v1/reading-plan-subscriptions/{subscription}/...` and the JSON envelope matches the handler. |
| Security | 5 | Ownership enforced server-side in each Form Request's `authorize()` (returns 403 via the handler), route binding continues to exclude soft-deleted subscriptions, no mass-assignment surface is expanded (DTO/validated payload only), no user input reaches raw SQL. Sanctum is still the only gate — no privilege escalation path was introduced. |
| Performance | 4 | Reschedule's uncompleted-day save loop is N single-row UPDATEs inside a transaction (bounded to ≤ ~365 rows per R1/R3 — acceptable, but a `Carbon`-keyed `CASE` or `upsert` would be cheaper if a plan ever exceeds that). `FinishReadingPlanSubscriptionAction`'s pending-positions query uses the existing `reading_plan_subscription_id` index (R3 accepted). `loadCount(...)` uses aggregate subqueries — no `days` hydration — so the response stays cheap even for long plans. No N+1 (`with('readingPlanDay')` in Reschedule). |
| Test Coverage | 5 | 35 new tests: 4 unit + 8 feature for Reschedule (incl. the R2 regression guard and the catch-up walk-through), 3 unit + 5 feature for Finish (incl. exact `pending_days` array and idempotent `completed_at` preservation), 4 unit + 5 feature for Abandon (incl. `deleted_at` null assertion and the 422 on `Completed`), plus the validator-level `RescheduleReadingPlanSubscriptionRequestTest`. Each of the 15 acceptance criteria has at least one direct assertion. |

## Issues Found

### Must Fix
_None._

### Should Fix
_None._

### Minor
- [ ] `app/Domain/ReadingPlans/Actions/RescheduleReadingPlanSubscriptionAction.php:18,29` — `Carbon::parse($data->startDate->toDateString())` re-parses an already-immutable date through a string. The `date` cast normalises `CarbonImmutable` on assignment; simpler form is `$subscription->start_date = $data->startDate;` and `$day->scheduled_date = $data->startDate->addDays($index);`. Pure readability, no behavioural change. (Also raised in `review.md`.)
- [ ] `app/Domain/ReadingPlans/Actions/FinishReadingPlanSubscriptionAction.php:43-49` and `AbandonReadingPlanSubscriptionAction.php:29-35` — identical `withProgressCounts()` private method copied across both Actions. One extra caller (any future lifecycle Action) would make a small trait or a shared base Action worth extracting; flag for the next eligible follow-up rather than fixing speculatively.
- [ ] `app/Http/Requests/ReadingPlans/{Reschedule,Finish,Abandon}ReadingPlanSubscriptionRequest.php` — four copies of the ~10-line owner-authorize block now exist (these three plus MBA-003's `CompleteReadingPlanSubscriptionDayRequest`). Architecture explicitly deferred extraction until a fifth endpoint lands; noting here so the trip-wire isn't forgotten.

## Recommendations
- **When the fifth owner-gated subscription endpoint lands**, extract the
  `authorize()` block to an `AuthorizesSubscriptionOwnership` trait or a
  `SubscriptionOwnerFormRequest` base — four copies is already one past the
  "rule of three" threshold the architect set.
- **If a future story ships a `GET /reading-plan-subscriptions/{subscription}`
  detail endpoint** (see R5), the three lifecycle endpoints may want to
  optionally include the re-anchored `days` array (or point the client to
  the new GET) so "show the user their new schedule" stops requiring a
  round-trip that doesn't exist yet.
- **Consider a single `UPDATE … CASE WHEN position = ? THEN ? …`** for the
  reschedule loop if a real plan ever exceeds a few hundred days — for v1,
  the documented ≤365 bound keeps the simple loop the right trade-off.

## Verdict
**AUDIT PASSED** — no Must-Fix or Should-Fix issues. The three Minor items
are readability/duplication notes and do not block promotion to `audited`.
