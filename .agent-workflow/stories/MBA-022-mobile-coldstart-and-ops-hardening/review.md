# Code Review: MBA-022-mobile-coldstart-and-ops-hardening

## Verdict

**REQUEST CHANGES** — addressed; awaiting re-review.

The implementation is architecturally sound and covers all six surfaces (bootstrap, sync, health, rate limiting, pagination, observability). The migration, models, toggle actions, controllers, middleware, and routes are correct. The story's feature tests (bootstrap, sync, health) cover the main happy paths.

However, **all plan-mandated unit tests are absent** (tasks 4–10, 13–14, 17), **two AC-required test cases are missing** (cache-hit 0 DB queries, tag-flush busts bootstrap), the **rate-limiter throttle tests are absent** (AC 17), the **`since` param accepts garbage without a 422** (deviation from plan), **`DeleteFavoriteCategoryAction` misses soft-deleted favorites** in the `category_id` null-out, and the **runbook was not updated** (task 19). Resolve Warnings 1–4 before re-reviewing.

---

## Warnings

### W1 — Extensive plan-mandated tests are missing — RESOLVED

**Files:** multiple — no new unit test files were added

The plan's task list specifies unit or feature tests for every new class. None of the following exist:

| Plan task | Missing test |
|---|---|
| Task 4 | `MobileCacheKeys` — assert key string and tags array for sample `Language` values |
| Task 5 | `ListQrCodesAction` — cache miss hits DB; cache hit returns cached result |
| Task 6 | `ShowAppBootstrapAction` — composes from mocked sub-Actions; cache hit serves 0 DB queries; flushing tag `news` busts `app:bootstrap:*` |
| Task 8 | `SyncTypeDelta` — readonly, cannot mutate after construction |
| Task 9 (×7) | Each `SyncBuilder` — full sync includes all rows; delta sync excludes pre-`since`; trashed rows surface in `deleted`; cap+1 trips `maxSeenAt`; cross-user rows excluded |
| Task 10 | `ShowUserSyncAction` — aggregates builders; `next_since = min(maxSeenAt)` when any builder truncates; `null` when no builder truncates |
| Task 13 | `PaginatesRead` trait — `per_page=0` → 1; `per_page=200` → 100; no `per_page` → 30 |
| Task 14 | `EnsureInternalOps` — VPC IP passes; public IP returns 403; comma-separated CIDRs supported |
| Task 16 | Rate limiter — 200 hits to a public route → first 180 OK, next 20 × 429 + `Retry-After`; 350 hits to auth route → 300 OK + 50 × 429; `/up` not affected after 1000 hits |
| Task 17 | Slow-query listener — 600 ms query writes to `slow_query` channel and adds Sentry breadcrumb; 100 ms query writes nothing |

Also missing from the story's ACs:
- AC 6: "Cache-hit feature test asserts second request issues 0 DB queries" — absent from `ShowAppBootstrapTest`.
- AC 6: "Tag-flush test asserts a daily-verse upsert busts `app:bootstrap:*`" — absent.

**Fix:** Add the missing unit tests and feature test cases. The two AC 6 cases belong in `ShowAppBootstrapTest`. The unit tests can be grouped per domain class.

---

### W2 — `DeleteFavoriteCategoryAction` skips soft-deleted favorites when nulling `category_id` — RESOLVED

**File:** `app/Domain/Favorites/Actions/DeleteFavoriteCategoryAction.php:17`

```php
Favorite::where('category_id', $category->id)
    ->update(['category_id' => null]);
```

`SoftDeletes` adds `WHERE deleted_at IS NULL` to all queries by default. Favorites that are already soft-deleted retain `category_id` pointing to the now-soft-deleted category. Those rows surface in the sync endpoint's `deleted` array, so clients will receive a deleted-favorite record whose `category_id` references a non-existent (soft-deleted) category. The existing unit test only covers the non-deleted path.

**Fix:**

```php
Favorite::withTrashed()
    ->where('category_id', $category->id)
    ->update(['category_id' => null]);
```

