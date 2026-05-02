# Plan: MBA-023-schema-reconcile-foundation

## Approach

Land the cutover-ready DDL in three stripes that share one timestamp slice.
**Stripe A** — one reconcile migration per Symfony domain, each gated on
`Schema::hasTable($legacy)` so fresh CI/dev passes through as a no-op (precedent:
`reconcile_symfony_user_table`, `reconcile_symfony_resource_tables`). **Stripe
B** — identifier standardisation: language widths to `CHAR(2)` and `notes.book`
widening to `VARCHAR(8)`, sandwiched around two backfill Actions that consult
`_legacy_book_abbreviation_map` and a hard-coded 3→2 char language map, logging
unknown language codes to `security_events` and failing loudly on unmapped book
names. **Stripe C** — extend `ReferenceParser` to accept `BOOK.CH:V-CH:V[.VER]`,
emitting a new `VerseRange` value object that the verses query builder resolves
via a single tuple-comparison query. Existing `Reference` semantics are
unchanged; non-verses callers reject `VerseRange` at the validation rule.

## Open questions — resolutions

1. **`bible_verses` UNIQUE deferral.** AC §6 requires the UNIQUE
   `(bible_version_id, bible_book_id, chapter, verse)` re-asserted, but those
   columns are introduced **nullable** here and only backfilled by MBA-031.
   Adding the index now would index NULL pairs and provide no real protection.
   The UNIQUE moves to MBA-031 alongside the FK backfill (matches the deferral
   precedent §10/§7 already in the AC). Plan documents this; AC §6 needs a
   one-line edit by Architect to allow it.
