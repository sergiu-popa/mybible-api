# Audit — MBA-026 Resource Books & Polymorphic Downloads

## Summary

Re-audited the implementation post-QA. Architecture, security, and HTTP
contract are sound: middleware chain (`api-key-or-sanctum` for public/anon,
`auth:sanctum + super-admin` for admin), rate limiter `downloads` keyed per
`(ip, device_id)` with a `'no-device'` IP-only fallback, polymorphic
morphMap registered for the three downloadables only (Sanctum tokenables
keep FQCN — the W5 deviation is justified), idempotent migrations with
explicit ETL backfill and IP drop, tag-based cache invalidation on every
write path. No new Critical or Warning was uncovered beyond the four already
addressed in review (W1–W4).

The five non-blocking suggestions S1–S5 are all addressed in code with no
behavioural drift; full suite is green at 1221 tests / 4522 assertions
(+1 new test for S5).

One latent perf nit beyond S3 was found and folded into the S3 fix:
`ShowResourceBookAction` eager-loads `chapters` but does **not** call
`withCount('chapters')`, so the `ResourceBookListResource::toArray`
fallback `$this->chapters_count ?? $this->chapters()->count()` was firing
a redundant `SELECT COUNT(*)` on every cache miss for the book-detail
endpoint. Resolved by reading off the loaded relation when present.

## Issues

| # | Issue | Location | Severity | Status | Resolution |
|---|---|---|---|---|---|
| 1 | Redundant `orderBy('position')` in eager-load closure — relation already orders | `app/Domain/EducationalResources/Actions/ShowResourceBookAction.php:26-28` | Suggestion (S1) | Fixed | Closure removed; `$book->load('chapters')`. |
| 2 | `per_page` literal `15` inlined in controller — not grep-able with the cache-key contract | `app/Http/Controllers/Api/V1/EducationalResources/ListResourceBooksController.php:20` | Suggestion (S2) | Fixed | Promoted to `ListResourceBooksAction::DEFAULT_PER_PAGE`; controller references the constant. |
| 3 | `ResourceBookListResource` fallback `$this->chapters_count ?? $this->chapters()->count()` hides a real extra `SELECT COUNT(*)` on book-detail because that caller eager-loads `chapters` without `withCount` | `app/Http/Resources/EducationalResources/ResourceBookListResource.php:29` | Suggestion (S3, extended) | Fixed | New `resolveChapterCount()` prefers the loaded relation (`relationLoaded('chapters') → $this->chapters->count()`) before falling back to `chapters_count` then a fresh `count()`. Comment documents the dual-caller contract. PHPStan clean. |
| 4 | Summary rows ordered only by `date` — non-deterministic across `downloadable_type / downloadable_id / language` | `app/Domain/Analytics/Actions/SummariseResourceDownloadsAction.php:54` | Suggestion (S4) | Fixed | Added `orderBy('downloadable_type')`, `orderBy('downloadable_id')`, `orderBy('language')` after `orderBy('date')`. Stable output for dashboards. |
| 5 | Missing `Cache-Control: max-age=3600` assertion on book detail (AC §13 symmetry with list + chapter cache assertions) | `tests/Feature/Api/V1/EducationalResources/ShowResourceBookTest.php` | Suggestion (S5) | Fixed | Added `test_it_sets_public_cache_headers` asserting `public` + `max-age=3600` on `resource-books.show`. |
| 6 | W5 — plan deviation: `Relation::morphMap` instead of `enforceMorphMap` | `app/Providers/AppServiceProvider.php:140-144` | Suggestion (W5 carry-over) | Skipped (justified) | Sanctum `personal_access_tokens.tokenable_type` and other in-app polymorphs would break under `enforceMorphMap`; comment in the provider documents the constraint. Acknowledged in review. |

## Verification

- `make lint-fix` — clean.
- `make stan` — `[OK] No errors`.
- `make test-api filter=ResourceBook` — 41 tests, 126 assertions ✅ (was 40 pre-S5).
- `make test-api filter=ResourceDownload` — 11 tests, 159 assertions ✅.
- `make test-api` (full suite) — **1221 tests, 4522 assertions ✅**, no regressions
  (was 1220 / 4519 — delta accounted for entirely by the S5 cache-header test).

## Verdict

**PASS** — status → `done`.

All four blocking warnings (W1–W4) were resolved in the prior round with
dedicated coverage; the five suggestions (S1–S5) plus the S3-extension perf
fix are now also resolved with no behavioural drift. Justified deviation W5
remains documented in the provider.
