# Story: MBA-006-reference-parser

## Title
Reference parser — port the Symfony Bible reference library to a Domain service

## Status
`done`

## Description
The Symfony app has a reference parsing library that turns human-readable
Bible references like `"John 3:16"`, `"Rom 1:1-5,8,10"`, or `"GEN.1:1-3,5.VDC"`
into structured `{book, chapter, verses, version}` objects, and back again
into display strings (with per-language regex for Romanian and Hungarian).
Collections (MBA-014), Favorites (MBA-010), and Notes (MBA-011) all depend on
it. It ships first so later stories can consume it without stubbing.

Source files to port:
- `src/Domain/Entity/Reference.php` (278 LOC — primary parser)
- `src/Domain/Entity/Parser/Parser.php` (interface)
- `src/Domain/Entity/Parser/ChapterRange.php` (chapter-range handling)
- `src/Domain/Entity/Parser/Multiple.php` (multi-reference handling)
- `src/Domain/Service/ReferenceTransformer.php` (66 LOC)
- `src/Domain/Service/ReferenceCreator.php` (352 LOC — multi-language regex
  and HTML link generation)

Target in Laravel: `App\Domain\Reference\*`. Pure PHP, no framework deps
beyond what `App\Domain\*` already uses (Beyond CRUD conventions). No HTTP
layer in this story.

The Symfony library has no tests. Build a thorough PHPUnit suite — this is
business-critical logic that future domains will rely on, and it is the only
component that ships with NO coverage to fall back on.

## Acceptance Criteria

### Parser
1. `App\Domain\Reference\Reference` value object holds `book`, `chapter`,
   `verses` (array of individual verse numbers, post-expansion of ranges),
   `version` (nullable string).
2. `App\Domain\Reference\Parser\ReferenceParser` parses the Symfony canonical
   form `BOOK.CHAPTER:VERSES.VERSION` (e.g. `GEN.1:1-3,5.VDC`) and returns a
   `Reference` (or array of `Reference` for chapter ranges / multiple refs).
3. Supports chapter ranges (`GEN.1-3.VDC`).
4. Supports verse ranges (`GEN.1:1-5`), comma-separated verses (`GEN.1:1,3,5`),
   and mixed forms (`GEN.1:1-3,5,7-9`).
5. Returns a typed exception (`InvalidReferenceException`) on unparseable
   input — never returns `null`/`false`. All call sites can `try/catch`
   deterministically.

### Book / chapter validation
6. Validates book abbreviations against the 66-book Bible canon map ported
   from Symfony (`BOOKS` constant in `Reference.php`). Unknown abbreviation
   → `InvalidReferenceException`.
7. Validates chapter numbers against the max-chapter-per-book map. Out of
   range → `InvalidReferenceException`.
8. Does NOT validate verse numbers against a max-verse map (Symfony does
   not either — preserve behavior).

### Reverse parser / formatter
9. `ReferenceFormatter::toCanonical(Reference $ref): string` produces the
   `BOOK.CHAPTER:VERSES.VERSION` form.
10. `ReferenceFormatter::toHumanReadable(Reference $ref, string $language):
    string` produces per-language output (e.g. `"Geneza 1:1-3"` for `ro`,
    `"Genesis 1:1-3"` for `en`, `"1 Mózes 1:1-3"` for `hu`).
11. Supported languages at minimum: `ro`, `en`, `hu`. Additional languages
    degrade to `en`.

### ReferenceCreator (HTML link generator)
12. `App\Domain\Reference\ReferenceCreator::linkify(string $text, string
    $language): string` finds Bible references in free text and wraps them
    in anchor tags (same regex set Symfony ships per language).
13. The link target is produced by an injectable `LinkBuilder` contract so
    the Domain layer does not know about HTTP routes. Default implementation
    in the HTTP layer (future story) wires in the route.

### Tests
14. Unit tests cover: the happy path for every supported format; every
    error mode from the validation list; at least 10 table-driven fixtures
    per supported language for `toHumanReadable`; at least 5 fixtures for
    `linkify` across RO/EN/HU.
15. Fixtures live under `tests/Unit/Domain/Reference/fixtures/`. Each
    fixture is `{input, expected}` so adding edge cases does not require
    new test methods.

## Scope

### In Scope
- Port all six Symfony files to `App\Domain\Reference\`.
- Full unit test coverage for parsing, formatting, and linkification.
- `InvalidReferenceException` with structured context (input string,
  failure reason).

### Out of Scope
- HTTP endpoints. This story is library-only.
- Database persistence of parsed references (MBA-010, MBA-011, MBA-014
  consume `Reference` objects and persist on their own).
- Performance tuning beyond what the Symfony library already does.
  Benchmark only if a linkification hot path is obviously slow.

## Technical Notes

### Directory shape
```
app/Domain/Reference/
├── Reference.php                        # readonly value object
├── Exceptions/
│   └── InvalidReferenceException.php
├── Parser/
│   ├── ReferenceParser.php              # primary entry point
│   ├── ChapterRangeParser.php
│   └── MultipleReferenceParser.php
├── Formatter/
│   ├── ReferenceFormatter.php
│   └── Languages/
│       ├── RomanianFormatter.php
│       ├── EnglishFormatter.php
│       └── HungarianFormatter.php
├── Creator/
│   ├── ReferenceCreator.php
│   └── LinkBuilder.php                  # interface
└── Data/
    └── BibleBookCatalog.php             # the 66-book map + max chapters
```

### Language formatter registry
A simple array keyed by ISO-639-1 in `BibleBookCatalog` maps language →
formatter class. Architect to decide if a strategy pattern is overkill for
three languages — a match expression might be clearer.

### Don't import Doctrine / Symfony annotations
The Symfony files have Doctrine entity annotations on `Reference.php`. These
are vestigial — the parser is behaviorally a value object. Strip them on the
port.

## Dependencies
- None. This is a pure-PHP library.

## Open Questions for Architect
1. **Canon data source.** Should the 66-book map live in a constant
   (`BibleBookCatalog`) or in config (`config/bible.php`)? Constant is
   simpler and the data is immutable; config would let us override per-env
   (no apparent use case).
2. **Linkify target URL pattern.** The Symfony code hard-codes the URL
   pattern. We want the Domain decoupled from routes — is introducing
   `LinkBuilder` worth it, or does the caller just pass a closure?
3. **Additional languages.** Are there any languages beyond RO/EN/HU we
   expect to support within 6 months? If yes, design the language registry
   as extensible now; if no, ship the three and extract only if a fourth
   appears (tripwire entry).
4. **Multi-reference return type.** `Multiple.php` returns an array of
   `Reference`. Do we want a `ReferenceCollection` value object (richer,
   more boilerplate) or stick with `array<Reference>` (flatter)?
