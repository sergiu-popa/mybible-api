# Tasks: MBA-003 Reading Plan Subscriptions

Work top-to-bottom. Each task is independently runnable, but later tasks depend
on earlier ones being merged into the working tree (not necessarily committed).
Run `make lint-fix && make stan && make test` after each logical group.

## Schema & Enum

- [x] 1. Create `App\Domain\ReadingPlans\Enums\SubscriptionStatus` (string-backed: `Active`, `Completed`, `Abandoned`).
- [x] 2. Create migration `create_reading_plan_subscriptions_table` per the schema in `architecture.md` (FK cascade on `user_id`, restrict on `reading_plan_id`, `softDeletes`, index on `(user_id, status)`).
- [x] 3. Create migration `create_reading_plan_subscription_days_table` per the schema (cascade on `reading_plan_subscription_id`, restrict on `reading_plan_day_id`, unique on `(subscription_id, day_id)`, index on `(subscription_id, scheduled_date)`).
- [x] 4. Run `make migrate` to apply both migrations locally.

## Models, QueryBuilder, Factories

- [x] 5. Create `App\Domain\ReadingPlans\Models\ReadingPlanSubscription` (`final`, soft-deletes, casts, relations to `User`, `ReadingPlan`, `days`, `newEloquentBuilder()` returning the new query builder, `#[UseFactory]` attribute).
- [x] 6. Create `App\Domain\ReadingPlans\Models\ReadingPlanSubscriptionDay` (`final`, casts for `scheduled_date` + `completed_at`, relations to `subscription` and `readingPlanDay`, `#[UseFactory]` attribute).
- [x] 7. Add `subscriptions(): HasMany<ReadingPlanSubscription, $this>` to `App\Domain\ReadingPlans\Models\ReadingPlan`.
- [x] 8. Override `App\Domain\ReadingPlans\Models\ReadingPlan::resolveRouteBinding($value, $field)` to apply `.published()` to the lookup. Update `ShowReadingPlanController` to accept the route-bound `ReadingPlan $plan` instead of the string slug + manual query.
- [x] 9. Create `App\Domain\ReadingPlans\QueryBuilders\ReadingPlanSubscriptionQueryBuilder` with `forUser(User $user)`, `withProgressCounts()` (using `withCount` plus a `days as completed_days_count` constraint on `whereNotNull('completed_at')`), and `withDaysOrdered()`.
- [x] 10. Create `database/factories/ReadingPlanSubscriptionFactory` with `active()`, `completed()`, and `abandoned()` states.
- [x] 11. Create `database/factories/ReadingPlanSubscriptionDayFactory` with `pending()` and `completed()` states.
- [x] 12. Write unit tests for `ReadingPlanSubscriptionQueryBuilder` (`forUser` returns only the user's rows; `withProgressCounts` reports correct `days_count` and `completed_days_count`).

## DTO & Actions

- [x] 13. Create `App\Domain\ReadingPlans\DataTransferObjects\StartReadingPlanSubscriptionData` (readonly, promoted properties: `User $user`, `ReadingPlan $plan`, `CarbonImmutable $startDate`).
- [x] 14. Create `App\Domain\ReadingPlans\Actions\StartReadingPlanSubscriptionAction` — wraps in `DB::transaction`, creates the parent row, bulk-inserts one `reading_plan_subscription_days` row per `ReadingPlanDay` ordered by `position` with `scheduled_date = startDate + (position - 1) days`. Returns the subscription with `days.readingPlanDay` and `withProgressCounts()` loaded.
- [x] 15. Create `App\Domain\ReadingPlans\Actions\CompleteSubscriptionDayAction` — idempotent: returns the day unchanged when `completed_at` is already set; otherwise sets `completed_at = now()` and saves.
- [x] 16. Write unit tests for `StartReadingPlanSubscriptionAction` (correct row count, correct `scheduled_date` per position, transactional rollback on failure, status defaults to `Active`).
- [x] 17. Write unit tests for `CompleteSubscriptionDayAction` (sets `completed_at` when null; preserves the original `completed_at` when called twice).

## Form Requests

- [x] 18. Create `App\Http\Requests\ReadingPlans\StartReadingPlanSubscriptionRequest` with `start_date` rule (`nullable|date|after_or_equal:today`), `authorize() => true`, helper `startDate(): CarbonImmutable`, and `toData(ReadingPlan $plan): StartReadingPlanSubscriptionData`.
- [x] 19. Create `App\Http\Requests\ReadingPlans\CompleteReadingPlanSubscriptionDayRequest` with empty `rules()` and `authorize()` returning `$this->route('subscription')->user_id === $this->user()->id`.
- [x] 20. Write unit tests for `StartReadingPlanSubscriptionRequest` (missing `start_date` → defaults to today; past `start_date` → validation error; future `start_date` → passes).

## Resources

- [x] 21. Create `App\Http\Resources\ReadingPlans\ReadingPlanSubscriptionResource` per the shape in `architecture.md` (use `withCount` aggregate when present, fall back to in-memory count; include `days` only via `whenLoaded`).
- [x] 22. Create `App\Http\Resources\ReadingPlans\ReadingPlanSubscriptionDayResource` (include `position` only when `readingPlanDay` is loaded).
- [x] 23. Modify `App\Http\Resources\ReadingPlans\ReadingPlanResource` to add `'subscriptions'` field via `$this->when($request->user() !== null && $this->relationLoaded('subscriptions'), …)`.
- [x] 24. Write unit tests for `ReadingPlanSubscriptionResource` (progress prefers `*_count` attributes; falls back to collection counts when not eager-loaded).

## Controllers

- [x] 25. Modify `App\Http\Controllers\Api\V1\ReadingPlans\ListReadingPlansController` to constrained-eager-load `subscriptions` (with `forUser` + `withProgressCounts`) when `$request->user() !== null`.
- [x] 26. Modify `App\Http\Controllers\Api\V1\ReadingPlans\ShowReadingPlanController` to accept the route-bound `ReadingPlan $plan` parameter and apply the same conditional eager-load.
- [x] 27. Create `App\Http\Controllers\Api\V1\ReadingPlans\StartReadingPlanSubscriptionController` (invokable; calls `StartReadingPlanSubscriptionAction`; returns `ReadingPlanSubscriptionResource` wrapped in `Response::HTTP_CREATED`).
- [x] 28. Create `App\Http\Controllers\Api\V1\ReadingPlans\CompleteReadingPlanSubscriptionDayController` (invokable; calls `CompleteSubscriptionDayAction`; returns `ReadingPlanSubscriptionDayResource`).

## Exception handling

- [x] 29. Add a renderer for `Illuminate\Auth\Access\AuthorizationException` in `bootstrap/app.php` returning `{ "message": … }` with status 403 (see Risk R1 in `architecture.md`).

## Routes

- [x] 30. In `routes/api.php`, change the catalog route group's middleware from `['api-key', 'resolve-language']` to `['api-key-or-sanctum', 'resolve-language']`. Convert `Route::get('{slug}', ShowReadingPlanController::class)` to `Route::get('{plan:slug}', ShowReadingPlanController::class)`.
- [x] 31. Add the start-subscription route under the catalog group: `Route::post('{plan:slug}/subscriptions', StartReadingPlanSubscriptionController::class)->middleware('auth:sanctum')->name('subscriptions.store')`. Confirm the resolved middleware stack for this route is `['api-key-or-sanctum', 'resolve-language', 'auth:sanctum']` — `auth:sanctum` enforces the user identity; the inherited middleware stays harmless on a token-bearing request.
- [x] 32. Add the day-completion route in a dedicated group: `Route::middleware('auth:sanctum')->prefix('reading-plan-subscriptions')->name('reading-plan-subscriptions.')->scopeBindings()->group(...)` with `Route::post('{subscription}/days/{day}/complete', CompleteReadingPlanSubscriptionDayController::class)->name('days.complete')`. Verify nested scoping by hitting a foreign `{day}` id and asserting 404.

## Regression check

- [x] 33. Run the existing MBA-001 catalog feature tests (`make test filter=ReadingPlans`) before adding new feature tests. They must pass after the middleware swap. Investigate any failure before proceeding.

## Feature tests

- [x] 34. Update `tests/Feature/Api/V1/ReadingPlans/ListReadingPlansTest.php` (and the show test) with two new cases: (a) Sanctum-authenticated request returns a `subscriptions` array containing only the current user's subscriptions with progress; (b) api-key-only request omits `subscriptions` from the JSON entirely.
- [x] 35. Create `tests/Feature/Api/V1/ReadingPlans/StartReadingPlanSubscriptionTest.php` covering: 201 happy path with default `start_date`; 201 with explicit future `start_date`; 422 on past `start_date`; 404 on draft plan slug; 404 on unknown slug; 401 on missing Sanctum token; subscription days are created with correct `scheduled_date` per position; `status` defaults to `active`; multiple subscriptions to the same plan are allowed (AC #4).
- [x] 36. Create `tests/Feature/Api/V1/ReadingPlans/CompleteReadingPlanSubscriptionDayTest.php` covering: 200 happy path sets `completed_at`; idempotent re-call returns 200 and does not overwrite `completed_at`; 403 when the subscription belongs to another user; 404 when the day belongs to a different subscription; 401 on missing Sanctum token.

## Final gate

- [x] 37. Run `make check` (lint + stan + full test suite) and resolve any issues before marking the story `engineered`.
