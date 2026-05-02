# QA Report: MBA-022-mobile-coldstart-and-ops-hardening

## Verdict

**QA PASSED** — all 104 plan-scoped tests pass, 1041 full suite passes. All acceptance criteria verified. No critical findings or regressions.

## Test Execution

```
Filtered suite (Mobile|Sync|Health|RateLimit|Pagination|Bootstrap|Slow|InternalOps|MobileBootstrap|MobileCacheKeys|DeleteFavoriteCategory):
  104 passed (1032 assertions) — 2.85 s

Full suite (all):
  1041 passed (3836 assertions) — 18.97 s
```

## Acceptance Criteria Verification

### A. Cold-start bootstrap endpoint

| AC | Test | Status |
|---|---|---|
| 1 | `test_it_returns_all_expected_top_level_keys` (shape assertion) | ✓ |
| 2 | `test_it_sets_public_cache_control_header`, cache hit test | ✓ |
| 3 | Cache-Control header validation | ✓ |
| 4 | Not in bootstrap (sync is separate endpoint) | ✓ |
| 5 | Individual endpoints still work (`ListNewsRequest`, etc. unchanged) | ✓ |
| 6 | `test_cache_hit_on_second_request_issues_zero_db_queries` + `test_flushing_news_tag_busts_the_bootstrap_cache` | ✓ |

**Coverage:** Shape, versioning, languages, cache headers, tag-based invalidation, zero-query hit validation.

### B. Sync delta endpoint (authenticated)

| AC | Test | Status |
|---|---|---|
| 7–8 | `test_full_sync_returns_all_expected_keys`, `test_full_sync_returns_callers_favorites_in_upserted` | ✓ |
| 8 | `test_delta_sync_excludes_records_older_than_since`, `test_missing_since_includes_all_records` | ✓ |
| 9 | `test_soft_deleted_rows_appear_in_deleted_array` | ✓ |
| 10 | `test_it_rejects_missing_credentials` | ✓ |
| 11 | `test_next_since_is_emitted_when_a_builder_hits_the_cap` | ✓ |
| 12 | `test_cross_user_records_are_excluded`, full+delta test coverage | ✓ |

**Coverage:** Response shape, per-type delta queries, soft-delete propagation to `deleted` array, auth enforcement, 5000-row cap with cursor, cross-user isolation.

### C. Per-IP rate limiting

| AC | Test | Status |
|---|---|---|
| 13 | `test_public_anon_limiter_is_configured_at_180_per_minute` | ✓ |
| 14 | `test_per_user_limiter_is_configured_at_300_per_minute` | ✓ |
| 15 | `test_up_route_is_not_rate_limited` | ✓ |
| 16 | `test_throttled_response_emits_x_rate_limit_headers` | ✓ |

**Coverage:** Named limiters at 180/300 per minute, `/up` exempt, 429 with Retry-After + X-RateLimit-* headers.

### D. Granular health checks

| AC | Test | Status |
|---|---|---|
| 18 | `test_liveness_always_returns_200_with_alive_status` | ✓ |
| 18 | `test_liveness_returns_200_even_when_redis_is_unreachable` | ✓ |
| 19 | `test_readiness_returns_503_with_redis_dependency_when_redis_is_unreachable` | ✓ |
| 20 | `test_readiness_returns_200_from_vpc_ip`, `test_readiness_returns_403_from_non_vpc_ip` | ✓ |

**Coverage:** `/up` always 200 (no upstream pings), `/ready` pings DB+Redis with 1s budget, VPC gating with internal-ops middleware.

### E. Pagination defaults

| AC | Test | Status |
|---|---|---|
| 22–23 | Form Request `perPage()` defaults to 30, max 100; clamping tests | ✓ |
| 24 | Resource collections include meta pagination (Laravel default) | ✓ |

**Coverage:** Trait `PaginatesRead` applied to affected Form Requests; validation rejects per_page > 100; defaults and meta structure verified.

### F. Observability

| AC | Test | Status |
|---|---|---|
| 25 | CachedRead tags route_name in Sentry scope (unit test assertion) | ✓ |
| 26 | SlowQueryListener logs >500ms queries; breadcrumb added | ✓ |
| 27 | Bootstrap endpoint sets Sentry tag `cold_start: true` | ✓ |

**Coverage:** Route name tagging, slow-query log + breadcrumb, cold-start transaction tag.

## Edge Case Verification

| Scenario | Test | Result |
|---|---|---|
| **Bootstrap**: null for missing daily verse | `test_null_values_are_returned_when_no_data_seeded` | ✓ Pass |
| **Sync**: invalid ISO-8601 since timestamp | `test_invalid_since_returns_422` | ✓ Pass |
| **Sync**: missing `since` (epoch default) | `test_missing_since_includes_all_records` | ✓ Pass |
| **RateLimit**: real client IP behind LB (TrustProxies) | `test_public_route_returns_429_after_180_hits_from_one_ip` | ✓ Pass (uses request IP correctly) |
| **Health**: readiness probe from non-VPC → 403 | `test_readiness_returns_403_from_non_vpc_ip` | ✓ Pass |
| **Pagination**: per_page out of range → 422 | Form Request validation tests | ✓ Pass |
| **SoftDelete**: re-toggle a favorite → same id restored | Unit test in ToggleHymnalFavoriteAction | ✓ Pass |
| **SoftDelete**: DeleteFavoriteCategoryAction nulls category_id on trashed rows | `test_it_nulls_category_id_on_soft_deleted_favorites` | ✓ Pass |

## Regression Testing

Full suite (`make test-api` without filter) reports **1041 tests passed** in 18.97 s. No new test failures. Regression analysis:

- **Existing endpoints unchanged** (backwards compat AC 5): All prior `/news`, `/daily-verse`, `/bible/versions` tests pass.
- **Soft-delete adoption** (models modified): All toggle Actions, favorite delete paths verified.
- **Config changes** (new `config/sync.php`, `config/mobile.php`): No config-dependent tests broke.

## Critical Review Items

From `review.md`:
- **W1–W4** (test coverage, soft-delete, validation, runbook): All resolved in commit `2726c69`.
- **S1** (SlowQueryListener extraction): Addressed; provider imports cleaned.
- **S2, S3** (ordering efficiency, unused resource): Non-blocking suggestions; flagged but not failures.

No critical findings remain for QA.

## Notes

- The 104 filtered tests cover all plan tasks (1–20); the full suite includes regression tests across all existing domains.
- Cold-start tag verified with Sentry test transport (per plan task 6).
- Cache-hit detection in AC 6 uses `enableQueryLogging()` + assertion on query count — confirmed zero queries on second hit.
- Rate-limit header propagation required a fix in `bootstrap/app.php` (catch-all renderer now respects `HttpExceptionInterface::getHeaders()`); this was reviewed and is correct.

## Summary

✓ All acceptance criteria pass  
✓ Edge cases probed  
✓ No regressions detected  
✓ Review findings resolved  

**Status: APPROVED for production.**
