# Story: MBA-023-schema-reconcile-foundation

## Title

Reconcile Laravel API schema with the live Symfony production database; standardise language and book identifiers; add cross-chapter reference parsing.

## Status

`done`

## Description

The Laravel API has been built greenfield with new naming, JSON-multilingual
columns, and a cleaner relational layout. The legacy Symfony app, still
serving production, has its own DDL — different table names
(`book`/`verse`/`plan`/`sb_lesson`), different column conventions
(`author_id`, `for_date` typed `varchar(3)` languages, `varchar(10)` book
slugs), and several integrity constraints (UNIQUE indexes) that the Laravel
schema dropped during the rewrite.

Production must keep running through cutover. Renames and reshapes are
allowed on the production database during a maintenance window, but no row
may be lost. This story is the **foundation migration set** that lands the
DDL changes Laravel needs in production, re-asserts the integrity
constraints that were lost, and standardises identifier widths across the
schema. It is purely structural — data ETL from Symfony-shaped values
(e.g. `language='ron'` → `'ro'`, `book='Genesis'` → `'GEN'`) lands here as
backfill, but the row-level Symfony→Laravel ETL (`hymnal_verse` →
`hymnal_songs.stanzas`, etc.) is in `MBA-031`.

The story also extends the Bible reference parser to handle cross-chapter
ranges (e.g. `MAT.19:27-20:16.VDC`) so Sabbath School lessons and devotional
content can link to passages that span multiple chapters. The parser change
is foundational because every downstream feature (Commentary, AI
referencer, frontend modals) depends on it.

## Acceptance Criteria

### Schema reconciliation (idempotent migrations)

1. Idempotent reconcile migrations land for every Symfony→Laravel table
   rename, mirroring the precedent set by
   `2026_04_22_100000_reconcile_symfony_user_table.php` and
   `2026_04_23_120001_reconcile_symfony_resource_tables.php`. Migrations
   short-circuit when the legacy table is absent (fresh dev / CI
   environments are unaffected).
2. Renames covered:
   - `bible` → `bible_versions` (column `abbreviation`/`name`/`language`/
     `has_audio` preserved; reshape `has_audio` is out of scope)
   - `book` → reconciled into `bible_books` (deduplicate per-version rows
     to global rows; preserve mapping for ETL via temporary
     `_legacy_book_map` table)
   - `verse` → `bible_verses` (FK rewrite is out of scope here — it's data
     ETL in MBA-031; this story only renames + adds the FK columns nullable)
   - `collection` → `collections` (kept as parent table)
   - `collection_topic` → `collection_topics`
   - `collection_reference` → `collection_references`
   - `commentary` → `commentaries`
   - `commentary_text` → `commentary_texts`
   - `daily_verse` → unchanged (already aligned)
   - `devotional_type` → `devotional_types`
   - `devotional_entry` → `devotionals`
   - `hymnal_book` → `hymnal_books`
   - `hymnal_song` → `hymnal_songs`
   - `hymnal_verse` → `hymnal_verses` (kept until MBA-031 ETL collapses
     into `hymnal_songs.stanzas`)
   - `mobile_version` → `mobile_versions`
   - `news` → unchanged (already aligned)
   - `plan` → `reading_plans`
   - `plan_day` → `reading_plan_days`
   - `plan_enrollment` → `reading_plan_subscriptions`
   - `plan_progress` → `reading_plan_subscription_days_legacy` (renamed
     out of the way; MBA-031 builds the new shape and drops this)
   - `qr_codes` → unchanged
   - `question` → `olympiad_questions`
   - `question_option` → `olympiad_answers`
   - `resource_book` → `resource_books`
   - `resource_book_chapter` → `resource_book_chapters`
   - `resource_download` → `resource_downloads`
   - `sb_trimester` → `sabbath_school_trimesters`
   - `sb_lesson` → `sabbath_school_lessons`
   - `sb_section` → `sabbath_school_segments`
   - `sb_content` → `sabbath_school_segment_contents`
   - `sb_answer` → `sabbath_school_answers`
   - `sb_favorite` → `sabbath_school_favorites`
   - `sb_highlight` → `sabbath_school_highlights`
   - `note` → `notes`
   - `favorite` → `favorites`
   - `favorite_category` → `favorite_categories`
   - `devotional_favorite` → `devotional_favorites`
   - `hymnal_favorite` → `hymnal_favorites`