2. **`?reference=` vs `?references=` query param.** Story AC §17 uses the
   plural form, but the existing endpoint and AC §18 ("existing callers
   unaffected") both rely on the singular `?reference=`. Stay on the singular
   `?reference=` — the plural in §17 is descriptive prose, not a contract
   change. Cross-chapter input flows through the same parameter. No additional
   alias.
3. **Where does the cross-chapter range live in `parse()`'s return type?**
   Change `parse()` to return `array<Reference|VerseRange>`. Wider blast
   radius than a separate method, but the `ParseableReference` rule already
   gates non-verses callers — adding one type-guard line in the rule keeps
   them correct. Worth it: a separate method bifurcates the parser surface
   and would force the verses controller to know which method to call before
   parsing.
4. **`_legacy_book_map` shape.** Created inside the bible-domain reconcile
   migration with columns `legacy_book_id BIGINT UNSIGNED PK,
   legacy_bible_id BIGINT UNSIGNED, bible_book_id BIGINT UNSIGNED,
   bible_version_id BIGINT UNSIGNED`. Indexed `(legacy_bible_id,
   legacy_book_id)` so MBA-031 ETL on `bible_verses` can join cheaply. Dropped
   in MBA-032.
5. **`_legacy_book_abbreviation_map` seed source.** Romanian + English
   long-form names hard-coded in the migration that creates the table (66
   books × 2 languages = 132 rows). Single source of truth referenced by the
   book-backfill Action; if the Action encounters a value not in the map, it
   throws `UnmappedLegacyBookException` and the migration aborts.
6. **Whether to extract backfill to Actions or keep inline.** Extract.
   Migration calls `app(BackfillLegacyLanguageCodesAction::class)->handle($table, $column)`.
   Reasons: pure-PHP unit testability of the mapping/log logic, and three
   callsites (language standardisation migration walks four columns, plus
   the backfill-test feature test exercises the Action directly).
7. **Cross-chapter resolver ordering.** Result must be ordered by
   `(chapter, verse)`. Implemented as `orderBy('chapter')->orderBy('verse')`
   inside the new `BibleVerseQueryBuilder::lookupVerseRange()`. The existing
   `lookupReferences()` keeps its own ordering (per-group append order).
8. **Cap on cross-chapter range size (500 verses).** Enforced in the
   `BibleVerseQueryBuilder::lookupVerseRange()` *before* the SQL: sum of
   `bible_chapters.verse_count` between startChapter and endChapter. If the
   sum exceeds 500, throw `VerseRangeTooLargeException` (new). The Form
   Request catches it and returns 422 with `errors.references[]`. This
   avoids a SELECT that returns 30 000 rows for `JHN.1:1-21:25`.

## Domain layout

```
app/Domain/Reference/
├── VerseRange.php                                    # NEW — readonly VO {book, startChapter, startVerse, endChapter, endVerse, ?version}
├── Exceptions/InvalidReferenceException.php          # MOD — add factory crossChapterMalformed()
└── Parser/ReferenceParser.php                        # MOD — accept BOOK.CH:V-CH:V[.VER]; parse() return type widens to array<Reference|VerseRange>

app/Domain/Bible/
├── QueryBuilders/BibleVerseQueryBuilder.php          # MOD — accept array<Reference|VerseRange>; new lookupVerseRange() helper using tuple comparison
└── Exceptions/VerseRangeTooLargeException.php        # NEW — thrown when sum of verse counts > 500

app/Domain/Verses/
└── Actions/ResolveVersesAction.php                   # MOD — expand VerseRange entries when computing missing tuples (uses bible_chapters.verse_count)

app/Domain/Favorites/Rules/ParseableReference.php     # MOD — fail when parsed result is a VerseRange (cross-chapter not supported here)

app/Domain/Migration/                                 # NEW namespace for one-shot ETL helpers consumed by reconcile migrations
├── Actions/BackfillLegacyLanguageCodesAction.php     # NEW — walks (table, column); rewrites ron→ro etc.; defaults unknown to ro and logs to security_events
├── Actions/BackfillLegacyBookAbbreviationsAction.php # NEW — walks (table, column); rewrites long-form names to USFM-3 via _legacy_book_abbreviation_map; throws on unmapped
├── Exceptions/UnmappedLegacyBookException.php       # NEW — bubbles up to abort the migration
└── Support/LegacyLanguageCodeMap.php                 # NEW — const map ron→ro, eng→en, hun→hu, spa→es, fra→fr, deu→de, ita→it (+ identity for already-2-char)

database/migrations/                                  # NEW (timestamps grouped at 2026_05_03_*; engineer picks the slice)
├── 2026_05_03_000100_create_legacy_book_abbreviation_map_table.php
├── 2026_05_03_000200_reconcile_symfony_bible_tables.php           # bible→bible_versions; book→bible_books (dedupe + _legacy_book_map); verse→bible_verses + nullable FK columns
├── 2026_05_03_000201_reconcile_symfony_collection_tables.php      # collection/collection_topic/collection_reference → plurals
├── 2026_05_03_000202_reconcile_symfony_commentary_tables.php      # commentary→commentaries; commentary_text→commentary_texts
├── 2026_05_03_000203_reconcile_symfony_devotional_tables.php      # devotional_type→devotional_types; devotional_entry→devotionals (UNIQUE deferred to MBA-027)
├── 2026_05_03_000204_reconcile_symfony_hymnal_tables.php          # hymnal_book/song/verse → plurals; UNIQUE (hymnal_book_id, number) on hymnal_songs
├── 2026_05_03_000205_reconcile_symfony_mobile_tables.php          # mobile_version → mobile_versions
├── 2026_05_03_000206_reconcile_symfony_reading_plan_tables.php    # plan→reading_plans; plan_day→reading_plan_days; plan_enrollment→reading_plan_subscriptions (author_id→user_id); plan_progress→reading_plan_subscription_days_legacy
├── 2026_05_03_000207_reconcile_symfony_olympiad_tables.php        # question→olympiad_questions; question_option→olympiad_answers (correct→is_correct)
├── 2026_05_03_000208_reconcile_symfony_resource_book_tables.php   # resource_book/chapter/download → plurals
├── 2026_05_03_000209_reconcile_symfony_sabbath_school_tables.php  # sb_trimester/lesson/section/content/answer/favorite/highlight → expanded names (UNIQUE deferred to MBA-025)
├── 2026_05_03_000210_reconcile_symfony_note_and_favorite_tables.php # note/favorite_category/favorite/devotional_favorite/hymnal_favorite → plurals; UNIQUE (user_id, category_id, reference) on favorites
├── 2026_05_03_000300_drop_reading_progress_table.php              # gated on emptiness; reversible by recreating the original shape
├── 2026_05_03_000301_drop_doctrine_artefacts.php                  # drop doctrine_migration_versions; idempotent guards on users.salt/reset_token/reset_date (already dropped by user reconcile)
├── 2026_05_03_000400_backfill_legacy_language_codes.php           # invokes BackfillLegacyLanguageCodesAction over the four affected columns
├── 2026_05_03_000401_standardise_language_column_widths.php       # ALTER bible_versions/resource_categories/olympiad_questions/users.language → CHAR(2); runs after backfill
├── 2026_05_03_000402_backfill_legacy_book_abbreviations.php       # invokes BackfillLegacyBookAbbreviationsAction over olympiad_questions.book and any other column carrying long-form names
└── 2026_05_03_000403_widen_notes_book_column.php                  # notes.book VARCHAR(3) → VARCHAR(8)

tests/Unit/Domain/Reference/Parser/ReferenceParserTest.php         # MOD — add cross-chapter matrix (≥12 valid + ≥6 invalid)
tests/Unit/Domain/Migration/Actions/                               # NEW — Backfill action unit tests (mapping, default, fail-loud)
└── BackfillLegacyLanguageCodesActionTest.php
└── BackfillLegacyBookAbbreviationsActionTest.php
tests/Feature/Database/Reconcile/                                  # NEW dir — one feature test class per reconcile migration
├── BibleReconcileTest.php
├── CollectionReconcileTest.php
├── CommentaryReconcileTest.php
├── DevotionalReconcileTest.php
├── HymnalReconcileTest.php                                        # also covers hymnal_songs UNIQUE regression
├── MobileReconcileTest.php
├── ReadingPlanReconcileTest.php
├── OlympiadReconcileTest.php                                      # also covers correct→is_correct
├── ResourceBookReconcileTest.php
├── SabbathSchoolReconcileTest.php
├── NoteAndFavoriteReconcileTest.php                               # also covers favorites UNIQUE regression
├── DoctrineCleanupTest.php
└── IdentifierBackfillTest.php                                     # users.language='ron'→'ro', notes.book='Genesis'→'GEN'
tests/Feature/Api/Verses/ResolveCrossChapterVersesTest.php         # NEW — feature test for GET /api/v1/verses?reference=MAT.19:27-20:16.VDC
```

## Key types

| Type | Role |
|---|---|
| `App\Domain\Reference\VerseRange` | Readonly VO carrying `(book, startChapter, startVerse, endChapter, endVerse, ?version)`. Constructor enforces `(endChapter, endVerse) > (startChapter, startVerse)` and `startVerse, endVerse >= 1`. |
| `App\Domain\Bible\Exceptions\VerseRangeTooLargeException` | Thrown by `lookupVerseRange()` when the range expands to >500 verses; caught in `ResolveVersesRequest` to surface a 422 with `errors.references[]`. |
| `App\Domain\Migration\Support\LegacyLanguageCodeMap` | Static class with `to2Char(string $legacy): ?string`; null-return signals "unknown" so the caller can fall back to `'ro'` and log via `security_events`. |
| `App\Domain\Migration\Exceptions\UnmappedLegacyBookException` | Thrown by `BackfillLegacyBookAbbreviationsAction` when no mapping is found. Bubbles out of the migration and aborts cutover (per AC §14: fail loudly). |

## HTTP endpoints

| Method | Path | Controller | Request | Resource | Auth | Notes |
|---|---|---|---|---|---|---|
| GET | `/api/v1/verses` | `Api\V1\Verses\ResolveVersesController` (existing) | `Verses\ResolveVersesRequest` (existing — extended) | `Verses\VerseCollection` (existing) | `api-key-or-sanctum` (existing) | Now accepts cross-chapter syntax via `?reference=`. JSON shape unchanged: flat verse array ordered by `(chapter, verse)`; `meta.missing` continues to flag absent verses. 422 with `errors.references[]` on `VerseRangeTooLargeException`. |

## Tasks

- [x] 1. Add `App\Domain\Reference\VerseRange` readonly VO with constructor invariants `(endChapter, endVerse) > (startChapter, startVerse)` and `startVerse, endVerse >= 1`.
- [x] 2. Extend `ReferenceParser::parseOne` to detect a colon on the right-hand side of `-` in the passage segment and emit `VerseRange`; widen `parse()` return type to `array<Reference|VerseRange>` and route the new shape through `parseOne`.
- [x] 3. Update `BibleVerseQueryBuilder::lookupReferences` to accept `array<int, Reference|VerseRange>`; add `lookupVerseRange(VerseRange): Collection` that issues one query per `(version, book)` with `WHERE (chapter, verse) BETWEEN ...` and `orderBy('chapter')->orderBy('verse')`.
- [x] 4. Add `App\Domain\Bible\Exceptions\VerseRangeTooLargeException`; have `lookupVerseRange` precompute the verse total via `bible_chapters.verse_count` and throw when >500.
- [x] 5. Update `ResolveVersesAction` so `expectedTuples()` expands `VerseRange` entries via `bible_chapters.verse_count` for missing-set computation (first chapter from startVerse, intermediate chapters full, last chapter up to endVerse).
- [x] 6. Catch `VerseRangeTooLargeException` in `ResolveVersesRequest::toData` and re-throw as `ValidationException::withMessages(['references' => ...])` so the 422 envelope carries `errors.references[]`. *(Implemented as a global exception render handler in `bootstrap/app.php` returning 422 with `errors.references[]`; the cap exception is raised inside the query builder during `lookupVerseRange`, after `toData` has run.)*
- [x] 7. Update `App\Domain\Favorites\Rules\ParseableReference` to fail validation when the single parsed element is a `VerseRange` rather than a `Reference`.
- [x] 8. Extend `tests/Unit/Domain/Reference/Parser/ReferenceParserTest` with a cross-chapter matrix covering ≥12 valid forms (single, range, chapter-only, chapter range, multi-`;`, version-suffixed, cross-chapter, cross-chapter+version, cross-chapter mid-book, cross-chapter spanning >2 chapters, plus two regressions for already-supported syntax) and ≥6 invalid forms (missing colon on RHS, swapped end<start, zero verse, malformed double colon, trailing dash, non-numeric chapter).
- [x] 9. Add a feature test `tests/Feature/Api/Verses/ResolveCrossChapterVersesTest` exercising `GET /api/v1/verses?reference=MAT.19:27-20:16.VDC`: asserts flat verse array ordered by `(chapter, verse)`, `meta.missing` for an absent verse, and 422 with `errors.references[]` for a >500-verse span.
- [x] 10. Add `App\Domain\Migration\Support\LegacyLanguageCodeMap` with constants for `ron, eng, hun, spa, fra, deu, ita` plus identity for already-2-char codes.
- [x] 11. Add `App\Domain\Migration\Actions\BackfillLegacyLanguageCodesAction::handle(string $table, string $column)`: chunked update walking the column, rewriting via the map, defaulting unknowns to `'ro'` and writing one `security_events` row per defaulted value with `event='language_backfill_default'`, `metadata={original_code, table, row_id}`.
- [x] 12. Add `App\Domain\Migration\Exceptions\UnmappedLegacyBookException` and `App\Domain\Migration\Actions\BackfillLegacyBookAbbreviationsAction::handle(string $table, string $column)` that throws when a value is missing from `_legacy_book_abbreviation_map`.
- [x] 13. Add unit tests for both backfill Actions covering: known mapping, default-unknown for language with `security_events` row asserted, fail-loud for book.
- [x] 14. Migration: `create_legacy_book_abbreviation_map_table` — creates the temp table `_legacy_book_abbreviation_map` (`name VARCHAR(64), language CHAR(2), abbreviation VARCHAR(8)`, UNIQUE `(name, language)`), seeds 66 books × Romanian and English long-form names → USFM-3.
- [x] 15. Migration: `reconcile_symfony_bible_tables` — gated on `Schema::hasTable('bible')`. Renames `bible→bible_versions`, dedupes `book` rows into `bible_books` keyed by USFM-3 abbreviation while populating temp table `_legacy_book_map(legacy_book_id PK, legacy_bible_id, bible_book_id, bible_version_id)`, renames `verse→bible_verses` and adds nullable `bible_version_id`, `bible_book_id` columns.
- [x] 16. Migration: `reconcile_symfony_collection_tables` — `collection→collections`, `collection_topic→collection_topics`, `collection_reference→collection_references`. Reversible.
- [x] 17. Migration: `reconcile_symfony_commentary_tables` — `commentary→commentaries`, `commentary_text→commentary_texts`. New columns are MBA-024's responsibility.
- [x] 18. Migration: `reconcile_symfony_devotional_tables` — `devotional_type→devotional_types`, `devotional_entry→devotionals`. Note in PHPDoc that the `(language, type_id, date)` UNIQUE is owned by MBA-027.
- [x] 19. Migration: `reconcile_symfony_hymnal_tables` — `hymnal_book→hymnal_books`, `hymnal_song→hymnal_songs`, `hymnal_verse→hymnal_verses`; add UNIQUE `(hymnal_book_id, number)` on `hymnal_songs`.
- [x] 20. Migration: `reconcile_symfony_mobile_tables` — `mobile_version→mobile_versions`.
- [x] 21. Migration: `reconcile_symfony_reading_plan_tables` — `plan→reading_plans`, `plan_day→reading_plan_days`, `plan_enrollment→reading_plan_subscriptions` (column `author_id→user_id`), `plan_progress→reading_plan_subscription_days_legacy`.
- [x] 22. Migration: `reconcile_symfony_olympiad_tables` — `question→olympiad_questions`, `question_option→olympiad_answers` with column rename `correct→is_correct`. Reversible.
- [x] 23. Migration: `reconcile_symfony_resource_book_tables` — `resource_book→resource_books`, `resource_book_chapter→resource_book_chapters`, `resource_download→resource_downloads`.
- [x] 24. Migration: `reconcile_symfony_sabbath_school_tables` — `sb_trimester→sabbath_school_trimesters`, `sb_lesson→sabbath_school_lessons`, `sb_section→sabbath_school_segments`, `sb_content→sabbath_school_segment_contents`, `sb_answer→sabbath_school_answers`, `sb_favorite→sabbath_school_favorites`, `sb_highlight→sabbath_school_highlights`. UNIQUE `lesson_unique` deferred to MBA-025.
- [x] 25. Migration: `reconcile_symfony_note_and_favorite_tables` — `note→notes`, `favorite_category→favorite_categories`, `favorite→favorites` (add UNIQUE `(user_id, category_id, reference)`), `devotional_favorite→devotional_favorites`, `hymnal_favorite→hymnal_favorites`.
- [x] 26. Migration: `drop_reading_progress_table` — gated on table existing AND row count zero (or column unused as documented in AC §4); reversible by recreating original shape.
- [x] 27. Migration: `drop_doctrine_artefacts` — drop `doctrine_migration_versions` if present; idempotent guards on `users.salt`, `users.reset_token`, `users.reset_date` (already covered by user reconcile).
- [x] 28. Migration: `backfill_legacy_language_codes` — calls `BackfillLegacyLanguageCodesAction` on `users.language`, `bible_versions.language`, `resource_categories.language`, `olympiad_questions.language`. Must run before width standardisation.
- [x] 29. Migration: `standardise_language_column_widths` — `ALTER COLUMN ... CHAR(2) NOT NULL` (or NULLABLE where the column was) on the four tables; preserves indexes by dropping/recreating where MySQL's `change` semantics require it.
- [x] 30. Migration: `backfill_legacy_book_abbreviations` — calls `BackfillLegacyBookAbbreviationsAction` on `olympiad_questions.book` and any other column listed in AC §13 that may carry long-form values.
- [x] 31. Migration: `widen_notes_book_column` — `notes.book` VARCHAR(3) → VARCHAR(8). Reversible.
- [x] 32. Feature test: `BibleReconcileTest` — seeds legacy `bible/book/verse` shape, runs the bible reconcile migration, asserts renames + nullable FK columns + `_legacy_book_map` populated.
- [ ] 33. Feature test: `CollectionReconcileTest` — *deferred*: trivial rename-only migration covered by `ReconcileTableHelper` unit-tested via the other reconcile tests; CI exercises the no-op path. Worth adding before MBA-031 cutover.
- [ ] 34. Feature test: `CommentaryReconcileTest` — *deferred*: same rationale as task 33.
- [ ] 35. Feature test: `DevotionalReconcileTest` — *deferred*: same rationale; the meaningful UNIQUE assertion lives in MBA-027.
- [x] 36. Feature test: `HymnalReconcileTest` — verifies the three renames and asserts the `(hymnal_book_id, number)` UNIQUE rejects a duplicate insert.
- [ ] 37. Feature test: `MobileReconcileTest` — *deferred*: trivial single-table rename covered by helper.
- [x] 38. Feature test: `ReadingPlanReconcileTest` — verifies the four renames including the `author_id→user_id` column rename and the `plan_progress→reading_plan_subscription_days_legacy` rename-out-of-the-way.
- [x] 39. Feature test: `OlympiadReconcileTest` — verifies the two renames and the `correct→is_correct` column rename.
- [ ] 40. Feature test: `ResourceBookReconcileTest` — *deferred*: trivial rename-only.
- [ ] 41. Feature test: `SabbathSchoolReconcileTest` — *deferred*: rename-only; helper-driven.
- [x] 42. Feature test: `NoteAndFavoriteReconcileTest` — verifies the five renames and asserts the `favorites (user_id, category_id, reference)` UNIQUE rejects a duplicate insert.
- [x] 43. Feature test: `DoctrineCleanupTest` — seeds `doctrine_migration_versions` + leftover `users.salt/reset_token/reset_date` columns, runs the cleanup migration, asserts the table and columns are gone, and re-runs the migration to assert idempotency.
- [x] 44. Feature test: `IdentifierBackfillTest` — exercises `BackfillLegacyLanguageCodesAction` on `users.language` (3-char `ron` → `ro`) and `BackfillLegacyBookAbbreviationsAction` on `notes.book` (long-form `Genesis` → `GEN`); also asserts `security_events` row written for an unknown language.
- [x] 45. Run `make lint-fix`, `make stan`, then `make test-api filter='Reconcile|Backfill|CrossChapter|ReferenceParser'` against the changed surfaces; finish with `make check` from the monorepo root before flipping the story to `qa`.

## Risks & notes

1. **Story is large; recommended optional split.** Three independently
   shippable stripes: **(a)** parser + verses endpoint extension (tasks 1–9);
   **(b)** reconcile migrations + tests (tasks 14–27, 32–43); **(c)**
   identifier standardisation + backfill (tasks 10–13, 28–31, 44). All three
   land before MBA-031 cutover, but (b) and (c) only matter against prod's
   Symfony schema (no-op in CI), and (a) is purely additive. Suggested cut:
   **MBA-023a** = (a) ships immediately; **MBA-023b** = (b)+(c) bundled
   because (c) needs the renamed tables to exist before it ALTERs them.
   Recommendation is to keep the story whole — Engineer can bracket (a) into
   the first PR and (b)+(c) into the second within the same story to keep
   review hygiene.
2. **`_legacy_book_map` shape and consumer.** Created here, consumed in
   MBA-031 ETL when rewriting `bible_verses.bible_book_id`/`bible_version_id`,
   dropped in MBA-032. Document the consumer in the table-creation
   migration's PHPDoc so a future reader does not delete it as orphaned
   scaffolding.
3. **`users.language` deprecation tension.** AC §11 widens `users.language`
   to CHAR(2); MBA-018/MBA-022 already added the JSON `languages` column.
   The single column stays for cutover compat (MBA-032 cleanup drops it).
   The width change is non-breaking for any reader since values are
   already ≤2 chars after the language backfill runs first. Order in the
   migration timestamp: backfill (28) → standardise (29).
4. **Reconcile migrations on a fresh CI/dev DB are no-ops.** Each gates on
   `Schema::hasTable($legacy)`. The feature tests therefore must seed the
   legacy shape themselves (drop the post-create Laravel-shape table, recreate
   the legacy shape via `Schema::create(...)`, seed rows, run `(new $migration
   )->up()`, then assert). Precedent for direct migration invocation is
   `NormalizeUsersRolesMigrationTest`.
5. **`book` deduplication is data-bearing.** Symfony's `book` is per-version
   (e.g. one `Genesis` row per `bible`); Laravel's `bible_books` is global.
   The dedupe keys on USFM-3 abbreviation. If two legacy rows for the same
   book carry diverging metadata (e.g. different `name` strings localised
   per Bible), the migration picks the row from `bible.abbreviation='VDC'`
   first, then the next available. Document this tie-break in the migration.
   Loss-of-information here is acceptable per the story's scope (it's the
   model rewrite — Symfony's per-version localisation moves to JSON columns
   in MBA-024/MBA-027).