Add a test case in `DeleteFavoriteCategoryActionTest`: create a category, create and soft-delete a favorite in that category, delete the category, assert the soft-deleted favorite now has `category_id = null`.

---

### W3 — `ShowUserSyncRequest` accepts invalid `since` without a 422 — RESOLVED

**File:** `app/Http/Requests/Sync/ShowUserSyncRequest.php:22`

```php
'since' => ['nullable', 'string'],
```

Any non-empty string passes validation. An unparseable value (e.g., `since=garbage`) is silently caught in the `try/catch` and falls back to epoch — triggering a full sync with no feedback to the caller. The plan specifies a `date_format` rule: "Validates `since` as nullable ISO-8601 (`date_format` rule with multiple accepted formats)".

**Fix:** Add a `date` rule (or explicit `date_format` list) so that invalid timestamps fail validation and return a 422:

```php
'since' => ['nullable', 'date'],
```

Then the `since()` method can remove the try/catch and use `new DateTimeImmutable($this->validated('since') ?? '@0')` safely.

---

### W4 — Runbook not updated — RESOLVED

**File:** `docs/runbook/cache.md` — unchanged in this commit

Plan task 19 explicitly requires updating the runbook with:
- Bootstrap cache key + tag map and `mybible:cache-clear-tag app:bootstrap` example
- `/up` vs `/ready` split — note that the deploy/LB readiness probe **must** move from `/up` → `/ready`
- Rate-limit headers (`Retry-After`, `X-RateLimit-*`)
- Slow-query log file location (`storage/logs/slow_query.log`, 14-day retention)

The `/up` behaviour change note is ops-critical: without it, an operator following the old runbook will leave the LB pointing at the liveness probe, which now never touches Redis or DB.

**Fix:** Update `docs/runbook/cache.md` per task 19.

---

## Suggestions

### S1 — `SlowQueryListener` is inlined in `AppServiceProvider` instead of a separate class — ADDRESSED (extracted to `App\Support\Observability\SlowQueryListener` to enable the unit test required by W1).

**File:** `app/Providers/AppServiceProvider.php:107-132`

Plan task 17 specifies `App\Support\Observability\SlowQueryListener::register()` as a named, extractable, testable unit. The inline closure cannot be easily unit-tested (finding W1's task-17 test), and `AppServiceProvider::boot()` is already growing. Extracting the listener into its own class also makes it easier to swap the 500 ms threshold via config without touching the provider.

---

### S2 — Sync builder cursor is based on `updated_at` only; may regress under mixed soft-delete pages

**File:** `app/Domain/Sync/Sync/Builders/FavoriteSyncBuilder.php:50-54` (and the other 6 builders)

```php
$maxSeenAt = $truncated && $rows->isNotEmpty()
    ? DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string) $rows->last()->updated_at) ?: null
    : null;
```

The query matches rows by `updated_at > since OR deleted_at > since`, but orders only by `updated_at`. A row with `deleted_at > since` but `updated_at << since` sorts at the old `updated_at` position. If such a row lands at position `$cap` in a truncated page, `next_since` is set to the old `updated_at` — causing the next call to re-fetch an overlapping window. Clients dedup by id (plan risk §2), so correctness is not broken, but cursor efficiency degrades.

**Suggestion:** Order and cursor by `GREATEST(updated_at, COALESCE(deleted_at, updated_at))` instead of plain `updated_at` to ensure the cursor monotonically advances.

---

### S3 — `AppBootstrapResource` exists but is never used

**File:** `app/Http/Resources/Mobile/AppBootstrapResource.php`

`ShowAppBootstrapController` builds the response directly:

```php
return response()->json(['data' => $action->execute(...)])->header(...);
```

`AppBootstrapResource` is instantiated nowhere. Either wire it through the controller (return `AppBootstrapResource::make($result)->response()->header(...)`) or remove the file. The current state adds a class that implies a contract but enforces nothing.

---

## Acknowledged

*(none)*
