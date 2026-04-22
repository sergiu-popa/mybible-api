# Review: MBA-006-reference-parser

## Summary

Port of the Symfony Bible reference library to `App\Domain\Reference\*` — parser,
formatter, linkifier, 66-book catalog, typed exception, RO/EN/HU language
formatters, and unit coverage. Implementation matches the plan's layout and
semantics; all 285 tests pass; Pint and PHPStan are clean. Two cross-language
inconsistencies in the EN linkify pathway should be resolved before merge; the
remaining items are minor cleanups.

**Verdict:** REQUEST CHANGES

---

## Warnings

- [x] **W1. EN `linkifyRegex()` uses `/m` while RO/HU use `/mi`** —
  `app/Domain/Reference/Formatter/Languages/EnglishFormatter.php:227`. The
  Romanian pattern at
  `app/Domain/Reference/Formatter/Languages/RomanianFormatter.php:183` and
  Hungarian at
  `app/Domain/Reference/Formatter/Languages/HungarianFormatter.php:242` are
  case-insensitive; EN is not. Result: `"genesis 1:1"` (lowercase) is not
  linkified in English, but `"geneza 1:1"` is in Romanian. The story says the
  EN regex should "mirror the shape of RO/HU" (plan Risks & notes:
  `plan.md:99`). Fix: add the `i` flag to the EN regex (or make an explicit,
  documented decision that EN is case-sensitive).

- [x] **W2. EN short-form aliases are dead code in `linkify()`** —
  `app/Domain/Reference/Formatter/Languages/EnglishFormatter.php:14-132` lists
  many short forms (`Gen`, `Exo`, `Deut`, `Josh`, `Judg`, `Rom`, `1 Cor`,
  `Matt`, `Rev`, …), but the `linkifyRegex()` at line 227 includes only the
  long forms plus a handful of abbreviations. Consequence: `"Gen 1:1"` is
  *not* linkified even though `abbreviation('Gen')` returns `GEN`. Either add
  the short forms to the alternation (remembering PCRE is left-to-right so
  longer alternates must precede their prefixes — e.g. `1 Corinthians`
  before `1 Cor`, `Genesis` before `Gen`) or remove the short aliases from
  `NAME_TO_ABBREV` to keep the two maps consistent. Picking either side is
  fine; the two must agree.

## Suggestions

- **S1. `Reference` VO reuses `InvalidReferenceException::unparseable()` for
  VO invariants** —
  `app/Domain/Reference/Reference.php:24-35`. The constructor throws
  `unparseable($this->describe(), …)` when verses aren't positive/ascending —
  but there is no query string being parsed; this is an invariant on the VO.
  Consider either a dedicated factory
  (`InvalidReferenceException::invalidVerses(string $reason)`) or a plain
  `\InvalidArgumentException` so that "unparseable" stays semantically tied
  to parser input.

- **S2. Plan deviation on `LinkBuilder::build()` signature, unnoted** —
  `app/Domain/Reference/Creator/LinkBuilder.php:17`. Plan `plan.md:49` says
  `build(Reference $ref, string $language)`; implementation is
  `build(string $book, string $passage, string $language)`. The change is
  well-justified (composite passages like `4:13;6:1-6` don't fit a single
  `Reference`), but the deviation is not noted in `plan.md` or the commit
  message. Add a one-liner to the plan's "Risks & notes" section so
  downstream readers don't wonder.

- **S3. `ReferenceFormatter::forLanguage()` duplicates `resolveLanguage()`** —
  `app/Domain/Reference/Formatter/ReferenceFormatter.php:51-54, 101-108`. The
  public `forLanguage()` is a thin passthrough to the private
  `resolveLanguage()`. Collapse into a single public method and delete the
  private wrapper.

- **S4. `ReferenceCreator` and `CanonicalLinkBuilder` each allocate their
  own `ReferenceFormatter`** —
  `app/Domain/Reference/Creator/ReferenceCreator.php:12-13` plus
  `app/Domain/Reference/Creator/CanonicalLinkBuilder.php:12`. When using the
  default-wired constructors, the creator constructs one formatter and the
  canonical link builder it defaulted in constructs a second. Inject a
  shared `ReferenceFormatter` or document that both instances are cheap and
  intentional. Micro-issue, but it's the sort of thing that looks like a
  mistake in a code review six months from now.

- **S5. Plan Task 15 lists a `parser.php` fixture that wasn't created** —
  `plan.md:92`. `ReferenceParserTest` uses inline test methods instead of a
  data-provider-driven fixture file. Either add the fixture for consistency
  with the other tests, or update the plan to reflect the chosen approach.
  Not a blocker.

- **S6. `Reference::getVerse()` returns `0` for whole chapters** —
  `app/Domain/Reference/Reference.php:56-59`. Using `0` as a sentinel inside
  a typed VO is awkward: `?int` with explicit `null` communicates intent
  better and matches the `isSingleVerse()`-guarded usage pattern. Low
  priority; cosmetic.

- **S7. `LanguageFormatter::linkifyRegex()` docblock doesn't mention flags**
  — `app/Domain/Reference/Formatter/Languages/LanguageFormatter.php:23-29`.
  Callers currently infer case-sensitivity from the pattern body. One
  sentence in the interface docblock would surface the convention (see W1).

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
