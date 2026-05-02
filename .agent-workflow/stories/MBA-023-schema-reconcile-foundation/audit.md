# Audit: MBA-023-schema-reconcile-foundation

## Verdict

**PASS** — Story is risky (broad schema reconcile + new parser surface +
backfill ETL), but Review/QA caught the load-bearing concerns, the
prior W1–W7 fixes hold, and one promoted Warning (silent VerseRange
filter in collections) is now resolved with test coverage. Two of the
five open Suggestions from the Review have been folded in as documented
fixes; the remaining three are deferred as polish with rationale.

Full test suite: **1080 passed** (3967 assertions). Lint + stan clean.

---

## Issues

| #  | Issue                                                                                                       | Location                                                                                              | Severity   | Status              | Resolution |
|----|-------------------------------------------------------------------------------------------------------------|-------------------------------------------------------------------------------------------------------|------------|---------------------|------------|
| A1 | `ResolveCollectionReferencesAction` silently dropped `VerseRange` entries (parser change in this story made it reachable for any admin who enters cross-chapter syntax). | `app/Domain/Collections/Actions/ResolveCollectionReferencesAction.php:61-65` (pre-fix)               | Warning    | Fixed               | Action now short-circuits with `parseError: 'Cross-chapter ranges are not supported in collections.'` and logs a `warning` when any parsed entry is a `VerseRange`. New unit test `test_it_returns_parse_error_for_cross_chapter_ranges` pins the contract. (Review S1, promoted to Warning because the parser change in this same story enables the silent path.) |
| A2 | `LegacyLanguageCodeMap` includes `ger → de` outside AC §12 with no explanation.                              | `app/Domain/Migration/Support/LegacyLanguageCodeMap.php:23`                                           | Suggestion | Fixed               | PHPDoc now explains `deu` is ISO-639-2/T (the AC-listed code) and `ger` is the alternative bibliographic 639-2/B code, kept defensively. Map unchanged. (Review S3.) |
| A3 | `ReconcileTableHelper::rename` is unsafe to re-run after a partially-failed cutover that has been hand-corrected (operator-populated target makes the next run a silent no-op). | `app/Domain/Migration/Support/ReconcileTableHelper.php:24-32`                                         | Suggestion | Fixed               | Added a PHPDoc paragraph spelling out the partial-failure hazard so any future operator who hand-recovers between attempts knows to verify both tables before re-running. (Review S5.) |
| A4 | `BackfillLegacyBookAbbreviationsAction` looks up the map with `whereRaw('LOWER(name) = ?')`, forcing a full table scan instead of using the `(name, language)` UNIQUE. | `app/Domain/Migration/Actions/BackfillLegacyBookAbbreviationsAction.php:65-67`                        | Suggestion | Skipped-with-reason | One-shot migration over a 132-row reference table; cost is irrelevant in absolute terms. The current Action also caches the lookup per (table, column) walk, so even a hot path takes O(unique_inputs) lookups, not O(rows). Index-friendly lookup would require a normalisation pass on the seed data and an ALTER on the temp table; not worth the change for a migration that runs once per environment. (Review S2.) |
| A5 | `VerseRange` constructor builds the canonical-string error message from out-of-bounds field values (e.g. negative chapters), producing slightly weird strings. | `app/Domain/Reference/VerseRange.php:26-52`                                                           | Suggestion | Deferred-with-pointer | Constructor checks are pure defense-in-depth — the parser already validates these bounds before constructing the VO (`ReferenceParser::parseCrossChapterRange:159-178`). The only real-world callers are tests that intentionally hit the constructor with bad data, where the weird-canonical-string message is acceptable diagnostic output. Tracking note: when an additional caller is added (e.g. an admin import path), pass a `?string $context = null` so the friendlier source string flows through; capture as a follow-up under the eventual MBA-024/MBA-027 admin-import work that introduces the second caller. (Review S4.) |

---

## Notes — Conformance & risk dimensions

### Architecture compliance
- Domain layout matches plan: `App\Domain\Reference\VerseRange`,
  `App\Domain\Bible\Exceptions\VerseRangeTooLargeException`, and the
  `App\Domain\Migration\…` namespace for one-shot ETL helpers all sit
  where the plan placed them.
- Controllers stay thin — `ResolveVersesController` delegates to
  `ResolveVersesAction`; the new admin `ValidateReferenceController`
  is invokable and side-effect-free.
- Form Request normalises version onto every parsed entry
  (`ResolveVersesRequest::toData:80-102`) so the downstream `LogicException`
  guards in `BibleVerseQueryBuilder::lookupReferences:113-118` and
  `ResolveVersesAction::expandRange:139-144` only fire on caller bugs,
  not user input.

### Code quality
- `final readonly` enforced on the new VO and DTO.
- Strict types declared throughout the new Domain code and migrations.
- No `else`, no magic strings, no leaked `Throwable` catches (W2 from
  prior pass).
- `chapterVerseCount` cache scoped to the action instance (W6) — confirmed
  on re-read; queue workers re-instantiate the action per job, so no
  cross-job leakage.

### API design
- Cross-chapter case keeps the existing flat verse-array shape and
  `meta.missing` semantics; chapter break is implicit via the per-row
  `chapter` field, matching AC §17.
- 422 envelope key (`errors.reference[]`) is consistent across both the
  parser path and the cap path (W3 / W4 from prior pass).
- Cap exception body uses a stable user-facing copy (`'The requested
  passage is too large.'`); the cap value is logged at info level only.

### Security
- Backfill writes to `security_events` are bounded (one row per defaulted
  language code); no PII.
- Form Request validates the `reference` string length (`max:200`) before
  parsing — prevents pathologically long inputs from reaching the parser.
- The 500-verse cap on cross-chapter ranges enforces the DoS floor; with
  fully-seeded `bible_chapters`, an attacker cannot pull a whole book.
  Plan §9 documents the unseeded-fallback caveat for MBA-031 QA.

### Performance
- `lookupVerseRange` precomputes the verse total via `bible_chapters`
  before issuing the SELECT; no unbounded scans.
- Backfill Actions chunk via `chunkById(500)`; no full-table loads.
- Reconcile migrations are idempotent and gated on `Schema::hasTable(...)`
  — fresh CI/dev DBs short-circuit, no overhead.

### Test coverage
- 11 reconcile feature tests (the deferred 7 are explicit "trivial
  rename-only" cases per the plan).
- Cross-chapter parser matrix exceeds the AC §21 minimum (12 valid + 6
  invalid required; 17 cases shipped).
- New audit-pass test (`ResolveCollectionReferencesActionTest::test_it_returns_parse_error_for_cross_chapter_ranges`)
  pins the silent-drop fix for A1.
- Full regression suite: **1080 / 1080**, no regressions from any prior
  story.

---

## Status

`done`.
