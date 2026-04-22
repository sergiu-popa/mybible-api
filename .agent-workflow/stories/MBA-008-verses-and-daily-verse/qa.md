# QA: MBA-008-verses-and-daily-verse

**QA:** QA agent
**Verdict:** `QA PASSED`
**Scope:** `make test` full suite + `make test filter=Verses` on branch `mba-008-verses-and-daily-verse` (HEAD `11035e1`).

---

## Test run

- `make test` — **366 passed**, 1136 assertions, 8.18s. No failures, no skipped, no risky.
- `make test filter=Verses` — **37 passed**, 93 assertions, 0.83s.
- `make lint` / `make stan` — previously confirmed clean in review (`11035e1`); not re-run.

## AC coverage

| AC | Test(s) |
|---|---|
| 1. `?reference=` canonical form | `ResolveVerses::it_returns_a_single_verse_by_canonical_reference` |
| 2. Response shape `{ data: [{ version, book, chapter, verse, text }] }`, unpaginated | Asserted across every `ResolveVerses` happy-path test via JSON structure + values |
| 3. Split params; 422 on both-or-neither | `it_accepts_split_parameters`, `it_rejects_when_both_reference_and_split_params_are_provided`, `it_rejects_when_neither_reference_nor_split_params_are_provided` |
| 4. `version` optional with 3-tier fallback | `it_falls_back_to_the_language_default_version` + unit `ResolveVersesRequest::{explicit_version_query_param_wins, embedded_reference_version_beats_user_and_config, user_preferred_version_beats_config_default, language_config_default_is_last_tier_before_failure}` |
| 5. 422 on malformed reference | `it_returns_422_for_unknown_book` + `it_rejects_an_unknown_explicit_version`; `InvalidReferenceException` handler exercised via route |
| 6. Partial resolution → 200 + `meta.missing` | `it_reports_partial_resolution_via_meta_missing` + unit `ResolveVersesAction::{it_resolves_all_verses_with_no_missing, it_reports_missing_verses, it_expands_whole_chapter_references_against_bible_chapters_verse_count, it_returns_empty_when_references_is_empty}` |
| 7. `api-key-or-sanctum` | `ResolveVerses::it_rejects_missing_auth` |
| 8. Daily verse today | `GetDailyVerse::it_returns_todays_daily_verse_when_no_date_is_supplied` |
| 9. `date` query param | `GetDailyVerse::it_returns_the_daily_verse_for_a_past_date` (+ `it_rejects_future_dates`, `it_rejects_malformed_date_format`) |
| 10. 404 on missing | `GetDailyVerse::it_returns_404_when_no_daily_verse_is_configured` |
| 11. Response shape | Asserted by `assertJsonPath` in `GetDailyVerse` happy-path tests (Option A shape: `{ date, reference, image_url }` — `text`/`source` dropped by plan open-question resolution, acknowledged in review) |
| 12. `Cache-Control: public, max-age=3600` | Asserted in `GetDailyVerse::it_returns_todays_daily_verse_when_no_date_is_supplied` |
| 13. Verse feature tests | All present (single/range/mixed/partial/422/auth/default cascade) |
| 14. Daily verse feature tests | today, past, missing, future, malformed, auth present. Language fallback N/A under Option A (no `language` column in `daily_verse`; plan-locked per open question #2). |
| 15. Unit tests for Actions | `ResolveVersesActionTest` (5 tests, inc. query-batching guarantee) + `GetDailyVerseActionTest` (2 tests) |

## Edge-case probes

- **Partial resolution boundary:** unit test confirms `missing` tuples produced with correct `(version, book, chapter, verse)` shape and `null` resolved-verse list preserved when every verse missing.
- **Whole-chapter expansion:** unit test expands against `Bible::verses_count` — confirms the action doesn't blindly trust the request.
- **Query batching:** unit test asserts one catalog query for `BibleVersion` + one for `BibleBook` + N verse queries per `(version, book, chapter)` group (no N+1).
- **Version cascade coverage:** all four tiers probed at unit level, plus integration via the `language` config fallback in feature.
- **Malformed date on daily verse:** `YYYY/MM/DD` / non-date strings rejected 422.
- **Future date on daily verse:** rejected 422 via `before_or_equal:today`.
- **Unknown explicit version:** rejected 422 with `errors.version` envelope (not bubbled as 500).

## Regressions

No regressions. Full suite of 366 tests passes; MBA-006 (reference parser) and MBA-007 (Bible catalog) tests remain green alongside the new Verses tests. `BibleVerseQueryBuilder::lookupReferences()` extension is additive; existing `BibleVerse` query paths unaffected.

## Notes

- AC 14's "language fallback" requirement is moot under Option A (plan open-question #2 — `daily_verse` has no `language` column; schema locked in shared DB during migration). Documented in plan + review; not a QA blocker.
- Review left 6 Suggestions, all explicitly non-blocking. No Critical items outstanding.