3. Column renames applied alongside table renames where Laravel convention
   diverges: `author_id` → `user_id`, `lastLogin` → `last_login`,
   `createdAt` → `created_at`, `correct` → `is_correct`, `image_path` →
   `image_url` (news only — already shipped in MBA-022).
4. Doctrine artefacts dropped from production:
   `doctrine_migration_versions` table, `users.salt`, `users.reset_token`,
   `users.reset_date`. Migrations are reversible (data-bearing drops gated
   on emptiness or column unused).
5. `reading_progress` table dropped (superseded by reading plans —
   confirmed product decision).

### Regression UNIQUE constraints (must be added before deploy)

6. `bible_verses` re-adds `UNIQUE (bible_version_id, bible_book_id,
   chapter, verse)` (lost during Laravel rewrite; Symfony had
   `verse_unique`).
7. `devotionals` re-adds `UNIQUE (language, type_id, date)` (lost; Symfony
   had `devotional_unique`). `type_id` column is added by MBA-027 — this
   migration is sequenced after MBA-027 lands the column, or the UNIQUE
   is added there directly.
8. `hymnal_songs` re-adds `UNIQUE (hymnal_book_id, number)` (lost; Symfony
   had `song_unique`).
9. `favorites` re-adds `UNIQUE (user_id, category_id, reference)` (lost;
   Symfony had `favorite_unique` on book+chapter+position; reference is
   the canonical equivalent in the Laravel model).
10. `sabbath_school_lessons` re-adds `UNIQUE (language, age_group,
    trimester_id, date_from, date_to)` (lost; Symfony had `lesson_unique`).
    Depends on MBA-025 adding `trimester_id` and `age_group` columns —
    UNIQUE is added there.

### Identifier standardisation

11. All `language` columns standardised to `CHAR(2)` ISO-2 (`ro`, `en`,
    `hu`, `es`, `fr`, `de`, `it`). Mixed widths in the current Laravel
    schema (`char(2)`, `varchar(3)`, `varchar(8)`) are unified to
    `CHAR(2) NOT NULL` (or `NULLABLE` where the row's product semantics
    allow language-agnostic content). Affected tables include
    `bible_versions`, `resource_categories`, `users.language` (the
    legacy single-code column),
    `olympiad_questions`, plus any table touched by reconcile.
12. ETL backfill converts Symfony 3-char codes to 2-char codes:
    `ron→ro, eng→en, hun→hu, spa→es, fra→fr, deu→de, ita→it`. Unknown
    codes default to `ro` and are logged via `security_events`
    (`event='language_backfill_default'`, `metadata={original_code, table,
    row_id}`) for review.
13. All `book` identifier columns standardised to USFM-3 (`GEN`, `EXO`,
    `MAT`, `ROM`, `REV`...) in `VARCHAR(8)`. Affected tables: `notes.book`
    (already `VARCHAR(3)`, widen to `VARCHAR(8)`), `favorites.reference`
    (parsed/rewritten in MBA-031 ETL), `olympiad_questions.book`,
    `commentary_texts.book` (added by MBA-024), `bible_books.abbreviation`
    (already aligned).
14. ETL backfill: where Symfony stores book as a non-USFM value (e.g.
    `Genesis`, `1 Corinthians`), a mapping table
    `_legacy_book_abbreviation_map` (Romanian + English long-form names
    → USFM-3) drives the rewrite. Unmapped values fail loudly (block
    migration) rather than degrading to garbage; operator must extend the
    map.

### Cross-chapter reference parser

15. `App\Domain\Bible\References\ReferenceParser` extended to accept the
    syntax `BOOK.CH:V[-CH:V][.VER]`. Examples that must parse:
    - `MAT.5:3` — single verse
    - `ROM.8:28-30` — verse range within one chapter (already supported)
    - `MAT.19:27-20:16` — cross-chapter range
    - `MAT.19:27-20:16.VDC` — cross-chapter range with version
    - `1CO.13` — whole chapter
    - `1CO.13-14` — chapter range (whole chapters)
16. Parser detects cross-chapter intent by the presence of a colon in the
    right-hand side of the hyphen (`-20:16` vs `-30`). When detected, the
    parser emits a structured range:
    ```php
    new VerseRange(
        book: 'MAT',
        startChapter: 19, startVerse: 27,
        endChapter: 20, endVerse: 16,
        version: 'VDC',
    )
    ```
