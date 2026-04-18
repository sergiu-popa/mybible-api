# Tasks: MBA-004 Reading Plan Lifecycle

Work top-to-bottom. Each task is independently runnable, but later tasks
depend on earlier ones being present in the working tree. Run
`make lint-fix && make stan && make test` after each logical group.

## Exceptions

- [ ] 1. Create `App\Domain\ReadingPlans\Exceptions\SubscriptionNotCompletableException` (`final`, not `HttpException`): constructor `public function __construct(public readonly array $pendingPositions)` that calls `parent::__construct('Subscription cannot be finished while days are pending.')`.
- [ ] 2. Create `App\Domain\ReadingPlans\Exceptions\SubscriptionAlreadyCompletedException` (`final`, extends `Symfony\Component\HttpKernel\Exception\HttpException`): constructor calls `parent::__construct(422, 'Cannot abandon a completed subscription.')`.
- [ ] 3. Add a renderer for `SubscriptionNotCompletableException` in `bootstrap/app.php` — emits `{ message, pending_days: [<positions>] }` with status 422. Place it before the catch-all `Throwable` renderer.

## DTO

- [ ] 4. Create `App\Domain\ReadingPlans\DataTransferObjects\RescheduleReadingPlanSubscriptionData` (`readonly final`, promoted properties: `ReadingPlanSubscription $subscription`, `CarbonImmutable $startDate`).

## Actions

- [ ] 5. Create `App\Domain\ReadingPlans\Actions\RescheduleReadingPlanSubscriptionAction` — inside a `DB::transaction(...)`: update `$subscription->start_date` and save; fetch uncompleted days with `readingPlanDay` loaded, sort in PHP by `readingPlanDay.position`; for each uncompleted day at index `$i`, set `scheduled_date = startDate->addDays($i)` and save. Return the subscription with `loadCount(['days', 'days as completed_days_count' => fn ($q) => $q->whereNotNull('completed_at')])`.
- [ ] 6. Create `App\Domain\ReadingPlans\Actions\FinishReadingPlanSubscriptionAction`. Idempotent on `Completed` (return with progress counts, no write). Otherwise query pending day positions via `$subscription->days()->whereNull('completed_at')->join('reading_plan_days', 'reading_plan_days.id', '=', 'reading_plan_subscription_days.reading_plan_day_id')->orderBy('reading_plan_days.position')->pluck('reading_plan_days.position')->all()`. If non-empty, throw `SubscriptionNotCompletableException($pending)`. Else set `status = Completed`, `completed_at = now()`, save, return with progress counts.
- [ ] 7. Create `App\Domain\ReadingPlans\Actions\AbandonReadingPlanSubscriptionAction`. Throw `SubscriptionAlreadyCompletedException` when `status === Completed`. Return unchanged (idempotent) when `status === Abandoned`. Else set `status = Abandoned`, save, return with progress counts.
- [ ] 8. Write unit tests for `RescheduleReadingPlanSubscriptionAction`: (a) completed days retain their original `scheduled_date`; (b) uncompleted days are re-anchored to consecutive dates from `newStartDate` in `position` order; (c) `subscription.start_date` is updated even when the `position 1` day is completed; (d) mixed completed/uncompleted middle days produce the expected contiguous date sequence on uncompleted rows.
- [ ] 9. Write unit tests for `FinishReadingPlanSubscriptionAction`: (a) throws `SubscriptionNotCompletableException` with correct pending positions when days remain; (b) sets `status = Completed` and `completed_at = now()` when all days are complete; (c) idempotent on `Completed` — returns with original `completed_at` preserved. Use `Carbon::setTestNow(...)` for (b).
- [ ] 10. Write unit tests for `AbandonReadingPlanSubscriptionAction`: (a) flips `Active → Abandoned`; (b) idempotent for `Abandoned` (no DB write, no status change); (c) throws `SubscriptionAlreadyCompletedException` when `status === Completed`; (d) does not soft-delete the row.

## Form Requests

- [ ] 11. Create `App\Http\Requests\ReadingPlans\RescheduleReadingPlanSubscriptionRequest` — `rules()` returns `['start_date' => ['required', 'date', 'after_or_equal:today']]` (see R2 in `architecture.md`); `authorize()` returns `$this->route('subscription') instanceof ReadingPlanSubscription && $this->user() instanceof User && $this->route('subscription')->user_id === $this->user()->id`; `toData(ReadingPlanSubscription $subscription): RescheduleReadingPlanSubscriptionData` builds the DTO using `CarbonImmutable::parse($this->validated('start_date'))`.
- [ ] 12. Create `App\Http\Requests\ReadingPlans\FinishReadingPlanSubscriptionRequest` — empty `rules()`, same owner `authorize()` pattern as task 11.
- [ ] 13. Create `App\Http\Requests\ReadingPlans\AbandonReadingPlanSubscriptionRequest` — empty `rules()`, same owner `authorize()` pattern.
- [ ] 14. Write unit tests for `RescheduleReadingPlanSubscriptionRequest`: (a) missing `start_date` → validation error; (b) non-date value → validation error; (c) past `start_date` (yesterday) → validation error on `after_or_equal`; (d) today → passes; (e) future `start_date` → passes. Freeze time with `Carbon::setTestNow(...)` so (c)–(e) are deterministic.

