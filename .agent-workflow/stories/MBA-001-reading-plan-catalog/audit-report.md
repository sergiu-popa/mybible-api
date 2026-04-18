# Audit Report — MBA-001-reading-plan-catalog

## Summary
The reading-plan catalog slice ships a clean, small, read-only bounded context
that matches the architecture, honours the JSON-only project overrides, and
exercises the eight acceptance criteria end-to-end. `make check` (lint + stan +
76/76 tests / 205 assertions) runs green. The Code Review and QA already
captured the main nits (AC 7 422-vs-fallback conflict now fixed, container-bound
language lookup, missing resource/request unit tests, `resolve-language` scope).
None of those rise to a blocker — they're "Should Fix" or "Minor". Confidence:
**HIGH**.

## Scores

| Dimension | Score (1–5) | Notes |
|---|---|---|
| Architecture Compliance | 4 | Two small deviations from architecture.md: `{slug}` with manual `first()` instead of `{plan:slug}` implicit binding, and the architecture-listed unit tests (`ReadingPlanResourceTest`, `ReadingPlanDayFragmentResourceTest`, `ListReadingPlansRequestTest`, `ShowReadingPlanRequestTest`) were not written — behavior is covered by feature tests. `resolve-language` is wired to the whole `v1` group rather than just the reading-plans subgroup. |
| Code Quality | 4 | Strict types, final classes, explicit return types, PHPDoc on properties and relations. Main quality concern is the service-locator pattern (`app(ResolveRequestLanguage::CONTAINER_KEY)`) in `ReadingPlanResource` and `ReadingPlanDayFragmentResource`: the container binding is set only when the `resolve-language` middleware runs, so any future route that forgets the middleware hard-fails with `BindingResolutionException`. |
| API Design | 5 | `/api/v1` prefix respected. GET-only endpoints with correct 200 / 401 / 404 / 422 status codes. JSON envelope (`{ "message": "...", "errors": {...} }`) routed through a single exception handler. Per-route `api-key` middleware keeps the public-auth surface explicit. Paginated resource shape (`data`, `meta`, `links`) matches Laravel defaults. |
| Security | 5 | `EnsureValidApiKey` compares keys with `hash_equals` and rejects missing/empty headers before iterating. `published()` + soft deletes guarantee drafts, unpublished, and soft-deleted plans never leak. Route-model access is slug-gated via the `published()` scope, so draft enumeration returns 404. No user input flows into `::create()` for these models, so `$guarded = []` poses no mass-assignment risk. No raw SQL; all queries go through Eloquent. |
| Performance | 5 | `(status, published_at)` composite index serves both the `WHERE` filter and `ORDER BY published_at DESC`. Listing endpoint skips `days`/`fragments` eager loading (correct — `days` is conditional on the relation being loaded). Show endpoint uses `withDaysAndFragments()` (`with(['days.fragments'])`) to avoid N+1. Pagination capped at 100 per `ListReadingPlansRequest::MAX_PER_PAGE`. |
| Test Coverage | 4 | 18 reading-plan-scoped tests (feature + 3 unit suites) cover every AC, including the AC 7 fallback regression. Missing: the four unit tests explicitly listed in the architecture testing table (resource-level + request-level). Also no direct unit test for `ResolveRequestLanguage` middleware — it's only exercised implicitly by the feature suite. |

## Issues Found

### Must Fix
- _None._ All acceptance criteria are met, tests and static analysis are
  green, and no security or correctness defects were found.

### Should Fix
- **Resource service-locator fragility.** `app/Http/Resources/ReadingPlans/ReadingPlanResource.php:25`
  and `app/Http/Resources/ReadingPlans/ReadingPlanDayFragmentResource.php:46`
  pull the resolved `Language` from the container using a key bound by
  `ResolveRequestLanguage::handle()`. If a future route forgets the middleware
  (or the middleware is refactored), these resources throw
  `BindingResolutionException` at render time. Preferred fix: thread the
  language explicitly via `->additional(['language' => $language])` from the
  controller, or guard the lookup with
  `app()->bound(ResolveRequestLanguage::CONTAINER_KEY) ? app(...) : Language::En`.
  Previously raised in `review.md` as a Suggestion.
- **Architecture drift on route binding & unit tests.** architecture.md
  specifies `{plan:slug}` implicit model binding for the show endpoint, but
  `routes/api.php:26` uses `{slug}` and `ShowReadingPlanController` resolves
  manually. Same document's testing table lists four unit tests
  (`ReadingPlanResourceTest`, `ReadingPlanDayFragmentResourceTest`,
  `ListReadingPlansRequestTest`, `ShowReadingPlanRequestTest`) that don't
  exist. Either bring the code in line or update `architecture.md` so the
  next reader doesn't chase ghosts.

### Minor
- **`resolve-language` scope.** `routes/api.php:13` applies the middleware to
  the whole `v1` group, including `/auth/*` routes that never read language.
  Move it to the reading-plans subgroup (or wherever future i18n routes
  cluster) to avoid implicit coupling.
- **Redundant `status` field on the public list resource.** The listing is
  already filtered to `Published`, so `status` in
  `ReadingPlanResource::toArray` is a constant string for every row. It adds
  no signal to clients and becomes churn if the status model grows
  (`Archived`, `Retired`, …). Consider dropping it from the public contract.
- **Duplicated api-key config in feature test `setUp`.** `ListReadingPlansTest`
  and `ShowReadingPlanTest` each call `config()->set('api_keys.header', ...)`
  and `config()->set('api_keys.clients', [...])` in `setUp()`. Extract a
  `WithApiKeyClient` trait to DRY and keep future api-key-aware tests
  consistent.
- **Implicit coverage of `ResolveRequestLanguage`.** The middleware is only
  exercised transitively via the feature suite. A small unit test that asserts
  the container binding is attached (matching `Language::En` for
  missing/unknown values, honouring `en`/`ro`/`hu` otherwise) would give
  faster feedback when the middleware is refactored.

## Recommendations
- When MBA-003 / MBA-004 land, they'll add write actions. That's the right
  moment to revisit `$guarded = []` on the three models — either keep it and
  rely on validated DTOs funnelling through Actions, or switch to explicit
  `$fillable`. Either is defensible; a conscious decision documented in that
  story would be ideal.
- The `LanguageResolver::resolve()` signature is static and pure — good. If
  the multilingual pattern spreads beyond reading plans, promote the resolver
  (and the `reading-plans.language` container key) into `App\Domain\Shared`
  to avoid each bounded context inventing its own.
- Consider collapsing the `in:en,ro,hu` vs. `Language::fromRequest` fallback
  debate into a single Form Request rule (e.g. a custom `ValidLanguageOrDefault`
  rule that transforms the value) so any future endpoint automatically picks
  up the AC 7 semantics without re-deriving them.

## Verdict
**AUDIT PASSED** — 0 Must Fix. 2 Should Fix and 4 Minor items captured for
future cleanup; none block shipping.
