# Architecture: MBA-003 Reading Plan Subscriptions

## Overview

Extend the existing `App\Domain\ReadingPlans` domain with a `ReadingPlanSubscription`
aggregate (parent `reading_plan_subscriptions` + child `reading_plan_subscription_days`)
plus two write endpoints: **start a subscription** and **complete a day**. The catalog
endpoints from MBA-001 are upgraded from `api-key` to `api-key-or-sanctum` so they can
conditionally surface the authenticated user's subscriptions on each plan response.
Lifecycle endpoints (reschedule / finish / abandon) are deferred to MBA-004.

## Domain placement

Subscriptions are part of the existing `ReadingPlans` bounded context — they reference
`ReadingPlan` and `ReadingPlanDay` and have no meaning outside the catalog. No new
top-level domain folder is introduced; new files live under
`app/Domain/ReadingPlans/{Models,Actions,DataTransferObjects,QueryBuilders,Enums}`.

---

## Domain changes

### Migrations

Two new migrations follow the existing `YYYY_MM_DD_HHMMSS_create_<resource>_table.php`
pattern, ordered after the MBA-001 migrations.

**`reading_plan_subscriptions`**

| Column | Type | Notes |
|---|---|---|
| `id` | `bigIncrements` | |
| `user_id` | `foreignId` → `users.id` | `cascadeOnDelete()` |
| `reading_plan_id` | `foreignId` → `reading_plans.id` | `restrictOnDelete()` |
| `start_date` | `date` | |
| `status` | `string` | default `'active'` |
| `completed_at` | `timestamp nullable` | written by MBA-004 finish |
| `timestamps` | | |
| `softDeletes` | | reserved for GDPR-style hard removal |

Indexes: `(user_id, status)` for the "list my plans" path that MBA-001's catalog
endpoint will hit when called with a Sanctum token, and the implicit FK indexes.

**`reading_plan_subscription_days`**

| Column | Type | Notes |
|---|---|---|
| `id` | `bigIncrements` | |
| `reading_plan_subscription_id` | `foreignId` | `cascadeOnDelete()` |
| `reading_plan_day_id` | `foreignId` → `reading_plan_days.id` | `restrictOnDelete()` |
| `scheduled_date` | `date` | |
| `completed_at` | `timestamp nullable` | |
| `timestamps` | | |

Constraints: unique `(reading_plan_subscription_id, reading_plan_day_id)` (a day
appears at most once per subscription); index `(reading_plan_subscription_id, scheduled_date)`
for date-ordered listings.

### Models

**`App\Domain\ReadingPlans\Models\ReadingPlanSubscription`** (new, `final`, soft-deletes):

- Casts: `start_date` → `date`, `completed_at` → `datetime`, `status` → `SubscriptionStatus`.
- Relations:
  - `user(): BelongsTo<User, $this>`
  - `readingPlan(): BelongsTo<ReadingPlan, $this>`
  - `days(): HasMany<ReadingPlanSubscriptionDay, $this>`
- Custom builder via `newEloquentBuilder()` returning `ReadingPlanSubscriptionQueryBuilder`.
- `#[UseFactory(ReadingPlanSubscriptionFactory::class)]`.

**`App\Domain\ReadingPlans\Models\ReadingPlanSubscriptionDay`** (new, `final`):

- Casts: `scheduled_date` → `date`, `completed_at` → `datetime`.
- Relations:
  - `subscription(): BelongsTo<ReadingPlanSubscription, $this>`
  - `readingPlanDay(): BelongsTo<ReadingPlanDay, $this>`
- `#[UseFactory(ReadingPlanSubscriptionDayFactory::class)]`.

**`App\Domain\ReadingPlans\Models\ReadingPlan`** (modified):

- Add `subscriptions(): HasMany<ReadingPlanSubscription, $this>` so the catalog
  endpoints can constrained-eager-load the current user's subscriptions.
- Add `resolveRouteBinding($value, $field)` override that delegates to the default
  resolution but forces `.published()`. See **Route bindings** below for the rationale.

**`App\Models\User`** (unchanged): no `subscriptions()` relation added — the FormRequest
authorizes via `user_id` comparison and the catalog controllers eager-load via the
`ReadingPlan->subscriptions` relation. Adding a `User->subscriptions` relation is
out-of-scope for MBA-003.

### Enum

**`App\Domain\ReadingPlans\Enums\SubscriptionStatus`** — string-backed:

- `Active = 'active'`
- `Completed = 'completed'`
- `Abandoned = 'abandoned'`

### QueryBuilder

**`App\Domain\ReadingPlans\QueryBuilders\ReadingPlanSubscriptionQueryBuilder`**:

