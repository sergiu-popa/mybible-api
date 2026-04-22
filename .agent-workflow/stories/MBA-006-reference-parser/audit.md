# Audit: MBA-006-reference-parser

## Summary

Holistic audit of the Symfony → Laravel Bible reference library port
(`App\Domain\Reference\*`). The story ships with two prior passes: review
(APPROVE, W1/W2 + S1–S7 all resolved) and QA (PASSED, 286 tests).

This audit checked architecture compliance, code quality, security,
performance, and test coverage against `story.md`, `plan.md`, `review.md`,
and `qa.md`. One latent warning found and fixed; three suggestions
accounted for.

**Verdict:** PASS

---

## Issues

| #  | Issue | Location | Severity | Status | Resolution |
|----|-------|----------|----------|--------|------------|
| 1  | `ChapterRangeParser::expand()` cast chapter bounds via `(int)` without digit validation, so a malformed query like `GEN.1abc-3.VDC` silently normalised to a valid 3-chapter range. Inconsistent with `ReferenceParser::parseChapter()` / `parseVerses()` which both use `ctype_digit`; a data-integrity hole for untrusted input. | `app/Domain/Reference/Parser/ChapterRangeParser.php:37-43` | Warning | Fixed | Added a `ctype_digit` guard on both bounds before casting. Added two regression tests (`test_throws_when_bound_is_not_digits`, `test_throws_when_end_bound_is_not_digits`) in `ChapterRangeParserTest`. |
| 2  | `ChapterRangeParser::expand()` allocates `range($start, $end)` before any book-aware max-chapter validation — a caller passing `GEN.1-999999.VDC` eats ~30 MB before `parseOne` throws on chapter 51. | `app/Domain/Reference/Parser/ChapterRangeParser.php:48-52` | Suggestion | Deferred | No HTTP surface in this story exposes the parser to untrusted input; Symfony didn't bound this either. Follow-up: cap the spread (or pre-validate `end` against `BibleBookCatalog::maxChapter()`) when the HTTP layer lands and calls the parser from a request handler. |
| 3  | `ReferenceFormatter::toCanonical()` throws `InvalidReferenceException::unparseable()` when `$ref->version === null` — the factory name describes parse failures, but this is a rendering-side invariant. | `app/Domain/Reference/Formatter/ReferenceFormatter.php:18-23` | Suggestion | Skipped | Already covered by a `test_to_canonical_rejects_null_version`; the message is accurate ("cannot render canonical form without a version"). Adding a dedicated factory would add code for no observable user benefit — callers catch `InvalidReferenceException` regardless. |
| 4  | `BibleBookCatalog::maxChapter()` throws untyped `RuntimeException` on unknown book. | `app/Domain/Reference/Data/BibleBookCatalog.php:92-94` | Suggestion | Skipped | All call sites pre-check with `hasBook()`. Documented in `plan.md`; intentional. |
| 5  | `ReferenceCreator::linkify()` can bubble `InvalidReferenceException::unknownBook` when the case-insensitive `/mi` regex matches lowercase text (e.g. `"genesis 1:1"`) but `NAME_TO_ABBREV` is case-sensitive. | `app/Domain/Reference/Creator/ReferenceCreator.php:41`, all language formatters. | Suggestion | Deferred | Already documented in `review.md:106-115` as an inherited Symfony limitation. Harden (lowercase lookup or `array_change_key_case` pre-map) only if free-text callers turn out to send lowercased content — tripwire on the HTTP linkify endpoint. |

---

## Audit dimensions

### Architecture compliance — PASS
- Domain-only layout under `app/Domain/Reference/*` matches `plan.md`.
- No framework deps leak in: pure PHP, no Laravel helpers, no Eloquent,
  no container bindings. `LinkBuilder` contract isolates HTTP concerns
  (AC 13).
- No Doctrine / Symfony annotations carried across (technical note).
- Final classes, strict types, explicit return types, promoted readonly
  ctor properties throughout — Beyond CRUD posture honoured.

### API design — N/A
No HTTP surface in this story. `/run-auditor`'s API-design dimension does
not apply to a pure Domain library.

### Code quality — PASS
- Named-constructor exception factories with context accessors
  (`input()`, `reason()`).
- `Reference` constructor enforces sorted/unique/positive verses; empty
  verses denote a whole chapter — encoded in `isWholeChapter()`,
  `isSingleVerse()`, `isRange()`, `getVerse(): ?int` (S6 previously
  resolved).
- `ReferenceFormatter::collapseVerses()` cleanly round-trips
  `[1,2,3,5,7,8,9]` ↔ `"1-3,5,7-9"`.
- No magic strings: book codes centralised in `BibleBookCatalog`;
  default versions on `LanguageFormatter`.
- One lax validation path found and fixed (Issue 1).

### Security — PASS
- `ReferenceCreator::linkify()` output: the regex constrains book names
  to a fixed alternation and the passage to `[\d,:;-]+ *[\d,:-]+`.
  Neither class contains HTML-special chars, so the `href` and anchor
  text emitted by `sprintf('<a class="js-read" href="%s">%s</a>', …)`
  cannot carry an XSS payload from the matched text.
- Surrounding free text is passed through unchanged — that's the
  contract with the caller (same as Symfony).
- `CanonicalLinkBuilder::build()` concatenates raw `$book` / `$passage` /
  `defaultVersion()`. In the default linkify path those are all
  regex-constrained; if a future caller invokes the builder with
  user-supplied values, they must escape before output.
- No SQL, no filesystem, no external I/O; no secrets handling.

### Performance — PASS with one deferred item
- Parsing is linear in input length; regex has no obvious
  catastrophic-backtrack shape, and `preg_replace_callback`'s `null`
  return is defended against (falls back to original text).
- Deferred: bounded chapter-range expansion (Issue 2).
- `LanguageFormatter` instances are tiny (two constants); allocation
  cost per `forLanguage()` call is negligible.

### Test coverage — PASS
- 116 Reference tests cover every parser path, the formatter, all three
  language formatters, the catalog, the linkifier, and all four
  exception factories. Data providers drive 11 human-readable fixtures
  per language (AC 14: ≥10) and 8 linkify fixtures across RO/EN/HU
  (AC 14: ≥5).
- Round-trip tests over all 66 books per language formatter.
- Negative paths for every factory (`unparseable`, `unknownBook`,
  `chapterOutOfRange`, `invalidVerses`) covered.
- Added by this audit: `ChapterRangeParserTest` regression coverage for
  non-digit bounds.

---

## Gate results (post-fix)

- `make test` → **288 passed, 921 assertions** (2.52s). +2 from baseline
  (the new non-digit-bound regressions).
- `make test filter=Reference` → all Reference tests green locally.
- `make lint-fix` → PASS (188 files).
- `make stan` → PASS (no errors, 169 files analysed).

---

## Verdict: PASS → status `done`

All Critical resolved (none found). One Warning fixed. All Suggestions
accounted for (Fixed / Skipped-with-reason / Deferred-with-pointer).