## Controllers

- [ ] 15. Create `App\Http\Controllers\Api\V1\ReadingPlans\RescheduleReadingPlanSubscriptionController` (invokable). Signature: `__invoke(RescheduleReadingPlanSubscriptionRequest $request, ReadingPlanSubscription $subscription, RescheduleReadingPlanSubscriptionAction $action): ReadingPlanSubscriptionResource`. Calls `$action->execute($request->toData($subscription))`, wraps in `ReadingPlanSubscriptionResource::make(...)`. Status 200.
- [ ] 16. Create `App\Http\Controllers\Api\V1\ReadingPlans\FinishReadingPlanSubscriptionController` (invokable). Signature: `__invoke(FinishReadingPlanSubscriptionRequest $request, ReadingPlanSubscription $subscription, FinishReadingPlanSubscriptionAction $action): ReadingPlanSubscriptionResource`. Returns `ReadingPlanSubscriptionResource::make($action->execute($subscription))`.
- [ ] 17. Create `App\Http\Controllers\Api\V1\ReadingPlans\AbandonReadingPlanSubscriptionController` (invokable). Same shape as task 16 with the abandon trio.

## Routes

- [ ] 18. In `routes/api.php`, inside the existing `reading-plan-subscriptions` group (`auth:sanctum` + `scopeBindings`), add: `Route::patch('{subscription}/start-date', RescheduleReadingPlanSubscriptionController::class)->name('reschedule')`, `Route::post('{subscription}/finish', FinishReadingPlanSubscriptionController::class)->name('finish')`, `Route::post('{subscription}/abandon', AbandonReadingPlanSubscriptionController::class)->name('abandon')`. Confirm with `php artisan route:list --path=reading-plan-subscriptions` that all three resolve and show the expected middleware stack (`['api', 'auth:sanctum']`).

## Regression check

- [ ] 19. Run the existing subscription feature tests (`make test filter=ReadingPlanSubscription`). They must still pass before adding new feature tests — the renderer addition and new routes should not affect MBA-003 behaviour.

## Feature tests

- [ ] 20. Create `tests/Feature/Api/V1/ReadingPlans/RescheduleReadingPlanSubscriptionTest.php` covering: (a) 200 happy path with mixed completed/uncompleted days — completed `scheduled_date`s unchanged, uncompleted re-anchored consecutively from new `start_date` in `position` order, `subscription.start_date` updated; (b) catch-up walk-through — seed a 7-day plan with `start_date = Monday`, day 1 completed on Monday; freeze `now()` to Thursday; PATCH with `start_date = Thursday`; assert `subscription.start_date = Thursday`, day 1 still Monday, day 2 = Thursday, day 3 = Friday, day 4 = Saturday; (c) 200 when `position 1` is completed — `start_date` still updates, first uncompleted day anchors to new `start_date`; (d) 422 when `start_date` is missing; (e) 422 when `start_date` is not a date; (f) 422 when `start_date` is in the past (regression guard for R2 / `after_or_equal:today`); (g) 403 when subscription belongs to another user; (h) 401 when no Sanctum token. Use `Carbon::setTestNow(...)` to freeze dates.
- [ ] 21. Create `tests/Feature/Api/V1/ReadingPlans/FinishReadingPlanSubscriptionTest.php` covering: (a) 200 happy path with all days completed — `status = completed`, `completed_at` set to frozen `now()`; (b) 422 with `pending_days` body (exact position list, sorted ascending) when days remain; (c) idempotent 200 on already-`Completed` subscription with original `completed_at` preserved; (d) 403 non-owner; (e) 401 missing Sanctum.
- [ ] 22. Create `tests/Feature/Api/V1/ReadingPlans/AbandonReadingPlanSubscriptionTest.php` covering: (a) 200 happy path flips `Active → Abandoned`, row is **not** soft-deleted (assert `deleted_at` is null); (b) idempotent 200 on already-`Abandoned`; (c) 422 when subscription is `Completed`; (d) 403 non-owner; (e) 401 missing Sanctum.

## Final gate

- [ ] 23. Run `make check` (lint + stan + full test suite). Resolve any failures before marking the story `engineered`.
