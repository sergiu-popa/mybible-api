# QA: MBA-007-bible-catalog

## Verdict

**QA PASSED** → status `qa-passed`.

## Suite results

- `php artisan test --compact` → **335 passed** (1052 assertions), 7.19s.
- Bible-scoped filter → **53 passed** (272 assertions).
- `make stan` → 0 errors (214 files).
- `make lint` → clean (233 files).

No regressions in the reading-plans / auth / reference suites.

## Acceptance criteria coverage

| AC | Test | Status |
|---|---|---|
| 1. List all versions | `ListBibleVersionsTest::test_it_lists_all_versions_for_authenticated_api_key` | Pass |
| 2. Paginated shape, default 50, cap 100 | `test_it_defaults_per_page_to_fifty`, `test_it_rejects_per_page_over_one_hundred`, unit `ListBibleVersionsRequestTest` | Pass |
| 3. `?language=` filter | `test_it_filters_by_language` | Pass |
| 4. Auth gate | `test_it_rejects_missing_api_key` | Pass |
| 5. `Cache-Control: public, max-age=3600` + ETag | `test_it_lists_all_versions…` asserts header + ETag | Pass |
| 6. Full export `version/books/chapters/verses` | `ExportBibleVersionTest::test_it_streams_the_full_version_as_structured_json` | Pass |
| 7. Response shape documented | same test + unit `BibleVersionResourceTest` | Pass |
| 8. `max-age=86400` + strong ETag | same test + `test_it_returns_304_when_if_none_match_matches_etag` | Pass |
| 9. 404 on unknown version | `test_it_returns_404_for_an_unknown_abbreviation` | Pass |
| 10. `GET /books?language=...` shape | `ListBibleBooksTest::test_it_returns_all_sixty_six_books_in_canonical_order` + `test_it_honours_an_explicit_language` | Pass |
| 11. Language defaults to resolved middleware attribute | `test_it_defaults_name_to_english_when_no_language_is_specified` | Pass |
| 12. Not paginated | `test_it_does_not_paginate` | Pass |
| 13. `GET /books/{book}/chapters` shape | `ListBibleBookChaptersTest::test_it_resolves_the_book_by_abbreviation` | Pass |
| 14. Book resolved by id OR abbreviation | Route bound to `{book:abbreviation}` only (see Observation) | Deviation — tracked |
| 15. 404 on unknown book | `test_it_returns_404_for_an_unknown_abbreviation` | Pass |
| 16. Standard `{ message, errors }` envelope | Exercised by `test_it_rejects_an_unsupported_language` (422 validation) | Pass |
| 17. Feature tests for each endpoint + 404 paths | Four feature test files present, auth + 404 covered | Pass |
| 18. ETag 304 round-trip | Both list + export have dedicated 304 round-trip tests | Pass |

## Observations (non-blocking)

- **AC 14 — id-or-abbreviation resolution.** The story asked for `GET /books/{book}/chapters` to resolve by id **or** abbreviation. The approved plan (§HTTP endpoints) scoped the route binding to `{book:abbreviation}` only, and the Engineer implemented it that way. A numeric id will currently 404. This is a plan-level deviation from the story, not a bug in the implementation — flagging here so the product stakeholder can confirm the id path is not needed before prod cutover. No action required from this story.
- **Code-review suggestions.** Five non-blocking items in `review.md` (exporter double-query, cache-header aggregate consolidation, `resolve-language` middleware overscope on export/chapters, `short_names` seeded with long names, redundant `@var` in exporter) remain open by design — none are blockers for QA.

## Regression probes

- Ran full suite: reading-plans lifecycle, auth (login/register/me/logout/reset), reference parser, language-resolver middleware, api-key middleware — all green.
- No new PHPStan errors introduced.
- No new Pint violations.