6. **Backfill ordering hazard.** Language backfill (28) writes
   `security_events` rows. The `security_events` table exists from MBA-020
   (already shipped). Verify no migration in this story drops/recreates it;
   if a future migration ever does, it would clobber the audit trail.
7. **Cross-chapter parser invariant.** The right-hand-side colon is the only
   disambiguator. AC §16's wording allows ambiguity only if a future passage
   syntax adds a colon meaning something else; we explicitly defer such
   future syntax to a separate story. The parser unit test pins this with
   "no colon on RHS = single-chapter range" and "colon on RHS = cross-chapter
   range" cases that fail loudly if anyone alters the dispatcher.
8. **`ParseableReference` rule blast radius.** Notes/favorites/collections
   FormRequests use `ParseableReference`. After the rule rejects `VerseRange`,
   their existing JSON shape continues to refuse cross-chapter input — the
   422 message reads "must refer to a single passage." That's the same
   message the rule already returns for multi-reference input, so there is
   no consumer-visible message change.
9. **Verse count expansion uses `bible_chapters` as the source of truth.**
   `ResolveVersesAction::expectedTuples` and `BibleVerseQueryBuilder
   ::lookupVerseRange`'s 500-verse cap both query `bible_chapters
   .verse_count`. If the table is unseeded for a particular book, the
   missing-set computation falls back to empty (existing behaviour) and the
   cap defaults to "unbounded for that range" — which would let an
   unintended whole-book scan through. Acceptable in this story (cap is the
   product floor, not a security boundary), but flag it for `MBA-031`
   QA — once the chapters table is fully seeded for prod, the cap becomes
   strictly enforced.
10. **No new Deferred Extractions tripwire entries.** The plan adds two
    backfill Actions, but they share no surface with existing patterns
    (Form Requests / lifecycle Actions), so no register update is needed.
    Existing entries (`Owner-authorize() block` 4/5, `withProgressCounts()`
    helper 2/3) stay as they are.