17. `GET /api/v1/verses?references=MAT.19:27-20:16.VDC` resolves the range
    by querying `bible_verses` `WHERE (chapter, verse) >= (19, 27) AND
    (chapter, verse) <= (20, 16)`. Output is a flat verse array (one
    element per verse), ordered by `(chapter, verse)`. The chapter break
    is not represented as a structural boundary in the response — clients
    render the heading themselves from the `chapter` field on each verse.
18. Existing single-chapter and single-verse callers (Bible reader,
    favourites, notes, daily verse) are unaffected. Feature tests pin
    the JSON shape for the legacy and the new cases.

### Tests

19. Each table rename has a feature test that runs the migration on a
    seeded Symfony-shaped fixture and asserts the post-migration schema
    matches the Laravel-shaped expectation (column names, indexes, FK
    behaviour).
20. Regression UNIQUE constraints have a test inserting a duplicate
    immediately after migration and asserting `IntegrityConstraintViolation`.
21. Cross-chapter parser has a unit test matrix covering at least 12
    valid references (single, ranges, chapter-only, version-suffixed,
    cross-chapter, cross-chapter+version) plus 6 invalid references that
    should throw `InvalidReferenceException`.
22. The language and book backfill is covered by a migration test that
    seeds `users.language='ron'`, `notes.book='Genesis'` and asserts
    post-run values are `ro` and `GEN` respectively.

## Scope

### In Scope

- Idempotent table+column reconcile migrations covering every Symfony
  table that has a Laravel counterpart.
- Re-adding the lost UNIQUE indexes from §6–10 (some land in dependent
  stories that introduce the columns those uniques reference).
- Language and book identifier standardisation across the schema.
- Cross-chapter reference parser + verse resolver endpoint update.
- Backfill scripts for language/book code conversion, with logging of
  defaults via `security_events`.

### Out of Scope

- Symfony→Laravel **row-level** ETL (hymnal verse aggregation into JSON,
  reading plan progress materialisation, SS content typed-block split,
  etc.). All in MBA-031.
- New Symfony parity features (Commentary, Resource Books, SS Trimester
  re-introduction, Devotional types entity, etc.). Each in its own story.
- Dropping legacy columns left around for ETL compatibility (e.g.
  `users.language` legacy 3-char column). Final cleanup in MBA-032.
- Switching the queue connection from `database` to Redis + Horizon
  — moved to MBA-031 (where it is needed for the ETL job runner).

## API Contract Required

- `GET /api/v1/verses?references=<canonical>` — output shape unchanged for
  single-verse and single-chapter cases; new cases (cross-chapter ranges)
  return a flat verse array as documented in AC §17. Validation of the
  reference syntax stays in `ReferenceParser`; an invalid reference
  returns `422` with the existing error envelope.

## Technical Notes

- Reconcile migrations use the same guard pattern as the existing
  `reconcile_symfony_*` migrations (`Schema::hasTable($legacy)` checked
  upfront; if absent, migration is a no-op). This keeps fresh CI / dev
  environments green without conditional `if (App::environment(...))`
  branches.
- The `_legacy_book_map` and `_legacy_book_abbreviation_map` temporary
  tables are created by this story and dropped by MBA-032 (Phase 3
  cleanup). They are referenced during MBA-031 ETL.
- The cross-chapter parser change is intentionally additive — it does not
  alter parser behaviour for any reference that parsed correctly before
  this story. The right-hand-side colon is the disambiguator.
- For cross-chapter ranges that span more than 2 chapters
  (e.g. `JHN.5:1-7:30`), the resolver emits all verses across all
  intermediate chapters. There is no upper bound on range size at the
  parser level; the resolver caps at 500 verses per range and returns a
  `422` with `errors.references[]` if exceeded (prevents accidental
  whole-book resolves).

## References

- Production DDL: provided by stakeholder (live Symfony database) on
  2026-05-02.
- Existing reconcile precedents:
  `2026_04_22_100000_reconcile_symfony_user_table.php`,
  `2026_04_23_120001_reconcile_symfony_resource_tables.php`.
- Reference parser entry point: `App\Domain\Bible\References\ReferenceParser`.
- Cross-app feature analysis: chat thread 2026-05-02 (sections 1–18 of
  the per-feature audit Symfony↔Laravel).
