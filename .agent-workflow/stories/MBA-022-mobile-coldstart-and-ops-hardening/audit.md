# Audit: MBA-022-mobile-coldstart-and-ops-hardening

## Verdict

**PASS** — one Warning fixed (W1). Three Suggestions accounted for (S1, S2, S3) — two carried forward from review with the same disposition; one new and deferred. No Critical findings. Status moves to `done`.

Filtered scope tests after the fix: **101 passed (1026 assertions)** in 2.52 s.
Full suite: **1041 passed (3836 assertions)** in 17.08 s.
Lint: PASS (777 files). Static analysis: PASS (753 files, 0 errors).

## Audit dimensions evaluated

| Dimension | Result |
|---|---|
| Architecture compliance (Beyond CRUD layers, Domain/Action/DTO/Resource) | OK |
| API design (verbs, status codes, JSON envelope, versioning) | OK |
| Code quality (final classes, strict types, return types, naming) | OK |
| Security (auth scoping, IP trust, CIDR check, no user-id in body) | OK |
| Performance (cache TTL, indexes, N+1) | OK |
| Test coverage (plan tasks, AC mapping, regressions) | OK after fix |

## Issues

| # | Issue | Location | Severity | Status | Resolution |
|---|---|---|---|---|---|
| 1 | `AppBootstrapResource` is dead code — class exists but `ShowAppBootstrapController` returns the action's array directly via `response()->json(['data' => …])`. No reference anywhere in `app/` or `tests/`. Dead `JsonResource` is a no-op passthrough that implies a contract nothing enforces. | `app/Http/Resources/Mobile/AppBootstrapResource.php` | Warning | Fixed | Deleted the file. The bootstrap payload's shape is owned by `ShowAppBootstrapAction` and exercised by `ShowAppBootstrapTest::test_it_returns_all_expected_top_level_keys`; a passthrough resource adds nothing. Carried over from review S3 (which left it open). |
| 2 | Sync builder duplication: 7 `*SyncBuilder` classes are ~58 LOC each and differ only by model class, resource class, and optional eager-load (`with(...)`). ~350 LOC of structural copy-paste. | `app/Domain/Sync/Sync/Builders/*.php` | Suggestion | Deferred | Defer to a follow-up refactor story. The duplication is shallow and each builder is independently shippable; consolidating into an `AbstractSyncBuilder<T extends Model>` template-method base is a non-trivial refactor that the reviewer did not flag and is outside the audit's "fix and refine" remit. Add to the cross-story Deferred Extractions register at 7/8 (extract on the next sync builder added). |
| 3 | Cursor monotonicity on bulk soft-delete: builders order by `updated_at` only and emit `next_since = last_row->updated_at`. A row whose `deleted_at > since` but whose `updated_at <= since` lands at its old position; if it falls at position `$cap` in a truncated page, the cursor advances by one row, not by the deleted_at value, so the same row resurfaces in the next page until its updated_at moves. Correctness holds (clients dedupe by id, per plan §2). | `app/Domain/Sync/Sync/Builders/*.php:32-34, 52-54` | Suggestion | Deferred | Same disposition as review S2: defer until observed. Mobile clients poll daily, so workloads with bulk soft-delete bursts of >5 000 rows (the `next_since` cap) are unlikely in practice. Track in QA — if surfaces, switch the order/cursor expression to `GREATEST(updated_at, COALESCE(deleted_at, updated_at))`. |
| 4 | `DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string) $row->updated_at) ?: null` silently drops `next_since` if Carbon's string format ever differs (e.g. a model overrides `$dateFormat`, Laravel changes the default to include microseconds, or a test injects a Carbon with sub-second precision). The fallback masks truncation: clients would see `next_since=null` despite a partial page, miss the cursor advance, and the truncated rows would never re-page. | `app/Domain/Sync/Sync/Builders/*.php:52-54` (each of the 7 builders) | Suggestion | Deferred | All 8 affected models use the framework default `Y-m-d H:i:s` Carbon serialisation (verified — none override `$dateFormat`), so the path is exercised correctly today and the test suite passes. Documented here so a future change to model date formats triggers a re-evaluation. The robust replacement is `Carbon::parse((string) $row->updated_at)->toDateTimeImmutable()`; defer because this story does not change Carbon serialisation and the brittleness is contingent on a future change. |

## Notes

- The earlier Code Reviewer's S3 (`AppBootstrapResource` unused) is now resolved by deletion (not by wiring it through). Wiring a no-op resource through the controller would have added ceremony without contract; deletion keeps the surface tight.
- Issues 3 and 4 both affect the same line range in the same seven builders; consolidating the builders (Issue 2) would let both fixes land in one place. Tracked together for the follow-up story.
- All other surfaces (bootstrap aggregator, rate limiters, health split, internal-ops middleware, slow-query listener, pagination trait, soft-delete migration + restore-or-create toggles) read clean: final classes, strict types, explicit return types, controllers delegate to actions, form requests own validation, resources own response shape (where used), no `$request->validate()` inline, no Eloquent models leaked from controllers, JSON envelope consistent.
- The catch-all renderer change in `bootstrap/app.php:132-138` (propagating `HttpExceptionInterface::getHeaders()` so 429 carries `Retry-After` / `X-RateLimit-*`) is correct and load-bearing for AC 16; QA flagged it explicitly.
- Restore-or-create on the three toggle Actions (`ToggleHymnalFavoriteAction`, `ToggleDevotionalFavoriteAction`, `ToggleSabbathSchoolFavoriteAction`) wraps the lookup-then-act sequence in `DB::transaction` with `lockForUpdate()`, which is the right concurrency guard — a duplicate toggle from a retry can't race against the unique index.
- `EnsureInternalOps` reads from `config('ops.internal_ops_cidr')` (cacheable) rather than `env(...)` directly inside the middleware — correct posture for `config:cache`.
- `CachedRead::tagSentryScope` now sets `route_name` (per AC 25) only when `request()->route()?->getName()` resolves — the conditional avoids polluting Sentry with a `route_name=null` tag on out-of-request executions (e.g. queued jobs touching the cache).
