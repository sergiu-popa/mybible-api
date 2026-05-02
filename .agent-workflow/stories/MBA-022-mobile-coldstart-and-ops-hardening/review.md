# Code Review: MBA-022-mobile-coldstart-and-ops-hardening

## Verdict

**APPROVE** — all four prior Warnings (W1–W4) and Suggestion S1 are resolved in commit `2726c69`. Remaining items are non-blocking suggestions (S2, S3) acknowledged below. Status moves to `qa-ready`.

The implementation covers all six surfaces (bootstrap aggregator, sync delta, liveness/readiness split, per-IP and per-user rate limiting, harmonised pagination, observability hooks). Plan tasks 1–20 are complete. The full filtered run (`make test-api filter='Mobile|Sync|Health|RateLimit|Pagination|Bootstrap|Slow|InternalOps|MobileBootstrap|MobileCacheKeys|DeleteFavoriteCategory'`) reports **104 passed, 1032 assertions** in 2.60 s.

## Re-review of prior findings

### W1 — Plan-mandated tests — RESOLVED

New unit and feature tests added in `2726c69`:

| Plan task | Test file |
|---|---|
| Task 4 | `tests/Unit/Domain/Cache/CacheKeysTest.php:117-130` (`test_mobile_bootstrap_keys_per_language`, `test_mobile_bootstrap_tag_union`) |
| Task 5 | `tests/Unit/Domain/QrCode/Actions/ListQrCodesActionTest.php` (miss/hit/tag-flush) |
| Task 6 | `tests/Unit/Domain/Mobile/Actions/ShowAppBootstrapActionTest.php` (compose, cache wiring, tag bust) |
| Task 8 | `tests/Unit/Domain/Sync/DataTransferObjects/SyncTypeDeltaTest.php` (readonly enforcement via reflection) |
| Task 9 | `tests/Unit/Domain/Sync/Sync/Builders/SyncBuilderContractTestCase.php` + 7 concrete subclasses — full sync, delta exclusion, trashed→deleted, cap+1→`maxSeenAt`, cross-user isolation |
| Task 10 | `tests/Unit/Domain/Sync/Actions/ShowUserSyncActionTest.php` (aggregation, `next_since` min-of-truncated, null when none truncate, user/since pass-through) |
| Task 13 | `tests/Unit/Http/Requests/Concerns/PaginatesReadTest.php` (default, clamp low/high, non-numeric, page rules) |
| Task 14 | `tests/Unit/Http/Middleware/EnsureInternalOpsTest.php` (VPC pass, public 403, comma-CIDRs) |
| Task 16 | `tests/Feature/Http/RateLimitTest.php` (limiter callbacks at 180/300, 429 + `Retry-After`, `/up` exempt, `X-RateLimit-*` headers) |
| Task 17 | `tests/Unit/Support/Observability/SlowQueryListenerTest.php` (600 ms logs, 100/499/500 ms silent, threshold const) |

AC 6 feature coverage added in `tests/Feature/Api/V1/Mobile/ShowAppBootstrapTest.php:104-164`:
- `test_cache_hit_on_second_request_issues_zero_db_queries` — filters the query log for the constituent tables, asserts `count == 0` on the second hit.
- `test_flushing_news_tag_busts_the_bootstrap_cache` — flushes the `news` tag, asserts the rebuild re-queries constituents.

### W2 — `DeleteFavoriteCategoryAction` skipped soft-deleted favorites — RESOLVED

`app/Domain/Favorites/Actions/DeleteFavoriteCategoryAction.php:17` now uses `Favorite::withTrashed()->where('category_id', …)->update(…)`. New test `test_it_nulls_category_id_on_soft_deleted_favorites` in `tests/Unit/Domain/Favorites/Actions/DeleteFavoriteCategoryActionTest.php:35-50` confirms a soft-deleted favorite's `category_id` is nulled when its category is deleted.

### W3 — `ShowUserSyncRequest` accepted invalid `since` — RESOLVED

`app/Http/Requests/Sync/ShowUserSyncRequest.php:23` rule changed to `['nullable', 'date']`. `since()` now reads `$this->validated('since')` and constructs `DateTimeImmutable` directly (no try/catch needed). Regression test `test_invalid_since_returns_422` in `tests/Feature/Api/V1/Sync/ShowUserSyncTest.php:156-163` asserts garbage values fail validation.

### W4 — Runbook not updated — RESOLVED

`docs/runbook/cache.md:121-194` adds:
- Bootstrap aggregator section: cache key, TTL, full tag union, manual flush command, cold-miss cost note.
- Health-check section: `/up` vs `/ready` table with the explicit "**The deploy/LB readiness probe MUST point at `/ready`**" warning.
- Rate-limit section: limiter table, exclusions, `TRUSTED_PROXIES` guidance for production.
- Slow-query log section: file path, retention, level, env exclusion, tail command.

`app:bootstrap` also added to the global invalidation table at `docs/runbook/cache.md:71`.

### S1 — `SlowQueryListener` extraction — ADDRESSED

Extracted to `app/Support/Observability/SlowQueryListener.php` (final class, `THRESHOLD_MS = 500` typed const, `register()` and `handle(QueryExecuted)` separated so the unit test can call `handle` directly). `app/Providers/AppServiceProvider.php:117` now reads:

```php
if (! $this->app->environment('local', 'testing')) {
    SlowQueryListener::register();
}
```

Provider imports trimmed (`DB`, `Log`, `Breadcrumb` removed).

## Suggestions

### S2 — Sync builder cursor still ordered by `updated_at` only — NOT ADDRESSED

**File:** `app/Domain/Sync/Sync/Builders/FavoriteSyncBuilder.php:32-33` (and the other six builders)

Same observation as the prior review: the query matches by `updated_at > since OR deleted_at > since` but orders only by `updated_at`. A row whose `deleted_at` is fresh but `updated_at` is stale lands at its old position; if it falls at position `$cap` in a truncated page, `next_since` regresses. Clients dedupe by id (per plan risk §2) so correctness holds, but cursor efficiency degrades on workloads with old rows being soft-deleted in bulk.

**Suggestion:** Order and cursor by `GREATEST(updated_at, COALESCE(deleted_at, updated_at))` so the cursor monotonically advances. Defer if the workload (mobile clients polling daily) doesn't surface this in practice — flag in QA for follow-up if observed.

### S3 — `AppBootstrapResource` is still unused — NOT ADDRESSED

**File:** `app/Http/Resources/Mobile/AppBootstrapResource.php`

The class exists but `ShowAppBootstrapController` returns the action's array directly. `grep` across `app/` and `tests/` confirms no reference. Either wire it through (`AppBootstrapResource::make($result)->response()->header(...)`) or delete the file to avoid implying a contract that nothing enforces. Non-blocking.

## Acknowledged

*(none)*

## Notes

- Side change in `bootstrap/app.php:131-138`: the catch-all renderer now propagates `HttpExceptionInterface::getHeaders()` to the JSON response. Without this, the throttle middleware's `Retry-After` and `X-RateLimit-*` headers were being stripped on 429s, which would have failed AC 16. The change is correct and scoped — `HttpExceptionInterface` headers are an established Symfony contract — and it makes the rate-limit feature tests pass. Worth highlighting for QA so the broader header behaviour is exercised on representative error paths.
- All seven `SyncBuilder` subclasses share `SyncBuilderContractTestCase` — clean shared setup, low duplication.
- `EnsureInternalOps` reads from `config('ops.internal_ops_cidr')` (new `config/ops.php`), which is preferable to reading `env()` directly inside middleware (config is cacheable; env in non-config code breaks `config:cache`).
