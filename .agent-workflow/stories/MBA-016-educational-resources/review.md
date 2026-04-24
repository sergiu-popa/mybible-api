# Code Review: MBA-016-educational-resources

**Reviewer:** Code Reviewer
**Commit under review:** `7e186ca`
**Branch:** `mba-016`

## Verdict

**APPROVE** → status `qa-ready`.

All acceptance criteria in `story.md` map to concrete code and tests. Plan
tasks 1–28 are implemented. `make lint`, `make stan`, and
`make test filter=EducationalResources` all pass (29 tests, 112 assertions,
phpstan 0 errors, pint clean).

## Summary of Findings

- **Critical:** none.
- **Warning:** none.
- **Suggestion:** 3 (all acknowledged/minor, non-blocking).

## Plan Adherence

- Domain layout matches `plan.md` § "Domain layout" exactly: enum,
  models, two QueryBuilders, `MediaUrlResolver`, three invokable
  controllers, three Form Requests, three API resources, config file.
- Route-model binding: `{category}` binds `ResourceCategory` by default
  `id`; `{resource:uuid}` resolved via `EducationalResource::getRouteKeyName()`
  returning `'uuid'`. No custom `resolveRouteBinding()` override — correct per
  plan (no published/soft-delete scope in play). 404 on missing row verified
  by `test_it_returns_404_for_unknown_uuid` and
  `test_it_does_not_accept_lookup_by_integer_id`.
- Migration strategy matches plan: one create migration guarded by
  `! Schema::hasTable('resource')` and a reconciliation migration guarded by
  `Schema::hasTable('resource')` that renames, backfills UUIDs, and adds the
  composite index. Both paths converge on the same target schema.
- Cache-Control header scoped to the categories listing only, per AC 3
  and the plan's explicit note that the two resource endpoints stay
  uncached.
- No Action class introduced — consistent with the plan's "helper needs
  a named consumer" rationale and CLAUDE.md § 6 pattern for pass-through
  reads.

## Acceptance Criteria Coverage

| AC | Endpoint / Behaviour | Covered |
|---|---|---|
| 1 | `GET /api/v1/resource-categories` shape, per-page 50 default, max 100 | `ListResourceCategoriesTest::test_it_returns_categories_with_resource_count` + `test_it_paginates_with_a_default_of_50_per_page` + `test_it_caps_per_page_validation_at_100` |
| 2 | `api-key-or-sanctum` on categories | `test_it_rejects_missing_api_key`, `test_it_rejects_an_unknown_api_key` |
| 3 | `Cache-Control: public, max-age=3600` | `test_it_sets_cache_control_header` |
| 4 | Resources by category, `type` filter, shape, 25/page, max 100 | `ListResourcesByCategoryTest::test_it_returns_resources_for_the_given_category`, `test_it_filters_by_type`, `test_it_paginates_with_a_default_of_25_per_page` |
| 5 | Newest-first ordering | `test_it_orders_resources_newest_first` + unit `test_latest_published_orders_newest_first` |
| 6 | Resource detail shape incl. nested category | `ShowEducationalResourceTest::test_it_returns_full_detail_for_a_resource` (asserts all keys via `assertJsonStructure` + `assertJsonPath`) |
| 7 | UUID route-model binding; unknown uuid ⇒ 404 | `test_it_returns_404_for_unknown_uuid`, `test_it_does_not_accept_lookup_by_integer_id` |
| 8 | `api-key-or-sanctum` on detail | `test_it_rejects_missing_api_key` |
| 9 | Feature tests for listing, filter, detail, 404, pagination | All three feature files present |
| 10 | Unit tests for domain helpers | Three unit test files cover both QueryBuilders and the `MediaUrlResolver` |

## Suggestions (non-blocking)

- [x] **S1:** `app/Http/Controllers/Api/V1/EducationalResources/ListResourceCategoriesController.php:34` — the guard `if ($request->query('language') !== null && $language instanceof Language)` relies on middleware's default `En` fallback to keep `$language` as a `Language` instance; the `instanceof` is a belt-and-braces check that can never fail given the current middleware. Not harmful; leaving for defensive clarity. — acknowledged: stylistic, no functional risk.
- [x] **S2:** `app/Http/Resources/EducationalResources/ResourceCategoryResource.php:35` — `resource_count` defaults to `0` via `(int) ($this->resource_count ?? 0)`. If a caller ever hits the resource without `withResourceCount()` in play, they silently see `0` instead of an error. The current code path always runs `withResourceCount()` so this is safe in practice. — acknowledged: accepted trade-off; the alternative (throwing when aggregate is missing) adds noise for no realistic caller benefit.
- [x] **S3:** `database/migrations/2026_04_23_120001_reconcile_symfony_resource_tables.php:52` — UUID backfill iterates row-by-row with one `UPDATE` per row. Acceptable because this runs exactly once at MBA-020 cutover on a table of known-bounded size (per plan's "Risks & notes"). Flagged here for traceability. — acknowledged: plan explicitly accepts this per-row cost for the cutover.

## What I Checked

- All 26 source files in the diff (migrations, config, models, query
  builders, support, requests, resources, controllers, factories,
  routes).
- All 6 test files (3 feature, 3 unit).
- Compliance with CLAUDE.md project overrides: no Blade/Livewire,
  routes under `/api/v1`, controllers delegate to query builders /
  resources only, Form Requests used for validation, Resources used for
  response shaping, JSON error envelope untouched.
- Middleware attribute pattern: `$request->attributes->get(ResolveRequestLanguage::ATTRIBUTE_KEY, Language::En)` is used in all three resources and the categories controller, honouring the CLAUDE.md § 2 "Middleware → Downstream Data Passing" rule.
- No constant-field leak: `language` column on the category response is
  only constant when a caller filters by `?language=ro`; the unfiltered
  listing mixes locales, so the field carries information. AC 1
  explicitly names `language` in the response envelope, so it stays.
- `make lint`, `make stan`, `make test filter=EducationalResources`
  all green.
