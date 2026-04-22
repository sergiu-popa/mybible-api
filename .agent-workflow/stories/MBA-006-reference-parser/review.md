# Review: MBA-006-reference-parser

## Summary

Port of the Symfony Bible reference library to `App\Domain\Reference\*` — parser,
formatter, linkifier, 66-book catalog, typed exception, RO/EN/HU language
formatters, and unit coverage. Second-pass review after commit
`76d24ed [MBA-006] Address review findings`. Both prior warnings (W1, W2) and
every suggestion (S1–S7) from the first pass are resolved. 116 Reference tests
pass; `make lint` and `make stan` are clean.

**Verdict:** APPROVE

---

## Warnings

- [x] **W1. EN `linkifyRegex()` uses `/m` while RO/HU use `/mi`** —
  `app/Domain/Reference/Formatter/Languages/EnglishFormatter.php:227`.
  Resolved: EN pattern now ends `/mi`, aligning free-text case-insensitivity
  with Romanian and Hungarian. The interface docblock
  (`LanguageFormatter.php:23-29`) was also updated to require the `i` flag
  for all implementations (closes S7 in the same edit).

- [x] **W2. EN short-form aliases are dead code in `linkify()`** —
  `app/Domain/Reference/Formatter/Languages/EnglishFormatter.php:227`.
  Resolved: the short-form aliases present in `NAME_TO_ABBREV` (`Gen`,
  `Exo`, `Deut`, `Josh`, `Judg`, `Rom`, `Matt`, `Rev`, `Ps`, etc.) are now
  appended to the regex alternation after the long forms. PCRE alternation
  order is safe here because the pattern mandates a trailing ` {1}`
  separator — a shorter prefix that matches but isn't followed by a space
  fails and PCRE falls through to the longer alternative (e.g. `Phil` vs
  `Philippians`, `Exo` vs `Exodus`, `Rom` vs `Romans`).

## Suggestions

- [x] **S1. `Reference` VO reuses `InvalidReferenceException::unparseable()`
  for VO invariants** — resolved. A dedicated
  `InvalidReferenceException::invalidVerses(string $book, int $chapter,
  string $reason)` factory now models VO-invariant failures
  (`app/Domain/Reference/Exceptions/InvalidReferenceException.php:39-48`),
  and the `Reference` constructor uses it at lines 24-36. Covered by
  `InvalidReferenceExceptionTest::test_invalid_verses_factory_sets_context`.

- [x] **S2. Plan deviation on `LinkBuilder::build()` signature, unnoted** —
  resolved. `plan.md:103` now documents that the implemented signature is
  `build(string $book, string $passage, string $language)` rather than
  `build(Reference $ref, string $language)` and explains why (composite
  passage expressions like `4:13;6:1-6` don't round-trip through a single
  `Reference`).

- [x] **S3. `ReferenceFormatter::forLanguage()` duplicates
  `resolveLanguage()`** — resolved. `resolveLanguage()` was removed and
  `forLanguage()` now contains the `match` directly
  (`app/Domain/Reference/Formatter/ReferenceFormatter.php:51-58`).

- [x] **S4. `ReferenceCreator` and `CanonicalLinkBuilder` each allocate
  their own `ReferenceFormatter`** — resolved.
  `ReferenceCreator::__construct` now seeds its default
  `CanonicalLinkBuilder` with the same `ReferenceFormatter` instance it
  stores for itself (`app/Domain/Reference/Creator/ReferenceCreator.php:15-21`),
  so a default-wired creator shares one formatter rather than allocating two.

- [x] **S5. Plan Task 15 lists a `parser.php` fixture that wasn't
  created** — resolved. `plan.md:104` now documents the decision: parser
  failure modes are structurally heterogeneous (distinct exception
  factories with different context shapes) and don't benefit from a flat
  `{input, expected}` table, so `ReferenceParserTest` keeps inline test
  methods. Human-readable, canonical, and linkify tests remain
  data-driven as planned.

- [x] **S6. `Reference::getVerse()` returns `0` for whole chapters** —
  resolved. Return type is now `?int` and whole-chapter references return
  `null` (`app/Domain/Reference/Reference.php:58-61`). Callers must guard
  with `isSingleVerse()` / `isRange()` or a null check — which is exactly
  the intent.

- [x] **S7. `LanguageFormatter::linkifyRegex()` docblock doesn't mention
  flags** — resolved. Interface docblock at
  `app/Domain/Reference/Formatter/Languages/LanguageFormatter.php:23-29`
  now states that implementations must use the `i` flag for
  case-insensitive matching across languages.

---

## Notes (no action required)

- Catalog counts verified: `BibleBookCatalog::BOOKS` has exactly 66 entries;
  round-trip tests for every abbreviation in RO/EN/HU pass (`assertCount(66,
  …)` in `BibleBookCatalogTest`, `test_round_trip_for_every_book` in each
  language formatter test).
- AC 14 fixture counts are met: 11 `toHumanReadable` fixtures per language
  (≥10), 8 `linkify` fixtures across RO/EN/HU (≥5).
- `linkify()` output-escaping surface looks safe by construction: the
  passage segment is regex-constrained to `[\d,:;-]`, the book name comes
  from a fixed alternation, and the abbreviation/version codes are
  internal. The surrounding free text is passed through unchanged, which
  matches the ported Symfony behaviour and is the caller's responsibility.
- `ReferenceCreator::linkify()` falls back to the original text if
  `preg_replace_callback` returns `null` (regex catastrophic backtracking
  on pathological input). This is defensive; the regex has no obvious
  catastrophic-backtrack shape.
- `RuntimeException` from `BibleBookCatalog::maxChapter()` is intentionally
  non-typed because all call sites pre-check with `hasBook()` — this is
  documented in the plan.
- **Known inherited limitation (not a regression of this story).** The
  linkify regex matches case-insensitively (`/mi`), but every language
  formatter's `NAME_TO_ABBREV` map is case-sensitive. A lowercase match
  (e.g. `"genesis 1:1"` in EN, `"geneza 1:1"` in RO) would enter the
  `preg_replace_callback`, fail the `abbreviation()` lookup, and throw
  `InvalidReferenceException::unknownBook` — surfacing through
  `linkify()`. This is the behaviour the Symfony library had for RO/HU
  and is now consistent for EN; hardening (lowercase lookup or a
  case-insensitive map) belongs in a follow-up if it turns out free-text
  callers pass lowercased content.

## Gate results

- `make test filter=Reference` → 116 passed, 478 assertions (0.63s).
- `make lint` → PASS (188 files).
- `make stan` → PASS (no errors, 169 files analysed).
