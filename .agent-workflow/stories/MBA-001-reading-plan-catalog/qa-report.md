# QA Report — MBA-001-reading-plan-catalog

## Test Suite Results
- Total: 74 | Passed: 74 | Failed: 0 | Skipped: 0
- Assertions: 199
- Duration: 1.09s
- Command: `make test` (Docker: `docker exec mybible-api-app php artisan test --compact`)

## Acceptance Criteria Verification

| # | Criterion | Test(s) | Status |
|---|---|---|---|
| 1 | List published plans with pagination (default `per_page=15`, max `100`), filtered by language | `ListReadingPlansTest::test_it_paginates_with_a_default_of_15_per_page`, `::test_it_honours_a_valid_per_page`, `::test_it_caps_per_page_validation_at_100`, `::test_it_honours_the_requested_language_when_present` | PASS |
| 2 | View single plan with all days + fragments, filtered by language | `ShowReadingPlanTest::test_it_returns_the_full_tree_for_a_published_plan`, `::test_it_resolves_language_per_fragment_with_fallback` | PASS |
| 3 | Multilingual `name`/`description`/`image`/`thumbnail`/html fragments return the requested language; fall back to `en` if unavailable | `ListReadingPlansTest::test_it_resolves_language_and_falls_back_to_english`, `ShowReadingPlanTest::test_it_resolves_language_per_fragment_with_fallback`, unit `LanguageResolverTest`, `ReadingPlanResourceTest` (via feature) | PASS |
| 4 | `references` fragments returned as raw strings regardless of requested language | `ShowReadingPlanTest::test_it_returns_references_as_raw_strings` | PASS |
| 5 | Drafts and unpublished plans never returned by public endpoints | `ListReadingPlansTest::test_it_returns_published_plans_only`, `ShowReadingPlanTest::test_it_returns_404_for_a_draft_plan`, unit `ReadingPlanQueryBuilderTest::test_published_excludes_drafts_and_unpublished` | PASS |
| 6 | Unknown plan slug returns `404` with the standard JSON error envelope | `ShowReadingPlanTest::test_it_returns_404_for_an_unknown_slug` | PASS |
| 7 | `language` accepts `en`/`ro`/`hu`; **other values fall through to the `en` fallback** | ❌ No test. Actual behavior: `ListReadingPlansRequest.php:27` / `ShowReadingPlanRequest.php:23` apply `in:en,ro,hu`, so `?language=fr` returns **HTTP 422** instead of silently falling back to `en`. | **FAIL** |
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
| `language = fr` (unsupported) | Per AC 7, fall back to `en` (200 OK) | **422 validation error** from `in:en,ro,hu` rule | **FAIL** |
| `language` omitted | Defaults to `en` | Defaults to `en` (`Language::fromRequest(null)` → `En`) | PASS |
| List endpoint response shape has no `days` key | `days` absent on list | `days` absent | PASS |
| `references` fragment localisation | Raw array regardless of language | Raw array returned | PASS |

## Regressions
None found. The 74-test suite (including prior auth suite) remains green. Pint and PHPStan are reported green by the Code Review.

## Verdict
**QA FAILED** — 1 critical issue remains.

### Blocking Issues

1. **AC 7 not met — unsupported languages return 422 instead of falling back to `en`.**
   - Location: `app/Http/Requests/ReadingPlans/ListReadingPlansRequest.php:27`, `app/Http/Requests/ReadingPlans/ShowReadingPlanRequest.php:23`.
   - Story contract (AC 7): *"The `language` query parameter accepts any of the supported languages (`en`, `ro`, `hu`); other values fall through to the `en` fallback."*
   - Current behavior: `['nullable', 'string', 'in:en,ro,hu']` rejects any unknown value with an HTTP 422 `ValidationException`.
   - Resolution (code path — preferred because `Language::fromRequest()` already implements the fallback and is unit-tested by `LanguageTest`):
     1. Drop the `in:en,ro,hu` rule from both Form Requests (keep `nullable|string`).
     2. Add feature tests — one per endpoint — asserting that `?language=fr` returns 200 with the `en`-fallback payload (e.g. `test_it_falls_back_to_english_for_an_unsupported_language`).
   - Alternative (spec path): amend story AC 7 to match the stricter 422 behavior; add a test pinning the 422 response.
   - Note: this was previously raised as a Warning in `review.md` and remains unresolved. It's a direct AC miss, so it's elevated to blocking here.

### Re-QA checklist
- [x] Decide AC 7 interpretation (code vs. spec). _Code path chosen — drop `in:` rule and let `Language::fromRequest()` fall back to `en`._
- [x] Implement the chosen path in both `ListReadingPlansRequest` and `ShowReadingPlanRequest`.
- [x] Add one feature test per endpoint pinning the behavior. _Added `test_it_falls_back_to_english_for_an_unsupported_language` in both `ListReadingPlansTest` and `ShowReadingPlanTest`._
- [x] Re-run `make check` (lint + stan + test) and rerun QA. _Lint + stan green; 76 tests passed (205 assertions)._
