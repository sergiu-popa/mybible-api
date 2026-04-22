## QA Report — MBA-006-reference-parser

**Verdict:** QA PASSED
**Status:** `qa-passed`

## Gate results

- `make test` → **286 passed, 919 assertions** (2.81s).
- `make test filter=Reference` → **116 passed, 478 assertions** (0.65s).
- `make lint` / `make stan` → clean (per review.md, re-verified nothing regressed under full `make test`).

## Acceptance-criteria coverage

| AC | Evidence |
|---|---|
| 1. `Reference` VO (book/chapter/verses/version + invariants) | `tests/Unit/Domain/Reference/ReferenceTest.php` — constructor, `isWholeChapter`, `isSingleVerse`, `isRange`, `getVerse` (incl. `null` for whole-chapter, per S6). |
| 2. Parser accepts canonical `BOOK.CHAPTER:VERSES.VERSION` | `ReferenceParserTest::test_parses_…_canonical`, whole-chapter, single-verse, ranges, comma list, mixed. |
| 3. Chapter ranges (`GEN.1-3.VDC`) | `ChapterRangeParserTest` (expand) + `ReferenceParserTest` dispatcher branch. |
| 4. Verse ranges / commas / mixed | `ReferenceParserTest` incl. overlap-dedup fixture (`1-3,5,2-7` → `1-7`). |
| 5. Typed `InvalidReferenceException` on bad input | `InvalidReferenceExceptionTest` (three named constructors + `invalidVerses`) and exception assertions in every parser test. |
| 6. Unknown book → exception | `ReferenceParserTest::test_rejects_unknown_book`, `BibleBookCatalogTest`. |
| 7. Chapter out of range → exception | `ReferenceParserTest::test_rejects_out_of_range_chapter` + `BibleBookCatalogTest::test_max_chapter_…`. |
| 8. No verse-max validation | Absence confirmed by `ReferenceParserTest` accepting arbitrary verse numbers (matches Symfony). |
| 9. `toCanonical()` | `ReferenceFormatterTest` driven by `fixtures/canonical.php`. |
| 10. `toHumanReadable()` RO/EN/HU | `RomanianFormatterTest`, `EnglishFormatterTest`, `HungarianFormatterTest` + `fixtures/human-readable.{ro,en,hu}.php` (11 fixtures each). |
| 11. RO/EN/HU supported; others fall back to EN | `ReferenceFormatterTest::test_falls_back_to_english_for_unknown_language`. |
| 12. `linkify()` wraps references in `<a class="js-read">` | `ReferenceCreatorTest` driven by `fixtures/linkify.{ro,en,hu}.php` (8 fixtures across languages). |
| 13. `LinkBuilder` contract with default implementation | `CanonicalLinkBuilderTest` — RO/HU/EN default-version output. |
| 14. ≥10 human-readable fixtures/lang; ≥5 linkify fixtures | Actual: 11 / 11 / 11 (RO/EN/HU); 8 linkify (≥5). |
| 15. Fixtures under `tests/Unit/Domain/Reference/fixtures/` | Present. `parser.php` intentionally omitted — documented in `plan.md:104`. |

## Edge-case probes

- **Overlap normalisation.** `GEN.1:1-3,5,2-7.VDC` round-trips to `GEN.1:1-7.VDC` (fixture in `canonical.php`); verses array is pre-expanded, sorted, unique — matches Symfony.
- **Whole-chapter `getVerse()`.** Returns `null`, not `0` (S6 fix verified in `ReferenceTest`).
- **Unsupported language fallback.** `ReferenceFormatter::toHumanReadable(ref, 'fr')` resolves through `match` default arm to `EnglishFormatter`.
- **Catalog completeness.** `BibleBookCatalogTest::test_contains_66_books` + round-trip tests for every abbreviation in each language formatter test.
- **EN `linkifyRegex` case-insensitivity.** `/mi` flag confirmed at `EnglishFormatter:227`; aligned with RO/HU (W1 resolved).
- **EN short-form aliases reachable in `linkify()`.** Regex alternation now includes short forms after long forms; W2 verified resolved via interface docblock + fixture coverage.
- **PCRE safety net.** `ReferenceCreator::linkify()` falls back to original text if `preg_replace_callback` returns `null`; defensive (no obvious catastrophic-backtrack shape).

## Review items left unresolved

None — W1, W2 and S1–S7 are all checked off in `review.md` (verdict APPROVE). No Critical items outstanding.

## Regression check

Full suite (`make test`) runs green (286 passed). The port is Domain-only (no routes, middleware, controllers, migrations) so no HTTP/auth/DB regressions are plausible — but the full suite confirms no autoloader/exception-renderer side-effects from the new classes.

## Known inherited limitations (not blocking)

- `linkify()` regex is case-insensitive but `NAME_TO_ABBREV` maps are case-sensitive: a lowercase match (`"genesis 1:1"`) enters the callback and throws `InvalidReferenceException::unknownBook`. Matches Symfony behaviour (`review.md` notes). Not a regression of this story.
- `BibleBookCatalog::maxChapter()` throws `RuntimeException` (non-typed) on unknown book; all call sites pre-check with `hasBook()`. Documented in `plan.md`.