- `forUser(User $user): self` — `where('user_id', $user->id)`.
- `withProgressCounts(): self` — `->withCount(['days', 'days as completed_days_count' => fn ($q) => $q->whereNotNull('completed_at')])`.
- `withDaysOrdered(): self` — eager-loads `days` joined to `readingPlanDay` and ordered by `readingPlanDay.position`.

The catalog controllers will use `forUser($user)->withProgressCounts()` as the
constrained eager-load callback when `$request->user() !== null`.

### Factories

- `ReadingPlanSubscriptionFactory` with states `active()`, `completed()`, `abandoned()`.
- `ReadingPlanSubscriptionDayFactory` with states `pending()`, `completed()` (sets `completed_at = now()`).

---

## Actions & DTOs

### DTOs

**`StartReadingPlanSubscriptionData`** (`readonly final`):

```php
public function __construct(
    public User $user,
    public ReadingPlan $plan,
    public CarbonImmutable $startDate,
) {}
```

No `from()` constructor — this DTO is built by the FormRequest's `toData(...)`
helper from the resolved route models + validated `start_date`.

No DTO is introduced for day completion. Its only inputs are the
`ReadingPlanSubscriptionDay` (resolved by scoped route binding) and the call
to `now()`. Adding a DTO would be ceremony without value.

### Actions

**`StartReadingPlanSubscriptionAction`**:

- Signature: `execute(StartReadingPlanSubscriptionData $data): ReadingPlanSubscription`.
- Wraps in a single `DB::transaction(...)`:
  1. Insert the parent row with `status = active`.
  2. Fetch `$plan->days` ordered by `position`.
  3. Bulk-insert one `ReadingPlanSubscriptionDay` per plan day with
     `scheduled_date = $data->startDate->addDays($position - 1)`.
- Returns the freshly created subscription with `days` and `withProgressCounts`
  loaded so the controller can hand it straight to the resource.

**`CompleteSubscriptionDayAction`**:

- Signature: `execute(ReadingPlanSubscriptionDay $day): ReadingPlanSubscriptionDay`.
- Idempotent: if `completed_at !== null`, return the model unchanged (no DB write,
  no overwrite of the original timestamp). Otherwise set `completed_at = now()`,
  save, return.

---

## Events & Listeners

None. No domain events are needed for MBA-003. (Future stories — notifications,
streak tracking — will likely fire `ReadingPlanSubscriptionStarted` /
`ReadingPlanDayCompleted`. Not adding them speculatively.)

---

## HTTP endpoints

| Method | Path | Controller | Form Request | Resource | Middleware |
|---|---|---|---|---|---|
| GET | `/api/v1/reading-plans` | `ListReadingPlansController` (existing, modified) | `ListReadingPlansRequest` | `ReadingPlanResource` | `api-key-or-sanctum`, `resolve-language` |
| GET | `/api/v1/reading-plans/{plan:slug}` | `ShowReadingPlanController` (existing, modified) | `ShowReadingPlanRequest` | `ReadingPlanResource` | `api-key-or-sanctum`, `resolve-language` |
| POST | `/api/v1/reading-plans/{plan:slug}/subscriptions` | `StartReadingPlanSubscriptionController` | `StartReadingPlanSubscriptionRequest` | `ReadingPlanSubscriptionResource` | `auth:sanctum` |
| POST | `/api/v1/reading-plan-subscriptions/{subscription}/days/{day}/complete` | `CompleteReadingPlanSubscriptionDayController` | `CompleteReadingPlanSubscriptionDayRequest` | `ReadingPlanSubscriptionDayResource` | `auth:sanctum` |

### Route bindings (scope-aware)

- **`{plan:slug}` (start subscription):** must resolve published plans only — a
  client must not be able to start a subscription against a draft. **Approach:**
  override `ReadingPlan::resolveRouteBinding($value, $field)` to apply
  `->published()` before lookup. Same scope is reused by the catalog endpoints
  (where the controllers currently apply `.published()` manually); migrating to
  a model-level override centralises the rule. Returns `null` → Laravel
  responds 404 via the existing `NotFoundHttpException` renderer.

  *Side effect on the catalog `ShowReadingPlanController`:* it currently calls
  `.published()->where('slug', $slug)` manually. Once the route is rewritten to
  use `{plan:slug}` binding, the controller becomes `__invoke(ShowReadingPlanRequest $request, ReadingPlan $plan)`.
  Keep the existing controller signature change scoped to this story.

- **`{subscription}` (complete day):** soft-deleted subscriptions must not
  resolve. Default Laravel binding already excludes soft-deleted records.
  Ownership is **not** baked into binding — that conflates "not found" (404)
  with "not yours" (403). The story spec calls for **403 on non-owner** and
  **404 on day-not-belonging-to-subscription**; binding stays loose, ownership
  is enforced in the FormRequest's `authorize()`.

