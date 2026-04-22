# Plan: MBA-006-reference-parser

## Approach

Port the Symfony reference library into `App\Domain\Reference\*` as a pure-PHP Domain service. The Symfony design mashes parsing, validation, and state into a single `Reference` entity — we split that into a **readonly value object** (`Reference`) plus stateless helpers (`ReferenceParser`, `ReferenceFormatter`, `ReferenceCreator`). All unknown-book / out-of-range-chapter cases raise a typed `InvalidReferenceException` (Symfony silently returned `isValid() === false`; we harden this per AC 5–7). Per-language behaviour is encapsulated in a small `LanguageFormatter` interface with three concrete implementations selected via a `match` expression — no registry, no strategy factory (open question #3).

## Open questions — resolutions

1. **Canon data source.** Constant on `BibleBookCatalog`. Data is immutable; no env override need is apparent.
2. **Linkify URL pattern.** Introduce the `LinkBuilder` contract (AC 13 locks this). Ship a Domain-local `CanonicalLinkBuilder` default that preserves the Symfony href format so linkify is usable before the HTTP story lands.
3. **Additional languages.** Ship RO/EN/HU via `match`. Promote to a registry only when a fourth language appears (tripwire).
4. **Multi-reference return type.** `array<Reference>` — flatter, matches Symfony. A `ReferenceCollection` VO is deferred until MBA-010/014 demonstrates it pays for itself.

## Domain layout

```
app/Domain/Reference/
├── Reference.php                          # readonly VO
├── Data/BibleBookCatalog.php              # 66-book canon + max-chapter lookup
├── Exceptions/InvalidReferenceException.php
├── Parser/
│   ├── ReferenceParser.php                # parse() dispatcher + parseOne()
│   ├── ChapterRangeParser.php             # GEN.1-3.VDC → sub-queries
│   └── MultipleReferenceParser.php        # GEN.1:1;2;3:5-7.VDC → sub-queries
├── Formatter/
│   ├── ReferenceFormatter.php             # toCanonical / toHumanReadable
│   └── Languages/
│       ├── LanguageFormatter.php          # interface
│       ├── RomanianFormatter.php
│       ├── EnglishFormatter.php
│       └── HungarianFormatter.php
└── Creator/
    ├── LinkBuilder.php                    # interface
    ├── CanonicalLinkBuilder.php           # default (Symfony-compatible)
    └── ReferenceCreator.php               # linkify
```

## Key types

| Type | Role |
|---|---|
| `Reference` (readonly) | `string $book`, `int $chapter`, `array<int> $verses` (pre-expanded, sorted, unique), `?string $version`. Helpers: `isWholeChapter()`, `isSingleVerse()`, `isRange()`, `getVerse()`. Constructor validates shape (verses ascending, ints ≥ 1); parsers are the only producers. |
| `BibleBookCatalog` | `public const BOOKS`; `hasBook(string): bool`; `maxChapter(string): int` (throws on unknown — internal usage only). |
| `InvalidReferenceException` (extends `\RuntimeException`) | Named constructors: `unparseable(string $input, string $reason)`, `unknownBook(string $input, string $book)`, `chapterOutOfRange(string $input, string $book, int $chapter, int $max)`. Accessors: `input()`, `reason()`. |
| `ReferenceParser` | `parseOne(string $query): Reference` (canonical form, single ref only — throws on `;` or chapter-only range). `parse(string $query): array<Reference>` (dispatcher — normalizes single ref to 1-element array). |
| `ChapterRangeParser` / `MultipleReferenceParser` | Each exposes `expand(string $query): array<string>` returning canonical single-ref sub-queries. |
| `ReferenceFormatter` | `toCanonical(Reference): string` — rebuilds `BOOK.C:V.VER` (collapses `[1,2,3,5]` → `1-3,5`). `toHumanReadable(Reference, string $language): string` — dispatches to the language formatter via `match`, degrades unsupported langs to `EnglishFormatter`. |
| `LanguageFormatter` (interface) | `bookName(string $abbrev): string`; `abbreviation(string $localized): string` (throws `InvalidReferenceException::unknownBook` for unknown); `linkifyRegex(): string`; `defaultVersion(): string`. |
| `LinkBuilder` (interface) | `build(Reference $ref, string $language): string` — Domain-layer contract; no HTTP knowledge. |
| `CanonicalLinkBuilder implements LinkBuilder` | Produces `"{BOOK}.{passage}.{language.defaultVersion}"` — the Symfony-compatible default. |
| `ReferenceCreator` | Constructor takes `LinkBuilder`. `linkify(string $text, string $language): string` — regex-scans text, wraps each match in `<a class="js-read" href="{builder.build(...)}">{original}</a>`. |

## Parsing semantics — preserve Symfony behaviour

- `GEN.1.VDC` → whole chapter (verses `[]`).
- `GEN.1:1.VDC` → single verse `[1]`.
- `GEN.1:1-3.VDC` → `[1,2,3]`.
- `GEN.1:1-3,5,7-9.VDC` → `[1,2,3,5,7,8,9]`.
- `GEN.1:1-3,5,2-7.VDC` → `[1,2,3,4,5,6,7]` (overlaps normalised — Symfony did this via `array_unique`+`sort`).
- `GEN.1-3.VDC` (chapter range) → 3 `Reference`s (whole-chapter each).
- `GEN.1:1;2;3:5-7.VDC` (multiple) → 3 `Reference`s.

Failure modes (all raise `InvalidReferenceException`):

- Not exactly 3 dot-separated parts → `unparseable`.
- Book abbreviation not in `BibleBookCatalog::BOOKS` → `unknownBook`.
- Chapter ≤ 0 or `> maxChapter` → `chapterOutOfRange`.
- Verses are not validated against any max-verse map (story AC 8 — preserve Symfony).

## Linkify semantics

- RO and HU regex + abbreviation maps ported verbatim from `src/Service/ReferenceCreator.php` (242 LOC — story's "352 LOC" was stale; see Risks).
- **EN is new** (Symfony never supported English linkify). Author an EN book-name regex and abbreviation map mirroring the RO/HU shape. Cover the 66 canon names + common short forms matching the EN formatter's `bookName()` output.
- Output HTML: `<a class="js-read" href="…">{original match}</a>`. Class name and tag preserved for client-side compatibility.

## Tasks

- [x] 1. Create `App\Domain\Reference\Data\BibleBookCatalog` with the 66-entry `BOOKS` constant (abbrev → max chapter) + `hasBook()` / `maxChapter()`. Unit test: each entry is non-empty; totals 66; `hasBook` returns bool; `maxChapter` throws on unknown.
- [x] 2. Create `App\Domain\Reference\Exceptions\InvalidReferenceException` with the three named constructors (`unparseable`, `unknownBook`, `chapterOutOfRange`) and `input()` / `reason()` accessors. Unit test: each factory sets the expected message and exposes context.
- [x] 3. Create readonly `App\Domain\Reference\Reference` VO (fields + helpers listed above). Unit test: `isWholeChapter` / `isSingleVerse` / `isRange` / `getVerse` each branch; constructor rejects non-ascending or non-positive verses.
- [x] 4. Create `App\Domain\Reference\Parser\ChapterRangeParser::expand()`. Unit test: `GEN.1-3.VDC` → three canonical sub-queries; invalid shape throws `InvalidReferenceException::unparseable`.
- [x] 5. Create `App\Domain\Reference\Parser\MultipleReferenceParser::expand()`. Unit test: `GEN.1:1;2;3:5-7.VDC` → three canonical sub-queries; mixed partials + whole-chapter expanded correctly.
- [x] 6. Create `App\Domain\Reference\Parser\ReferenceParser::parseOne()` (canonical single-ref). Unit test: whole chapter, single verse, verse range, comma list, mixed, overlap-dedup; unknown book; out-of-range chapter; missing/extra dot segments; `;` in input → rejects (not a single ref).
- [x] 7. Add `ReferenceParser::parse()` dispatcher — detects chapter-range vs. multiple vs. single; delegates to `ChapterRangeParser` / `MultipleReferenceParser` / `parseOne()`; always returns `array<Reference>`. Unit test: each dispatch branch + an `InvalidReferenceException` bubble-up case.
- [x] 8. Create `App\Domain\Reference\Formatter\ReferenceFormatter::toCanonical()` with a private `collapseVerses(array<int>): string` helper. Unit test: whole chapter, single verse, range, mixed, single-verse-per-gap (`[1,3,5]` → `1,3,5`), null version rejected.
- [x] 9. Create `LanguageFormatter` interface + `RomanianFormatter` (port RO abbrev map + regex from Symfony; `defaultVersion = 'VDC'`). Unit test: abbrev round-trip (`Geneza` ↔ `GEN`) for all 66 RO entries; unknown name throws.
- [x] 10. Create `EnglishFormatter` (author EN abbrev map + regex; `defaultVersion = 'KJV'` — confirm during implementation or pick the current Bible catalog default). Unit test: abbrev round-trip for all 66 EN entries; unknown throws.
- [x] 11. Create `HungarianFormatter` (port HU abbrev map + regex; `defaultVersion = 'KAR'`). Unit test: abbrev round-trip for every HU alias (both short `1Móz` and long `1Mózes` map to `GEN`); unknown throws.
- [x] 12. Add `ReferenceFormatter::toHumanReadable()` — `match` selects formatter; unsupported language falls back to English. Unit test: at least 10 fixtures per RO/EN/HU from `fixtures/human-readable.{ro,en,hu}.php`; fallback to EN for an arbitrary code (`fr`).
- [x] 13. Create `App\Domain\Reference\Creator\LinkBuilder` interface + `CanonicalLinkBuilder` default (uses the language formatter's `defaultVersion()`). Unit test: `CanonicalLinkBuilder` emits `GEN.17:2.VDC` / `1CO.1:1.KAR` / `GEN.17:2.KJV` for RO/HU/EN respectively.
- [x] 14. Create `ReferenceCreator::linkify()` driven by the active language formatter's regex + `LinkBuilder`. Unit test: at least 5 fixtures across RO/EN/HU from `fixtures/linkify.*.php`; port the four Symfony `ReferenceCreatorTest` cases verbatim to guarantee byte-identical RO/HU output.
- [x] 15. Create `tests/Unit/Domain/Reference/fixtures/` with one file per fixture set (`parser.php`, `canonical.php`, `human-readable.ro.php`, `human-readable.en.php`, `human-readable.hu.php`, `linkify.ro.php`, `linkify.en.php`, `linkify.hu.php`). Each returns `array<array{input:…, expected:…}>`; tests use a single `@dataProvider` per file.
- [x] 16. Run `make lint-fix`, `make stan`, then `make test --filter=Reference`; finally run the full suite (`make test`) before marking the story ready for review.

## Risks & notes

- **Source line-count mismatch.** Story lists `ReferenceCreator.php` as 352 LOC. Actual file (`src/Service/ReferenceCreator.php`, not `Domain/Service/`) is 242 LOC. No behavioral surprise — just correct the path when porting.
- **Overlap normalisation.** `toCanonical(parse('GEN.1:1-3,5,2-7.VDC'))` returns `GEN.1:1-7.VDC`, not the input verbatim. Acceptable (matches the verses array). Document with a fixture so the normalisation is intentional rather than a bug.
- **EN regex is new.** Symfony never supported EN linkify. The EN regex and abbreviation map are authored fresh; they should mirror the shape of RO/HU but will not have a Symfony golden-fixture to compare against. Hand-curate at least 5 fixtures.
- **`CanonicalLinkBuilder` as Domain default.** The story says the "default implementation" lives in a future HTTP story. Shipping `CanonicalLinkBuilder` in the Domain gives `ReferenceCreator` a working default and lets linkify be tested without a stub. The HTTP layer's builder replaces it by DI binding — no Domain change needed.
- **Drop Doctrine annotations** on the Symfony `Reference` source during port (story technical note).
- **No HTTP layer.** Pure Domain library — no controller, Form Request, Resource, route, or feature test. Unit tests fully cover the scope.
