# Audit: MBA-016-educational-resources

**Auditor:** Auditor
**Commit under audit:** `19a2c81` (QA PASSED)
**Branch:** `mba-016`

## Verdict

**PASS** → status `done`.

All acceptance criteria hold. One warning found and fixed (validation
convention + silent behaviour mismatch on unknown language codes). Three
suggestions — two already acknowledged in Review (accepted), one folded
into the same fix. Full test suite remains green after fixes (650 tests,
2020 assertions).

## Findings

| # | Issue | Location | Severity | Status | Resolution |
|---|---|---|---|---|---|
| 1 | `language` validation uses `size:2` instead of restricting to `Language::cases()`. Passing an unknown 2-letter code (e.g. `?language=fr`) silently falls back to `en` via `Language::fromRequest()` and then filters the query by `en`, returning misleading English-only results instead of a 4xx. Inconsistent with sibling requests (`ListBibleBooksRequest`, `ListBibleVersionsRequest`, `ListHymnalBooksRequest`), which all use `Rule::in(Language::cases())`. | `app/Http/Requests/EducationalResources/ListResourceCategoriesRequest.php:26` | Warning | Fixed | Replaced `size:2` with `Rule::in(array_map(fn (Language $l) => $l->value, Language::cases()))`. Added a `languageFilter(): ?Language` accessor that returns the typed enum when present, aligning with `ListHymnalBooksRequest::languageFilter()`. Added feature test `test_it_rejects_an_unsupported_language` asserting 422 on `?language=fr`. |
| 2 | Controller gate `if ($request->query('language') !== null && $language instanceof Language)` (Review S1) couples the controller to middleware-set attributes and requires a belt-and-braces `instanceof` check that cannot fail at runtime. | `app/Http/Controllers/Api/V1/EducationalResources/ListResourceCategoriesController.php:34` | Suggestion | Fixed | Refactored controller to call `$request->languageFilter()` and branch on `?Language`. Removed `Language` + `ResolveRequestLanguage` imports that became unused. |
| 3 | `resource_count` silently defaults to `0` when a caller hits `ResourceCategoryResource` without `withResourceCount()` applied (Review S2). | `app/Http/Resources/EducationalResources/ResourceCategoryResource.php:35` | Suggestion | Skipped-with-reason | Accepted in Review. The single caller (`ListResourceCategoriesController`) always applies `withResourceCount()`. Throwing on a missing aggregate adds noise for no realistic reader benefit. |
| 4 | Reconciliation migration backfills `uuid` row-by-row with one `UPDATE` per row (Review S3). | `database/migrations/2026_04_23_120001_reconcile_symfony_resource_tables.php:57` | Suggestion | Deferred-with-pointer | Accepted in Review + `plan.md` § "Risks & notes". Runs once at MBA-020 cutover against a bounded table. Not acceptable for live traffic; no live callers exist pre-cutover because this is a new Laravel-side feature. Revisit if the cutover runbook measures a pain point. |

## Dimensions

- **Architecture compliance.** Matches `plan.md` § "Domain layout" exactly: enum, two models, two QueryBuilders, `MediaUrlResolver`, three invokable controllers, three Form Requests, three API resources, config file. No Action class (justified by pass-through reads; CLAUDE.md § 6). `getRouteKeyName()` returns `'uuid'`; no scoped resolver override; 404 on unknown uuid verified.
- **Code quality.** After the fix, the listing controller uses the established `languageFilter()` precedent. `final class` + `declare(strict_types=1)` + explicit return types throughout. PHPDoc array shapes on translatable fields. No `else`, no magic strings outside the enum.
- **API design.** Endpoints return JSON under `/api/v1`. Correct status codes (200, 401, 404, 422). Form Requests own validation; resources own shaping; error envelope honoured via the framework-wide handler in `bootstrap/app.php`. `Cache-Control: public, max-age=3600` set only on the categories listing, matching AC 3 and the plan's explicit scope.
- **Security.** `api-key-or-sanctum` protects all three routes. No mass-assignment risk (controllers never populate models from request input — all endpoints are read-only). Route-model binding resolves by `uuid` not id — integer-id lookups are denied by test. Unknown `type` values rejected by `Rule::enum`. Media URLs are constructed via the configured `Storage::disk(...)` so raw storage paths never leak. Post-fix, unknown language codes now produce a 422 instead of a silent filter collapse.
- **Performance.** `withResourceCount()` pulls the aggregate in a single query, avoiding an N+1 on the categories listing. `ShowEducationalResourceController` calls `$resource->load('category')` after route-model binding — one extra query per request; acceptable for a detail endpoint and keeps the detail resource free of `whenLoaded` gymnastics. Composite index `(resource_category_id, type, published_at)` declared on the resources table supports the category-filtered, type-filtered, newest-first pagination.
- **Test coverage.** 30 tests covering all 10 ACs plus the new unsupported-language path; 115 assertions. Three feature files mirror the three endpoints; three unit files cover QueryBuilders and `MediaUrlResolver`. Happy paths, filter branches, pagination caps, 401/404/422 edge cases, language fallback, null media paths, integer-id-denied all exercised.

## Verification

- `make lint` — clean (489 files).
- `make stan` — no errors (468 files).
- `make test --filter=EducationalResources` — 30 passed / 115 assertions / 0.85s.
- `make test` (full) — 650 passed / 2020 assertions / 9.15s. No regressions.