- **`{day}` (nested under `{subscription}`):** must belong to the resolved
  subscription. **Approach:** call `->scopeBindings()` on the route group so
  Laravel constrains the `day` lookup to the parent's `days()` relation —
  mismatched ids return 404 automatically. Avoids a manual lookup in the
  controller and matches the story's "404 on cross-subscription" requirement.

### Form Requests

**`StartReadingPlanSubscriptionRequest`**:

- Rules: `start_date` → `nullable|date|after_or_equal:today`.
- `authorize()` → `true` (any authenticated user can subscribe to any published plan;
  AC #4 explicitly allows multiple active subscriptions to the same plan).
- Helper: `startDate(): CarbonImmutable` — returns the validated date or `today()`.
- `toData(ReadingPlan $plan): StartReadingPlanSubscriptionData` — builds the DTO using
  `$this->user()` and the route-bound plan.

**`CompleteReadingPlanSubscriptionDayRequest`**:

- No rules (empty body).
- `authorize()` → returns `$this->route('subscription')->user_id === $this->user()->id`.
  Returning `false` raises `AuthorizationException`, which must render as 403 (see
  **Risks & open questions** below).

### Resources

**`ReadingPlanSubscriptionResource`** (`final`, `@mixin ReadingPlanSubscription`):

```php
[
    'id' => $this->id,
    'plan_id' => $this->reading_plan_id,
    'status' => $this->status->value,
    'start_date' => $this->start_date->toDateString(),
    'completed_at' => $this->completed_at?->toIso8601String(),
    'progress' => [
        'completed_days' => $this->completed_days_count ?? $this->days->whereNotNull('completed_at')->count(),
        'total_days' => $this->days_count ?? $this->days->count(),
    ],
    'days' => ReadingPlanSubscriptionDayResource::collection($this->whenLoaded('days')),
]
```

`progress` prefers the `withCount()` aggregates (set by `withProgressCounts()`) and
falls back to in-memory counts when the loaded collection is already present —
this matches MBA-001's resource style and avoids forcing every caller to opt into
withCount.

**`ReadingPlanSubscriptionDayResource`** (`final`, `@mixin ReadingPlanSubscriptionDay`):

```php
[
    'id' => $this->id,
    'day_id' => $this->reading_plan_day_id,
    'position' => $this->whenLoaded('readingPlanDay', fn () => $this->readingPlanDay->position),
    'scheduled_date' => $this->scheduled_date->toDateString(),
    'completed_at' => $this->completed_at?->toIso8601String(),
]
```

`position` is included whenever the related `readingPlanDay` is loaded; the start
action eager-loads it so the start response carries positions out of the box.

**`ReadingPlanResource`** (modified): add a `subscriptions` field that is conditional
on **both** an authenticated user being present **and** the relation being loaded:

```php
'subscriptions' => $this->when(
    $request->user() !== null && $this->relationLoaded('subscriptions'),
    fn () => ReadingPlanSubscriptionResource::collection($this->subscriptions),
),
```

`$this->when(...)` (vs. `whenLoaded(...)`) is required so the field is fully omitted
on api-key-only requests — matching AC #10.

### Controller updates (MBA-001)

- `ListReadingPlansController` and `ShowReadingPlanController` switch their
  middleware stack from `['api-key', 'resolve-language']` to
  `['api-key-or-sanctum', 'resolve-language']`.
- When `$request->user() !== null`, the controller appends a constrained eager-load:
  `->with(['subscriptions' => fn ($q) => $q->forUser($request->user())->withProgressCounts()])`.
- `ShowReadingPlanController` switches from manual `where('slug', $slug)` to a
  route-bound `ReadingPlan $plan` parameter (enabled by the new
  `resolveRouteBinding` override).

---

## Risks & open questions

### R1 — `AuthorizationException` is not currently rendered as JSON 403

The exception renderer chain in `bootstrap/app.php` handles `ValidationException`,
`AuthenticationException`, `ModelNotFoundException`, and `NotFoundHttpException`
explicitly. The catch-all `Throwable` renderer maps to `$e->getStatusCode()` only
when `$e instanceof HttpExceptionInterface`. `Illuminate\Auth\Access\AuthorizationException`
does **not** implement `HttpExceptionInterface`, so it would currently render as
a **500** with the exception message — not 403.

**Resolution (in MBA-003 scope):** add a renderer block in `bootstrap/app.php`:

```php
$exceptions->render(function (AuthorizationException $e, Request $request) {
    return response()->json([
        'message' => $e->getMessage() !== '' ? $e->getMessage() : 'This action is unauthorized.',
    ], 403);
});
```

This is required for AC #8 (non-owner returns 403) and is a foundational fix —
flag it in the PR description so future stories know it's there.

### R2 — Catalog routes flipping to `api-key-or-sanctum` could regress MBA-001 tests

Existing MBA-001 feature tests use `WithApiKeyClient` and don't send a bearer
token. They should continue to pass because `EnsureApiKeyOrSanctum` falls
through to `EnsureValidApiKey` when no bearer token is present. The Engineer
must run the full MBA-001 suite after the middleware swap and before any
resource changes land — explicit task in `tasks.md`.

### R3 — Constrained eager-load on the list endpoint may issue a large IN query

`ListReadingPlansController` paginates published plans (default 15 per page).
The constrained eager-load runs `WHERE reading_plan_id IN (…) AND user_id = ?`,
which is bounded by the page size and indexed — acceptable. No N+1, no
unbounded scan. No further mitigation needed for v1.

### R4 — Past-date rule is server-timezone-dependent

The `after_or_equal:today` rule evaluates against the server's `app.timezone`.
A user in a different timezone whose "today" is a day behind the server could
have their request rejected. The story explicitly defers timezone-correct
date validation to a future i18n story; documenting here so it's not silently
forgotten. No code in MBA-003 should pretend to handle per-user timezones.

### R5 — Bulk-insert vs Eloquent loop for subscription day generation

Plans typically have 7–90 days. `DB::table('reading_plan_subscription_days')->insert($rows)`
is one round-trip; an Eloquent `->createMany()` loop is N round-trips. Use the
bulk insert inside the transaction. Cost: timestamps must be set manually
(`['created_at' => $now, 'updated_at' => $now]`). Tradeoff is worth it.

### Open questions answered (from story)

1. **Pagination of `subscriptions` payload on `ReadingPlanResource`:** for v1,
   eager-load all subscriptions for the current user without pagination. Real
   users will have 1–3 per plan; the dataset is bounded by repeat-subscription
   frequency, not by user count. Revisit only if a future analytics view
   surfaces a heavy-hitter outlier.

2. **User deletion behaviour:** confirmed cascade. The `user_id` FK uses
   `cascadeOnDelete()`. Anonymisation for metrics is a GDPR-driven concern that
   warrants its own story (touches `users` and `personal_access_tokens` too).

---

## Testing strategy

| Layer | Tool | Suite | Notes |
|---|---|---|---|
| Migration | (no test) | — | Schema correctness verified by feature tests with `RefreshDatabase`. |
| `ReadingPlanSubscription` model | (no test) | — | Pure framework model; relations are exercised by Action and feature tests. |
| `ReadingPlanSubscriptionQueryBuilder` | PHPUnit + `RefreshDatabase` | unit | Covers `forUser` and `withProgressCounts` scope correctness. Reusable across MBA-004; worth its own suite. |
| `StartReadingPlanSubscriptionAction` | PHPUnit unit | unit | Covers the bulk-insert + transaction logic and `scheduled_date` computation in isolation — easier to reason about than the equivalent feature-test assertion that decodes JSON. |
| `CompleteSubscriptionDayAction` | PHPUnit unit | unit | The idempotent branch (`completed_at` already set → no-op, no overwrite) is regression-sensitive and trivial to assert here. |
| `StartReadingPlanSubscriptionRequest` | PHPUnit unit | unit | Past-date rejection + `startDate()` defaulting are pure validation logic; cheap to lock down independently of HTTP. |
| `CompleteReadingPlanSubscriptionDayRequest` | (no unit test) | — | Single-line `authorize()` is fully covered by the feature test's 403 case. |
| `ReadingPlanSubscriptionResource` | PHPUnit unit | unit | Asserts the `progress` fallback (withCount aggregate vs collection count) — the dual-path branch isn't worth a feature test for both shapes. |
| `ReadingPlanSubscriptionDayResource` | (no unit test) | — | Trivial passthrough; feature tests assert shape. |
| `ReadingPlanResource` (subscriptions field) | (no unit test) | — | Conditional inclusion is covered explicitly by feature tests for both api-key and Sanctum paths. |
| Catalog endpoints (list, show) | Feature | feature | Add Sanctum-token cases that assert `subscriptions` is present + scoped to the current user; api-key-only cases assert it is omitted. Existing MBA-001 tests must continue to pass. |
| `POST /reading-plans/{plan:slug}/subscriptions` | Feature | feature | Happy path (201 + day rows), default `start_date` = today, past-date 422, draft-plan 404, unknown-slug 404, missing Sanctum 401. |
| `POST /reading-plan-subscriptions/{subscription}/days/{day}/complete` | Feature | feature | Happy path (200), idempotent re-call (200, no overwrite), non-owner 403, day-from-other-subscription 404, missing Sanctum 401. |

Use `Sanctum::actingAs($user)` for Sanctum-protected feature tests; reuse the
existing `WithApiKeyClient` trait for the api-key-only catalog case. No new
test trait is needed for MBA-003.
