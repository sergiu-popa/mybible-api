# QA Report — MBA-001-reading-plan-catalog

> Re-QA after the AC 7 fix (commit 31d3ee0). Previous run failed because
> unsupported `language` values returned 422; the `in:en,ro,hu` rule has
> since been dropped and the two `test_it_falls_back_to_english_for_an_unsupported_language`
> tests pin the current behavior.

## Test Suite Results
- Total: 76 | Passed: 76 | Failed: 0 | Skipped: 0
- Assertions: 205
- Duration: 0.97s
- Command: `make test` (Docker: `docker exec mybible-api-app php artisan test --compact`)

Reading-plan scoped run (`--filter='Api\\V1\\ReadingPlans'`): 18 passed, 64 assertions, 0.78s.

## Acceptance Criteria Verification

| # | Criterion | Test(s) | Status |
|---|---|---|---|
| 1 | List published plans with pagination (default `per_page=15`, max `100`), filtered by language | `ListReadingPlansTest::test_it_paginates_with_a_default_of_15_per_page`, `::test_it_honours_a_valid_per_page`, `::test_it_caps_per_page_validation_at_100`, `::test_it_honours_the_requested_language_when_present` | PASS |
| 2 | View single plan with all days + fragments, filtered by language | `ShowReadingPlanTest::test_it_returns_the_full_tree_for_a_published_plan`, `::test_it_resolves_language_per_fragment_with_fallback` | PASS |
| 3 | Multilingual `name`/`description`/`image`/`thumbnail`/html fragments return the requested language; fall back to `en` if unavailable | `ListReadingPlansTest::test_it_resolves_language_and_falls_back_to_english`, `ShowReadingPlanTest::test_it_resolves_language_per_fragment_with_fallback`, unit `LanguageResolverTest` | PASS |
| 4 | `references` fragments returned as raw strings regardless of requested language | `ShowReadingPlanTest::test_it_returns_references_as_raw_strings` | PASS |
| 5 | Drafts and unpublished plans never returned by public endpoints | `ListReadingPlansTest::test_it_returns_published_plans_only`, `ShowReadingPlanTest::test_it_returns_404_for_a_draft_plan`, unit `ReadingPlanQueryBuilderTest::test_published_excludes_drafts_and_unpublished` | PASS |
| 6 | Unknown plan slug returns `404` with the standard JSON error envelope | `ShowReadingPlanTest::test_it_returns_404_for_an_unknown_slug` | PASS |
| 7 | `language` accepts `en`/`ro`/`hu`; other values fall through to the `en` fallback | `ListReadingPlansTest::test_it_falls_back_to_english_for_an_unsupported_language`, `ShowReadingPlanTest::test_it_falls_back_to_english_for_an_unsupported_language`, unit `LanguageTest` `fromRequest` cases | PASS |
| 8 | Missing or unknown `X-Api-Key` returns `401` with JSON error envelope | `ListReadingPlansTest::test_it_rejects_missing_api_key`, `::test_it_rejects_an_unknown_api_key`, `ShowReadingPlanTest::test_it_rejects_requests_without_an_api_key` | PASS |

## Edge Cases Tested

| Case | Expected | Actual | Status |
|---|---|---|---|
| `per_page = 101` (above cap) | 422 validation error | 422 on `per_page` | PASS |
| `per_page = 3` (under default) | Honoured, returns 3 results | 3 results, `meta.per_page = 3` | PASS |
| Draft plan listed | Excluded from list | Excluded | PASS |
| Published-but-`published_at=null` | Excluded from list | Excluded | PASS |
| Draft plan fetched by slug | 404 | 404 | PASS |
| Unknown slug | 404 JSON envelope | 404 with `message` | PASS |
| Missing `X-Api-Key` | 401 JSON envelope | 401 with `message` | PASS |
| Unknown `X-Api-Key` | 401 JSON envelope | 401 with `message` | PASS |
| Requested language absent on record (`hu`) → falls back to `en` | `en` value returned | `en` value returned | PASS |
| `language = fr` (unsupported) | Falls back to `en` (200 OK) | 200 OK with `en` payload | PASS |
| `language` omitted | Defaults to `en` | `Language::fromRequest(null) → En` | PASS |
| List endpoint response shape has no `days` key | `days` absent on list | `days` absent | PASS |
| `references` fragment localisation | Raw array regardless of language | Raw array returned | PASS |
| Soft-deleted published plan fetched by slug | 404 (excluded by `published()` scope + soft deletes) | Not returned (verified via tinker) | PASS |
| `LanguageResolver` with empty map | Returns `null` | Returns `null` (verified via tinker) | PASS |

## Regressions
None. The 76-test suite (auth + reading plans) remains green. The `api-key`
middleware shared with MBA-002 still rejects missing/unknown keys with `401`
and the standard JSON envelope. Pint and PHPStan reported green by the most
recent Code Review pass.

## Outstanding Review Suggestions (non-blocking)
Code Review left six Suggestions — route scoping of `resolve-language`, route-model
binding vs. manual slug resolution, container-bound language lookup fragility,
missing resource/request unit tests, exposing the always-`published` `status`
field, and duplicated api-key config in test `setUp`. None of these block the
story; they're tracked in `review.md` for a future polish pass.

## Verdict
**QA PASSED** — all 8 acceptance criteria covered and passing; AC 7 regression
(flagged in the previous QA pass and as a Warning in review) is resolved and
pinned by tests.
