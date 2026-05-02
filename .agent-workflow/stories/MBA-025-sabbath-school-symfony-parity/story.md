# Story: MBA-025-sabbath-school-symfony-parity

## Title

Restore full Symfony parity for the Sabbath School domain: trimester
hierarchy, lesson metadata (age group, memory verse, image), explicit
section dates, typed content blocks, and intra-text highlights with
offsets and colour.

## Status

`draft`

## Description

The Laravel API has a flattened Sabbath School model:
`sabbath_school_lessons → sabbath_school_segments` with `content` stored
as a single `LONGTEXT` and questions extracted into a separate
`sabbath_school_questions` table. The Symfony app uses the richer
hierarchy `sb_trimester → sb_lesson → sb_section → sb_content` where
content is a list of typed blocks (text, question, memory verse, etc.)
and highlights are anchored to a specific block by character offsets
with a colour, not a Bible reference string.

The Sabbath School admin UI (admin repo MB-011) was built against the
**Symfony-rich model** and already exposes a 4-level editor (Trimester →
Lesson → Section → Content). The Laravel API is the bottleneck: admin
pages don't render correctly because the API doesn't return the trimester
or the typed blocks. Mobile apps (90–95% of traffic) currently consume
the Symfony API and rely on the rich model for differentiated rendering
(question cards, memory-verse highlight, etc.).

This story restores full Symfony parity at the API layer. It is a major
schema change: `sabbath_school_segments.content LONGTEXT` is split into
rows of `sabbath_school_segment_contents (segment_id, type, position,
title, content)`; questions become content rows with `type='question'`;
`sabbath_school_highlights.passage VARCHAR(255)` is replaced by
`(segment_content_id, start_position, end_position, color)` to match
Symfony's intra-text selection model.

`sabbath_school_favorites` keeps the Laravel-richer model
(`segment_id` allowing per-segment favourites in addition to whole-lesson),
but with `segment_id NULL` replacing the sentinel `0` for cleaner
semantics, gated by a partial UNIQUE index.

## Acceptance Criteria

### Trimester (re-introduced)

1. `sabbath_school_trimesters` table (renamed from `sb_trimester` by
   MBA-023) is added back to the public domain. Columns: `id`, `year`
   (`VARCHAR(4)`), `language` (`CHAR(2)`), `age_group` (`VARCHAR(50)`),
   `title` (`VARCHAR(128)`), `number` (`SMALLINT`), `date_from` (`DATE`),
   `date_to` (`DATE`), `image_cdn_url` (`TEXT NULL`), timestamps.
2. UNIQUE `(language, age_group, date_from, date_to)`.
3. `sabbath_school_lessons.trimester_id BIGINT UNSIGNED NULL` FK with
   `ON DELETE CASCADE`.
4. `sabbath_school_lessons` gains the missing Symfony fields:
   - `age_group VARCHAR(50) NOT NULL`
   - `memory_verse TEXT NULL`
   - `image_cdn_url TEXT NULL`
   - `number SMALLINT NOT NULL`
5. UNIQUE `(language, age_group, trimester_id, date_from, date_to)`
   re-asserted on lessons (Symfony `lesson_unique`).

### Section explicit date

6. `sabbath_school_segments.for_date DATE NULL` added (matches Symfony
   `sb_section.for_date`). The existing `day TINYINT` column is kept
   nullable for backward compatibility but its semantic role is
   superseded by `for_date`. Backfill: `for_date = lesson.week_start +
   (day) days` for non-null `day` values.
7. The lesson detail Resource exposes both `for_date` (preferred) and
   `day` (deprecated, retained for one minor version of mobile rollout).
   New segments created via the admin API must set `for_date`; setting
   `day` only is a deprecation warning header but accepted until
   mobile cutover.

### Typed content blocks

8. New table `sabbath_school_segment_contents` (renamed from `sb_content`
   by MBA-023): `id`, `segment_id BIGINT UNSIGNED NOT NULL` FK, `type
   VARCHAR(50) NOT NULL`, `title VARCHAR(128) NULL`, `position SMALLINT
   UNSIGNED NOT NULL`, `content LONGTEXT NOT NULL`, timestamps.
9. Composite index `(segment_id, position)`.
10. Allowed values for `type` (validated by Form Request): `text`,
    `question`, `memory_verse`, `passage`, `prayer`, `discussion`,
    `summary`. The list is derived from Symfony usage; future types can
    be added by extending the validation rule and a corresponding
    `SegmentContentType` enum.
11. `sabbath_school_questions` table is **dropped** as a separate
    surface; questions become content rows with `type='question'`. The
    admin UI in MB-011 already authors them as typed blocks.
12. `sabbath_school_answers.sabbath_school_question_id` is renamed to
    `segment_content_id` with FK to `sabbath_school_segment_contents`.
    Symfony already does this — `sb_answer.question_id` references
    `sb_content.id`. ETL by MBA-031 maps existing rows.
13. The lesson detail public endpoint (`GET /api/v1/sabbath-school/
    lessons/{lesson}`) returns segments with a nested `contents[]` array
    in `position` order. Each content has `type`, `title`, `position`,
    `content`. Questions still have `id` (clients submit answers
    referencing it).

### Intra-text highlights

14. `sabbath_school_highlights` is reshaped:
    - DROP `passage VARCHAR(255)`.
    - ADD `segment_content_id BIGINT UNSIGNED NOT NULL` FK to
      `sabbath_school_segment_contents`.
    - ADD `start_position INT UNSIGNED NOT NULL` (character offset
      within the content block, inclusive).
    - ADD `end_position INT UNSIGNED NOT NULL` (character offset, exclusive).
    - ADD `color VARCHAR(9) NOT NULL` (`#RRGGBB` or `#RRGGBBAA`).
15. Unique `(user_id, segment_content_id, start_position, end_position)`
    prevents duplicate identical highlights.
16. `POST /api/v1/sabbath-school/highlights/toggle` body changes from
    `{passage}` to `{segment_content_id, start_position, end_position,
    color}`. Toggle semantics: identical (user, content, range) deletes;
    otherwise creates. Updating colour on an existing range is a separate
    `PATCH /api/v1/sabbath-school/highlights/{highlight}`.
17. `GET /api/v1/sabbath-school/highlights?segment_id=` returns a flat
    list of all highlights across the segment's content blocks. Each
    item carries `segment_content_id` so the client can attach to the
    right block.
18. Existing Laravel passage-string highlights (created during the
    Laravel-only window before this story) are migrated by MBA-031 by
    parsing the passage and generating offset-based highlights against
    the corresponding content block. If parsing fails, the row is
    archived to `sabbath_school_highlights_legacy` (read-only) with
    `created_at` preserved, and a `security_events` row is logged. No
    user data is silently lost.

### Favorites cleanup (lighter touch)

19. `sabbath_school_favorites.sabbath_school_segment_id` becomes
    nullable and the sentinel `0` is replaced with `NULL` (whole-lesson
    favourites). Existing rows with `segment_id=0` are updated to NULL
    by the same migration.
20. UNIQUE index becomes a partial unique pair:
    - `UNIQUE (user_id, sabbath_school_lesson_id) WHERE sabbath_school_segment_id IS NULL`
    - `UNIQUE (user_id, sabbath_school_segment_id) WHERE sabbath_school_segment_id IS NOT NULL`
    MySQL 8 supports this via functional/expression indexes; if the
    target is MariaDB, fallback to two separate non-partial UNIQUEs
    (acceptable: the `IS NULL` partial is the only enforcement that
    differs).

### Admin endpoints

21. Trimester CRUD:
    - `GET /api/v1/admin/sabbath-school/trimesters?language=`
    - `POST /api/v1/admin/sabbath-school/trimesters`
    - `PATCH /api/v1/admin/sabbath-school/trimesters/{trimester}`
    - `DELETE /api/v1/admin/sabbath-school/trimesters/{trimester}`
22. Lesson endpoints extended for new fields (`age_group`, `memory_verse`,
    `image_cdn_url`, `number`, `trimester_id`).
23. Segment content CRUD:
    - `GET /api/v1/admin/sabbath-school/segments/{segment}/contents`
    - `POST /api/v1/admin/sabbath-school/segments/{segment}/contents`
    - `PATCH /api/v1/admin/sabbath-school/segment-contents/{content}`
    - `DELETE /api/v1/admin/sabbath-school/segment-contents/{content}`
    - `POST /api/v1/admin/sabbath-school/segments/{segment}/contents/reorder`
24. The existing reorder endpoint
    `POST /api/v1/admin/sabbath-school/segments/{segment}/questions/reorder`
    (from MBA-022 §12) is retired — questions are now content blocks and
    use the contents reorder endpoint above. Document the removal in the
    deferred extractions tripwire.

### Public endpoints

25. `GET /api/v1/sabbath-school/trimesters?language=` — list published
    trimesters for the request language, ordered by `(year DESC,
    number DESC)`.
26. `GET /api/v1/sabbath-school/trimesters/{trimester}` — detail with
    nested lessons (paginated by week or full list, TBD with frontend).
27. Lesson list endpoint accepts optional `trimester` and `age_group`
    filters.

### Tests

28. Feature tests for trimester CRUD and listing (auth, validation,
    happy path, language scoping).
29. Migration tests asserting:
    - `for_date` backfilled correctly from `day` for existing rows.
    - Questions migrated to content blocks with `type='question'`,
      preserving `position` and `prompt` → `content`.
    - Highlights ETL: parseable passage strings produce offset-based
      highlights against the right content block; unparseable rows land
      in `sabbath_school_highlights_legacy` with security event logged.
30. Unit tests for the highlight toggle action covering: identical
    range deletes, different range creates, colour-only update goes
    through PATCH not toggle.
31. Unit tests for the favourites partial-uniqueness migration
    asserting NULL and non-NULL segment_id rows can coexist for the
    same lesson without collision.

## Scope

### In Scope

- Trimester table + lesson metadata + section `for_date`.
- Typed content blocks; questions as content rows with `type='question'`.
- Intra-text highlights with offsets and colour.
- Favourites partial-NULL refactor.
- Admin endpoints for trimester CRUD and segment-content CRUD/reorder.
- Public endpoints for trimester listing and detail.
- Schema migrations + ETL hooks (the heavy data ETL — passage parsing
  for old highlights, content longtext split — runs in MBA-031).

### Out of Scope

- The `sabbath_school_segments.content LONGTEXT` field is **kept** for
  the duration of the rollout, with new authoring funneling into
  `_segment_contents` rows. MBA-032 drops the legacy column after mobile
  cutover. Until then, the public lesson detail endpoint serves both
  shapes via Resource fallback (prefer `contents[]`; fall back to
  `content` text if no contents rows exist for a segment — handles
  not-yet-migrated lessons).
- The `sabbath_school_questions` table itself is dropped only after the
  ETL migrates all rows; this story prepares the schema and the answers
  FK rewrite, but the actual drop is sequenced by MBA-032.
- Admin UI alignment for `for_date`, content blocks, etc. — that's admin
  MB-015.
- Frontend SS reader rendering — that's frontend MB-018.

## API Contract Required

- Lesson detail Resource: `segments[].contents[]` (new) is the canonical
  shape. `segments[].content` (legacy) is included only when a segment
  has no rows in the new table — this avoids breaking mobile during the
  transition window.
- Highlight Resource: replace `passage` field with `segment_content_id`,
  `start_position`, `end_position`, `color`. Mobile and frontend must
  update at the same time as this API ships; coordinate via cutover
  story.
- Trimester Resource: standard list + detail with nested lessons.

## Technical Notes

- This is the largest schema change in the cutover. Sequencing is
  load-bearing: MBA-023 lands the renames, then MBA-025 lands the
  reshape (this story), then MBA-031 ETLs the old longtext into typed
  blocks and the old passage strings into offset highlights.
- The decision to keep the legacy `segments.content` column during the
  rollout is intentional — it gives mobile a known shape while the new
  one is being adopted, and avoids a flag day.
- `for_date NULL` is preserved (Symfony has nullable). Some segments
  (intro, recap, prayer week) genuinely have no calendar date.
- The highlight model (`segment_content_id` + offsets + colour) is the
  Symfony shape; it is strictly more expressive than the Laravel
  passage-string variant. We accept that some legacy Laravel highlights
  cannot be cleanly mapped (passage strings reference whole verses, not
  intra-text offsets) and archive those into the `_legacy` table per
  AC §18.
- Favourites partial-NULL UNIQUE: this is the cleanest model. Postgres
  handles partial indexes natively; MySQL 8.0+ supports functional
  indexes; the fallback (two non-partial uniques) leaks a tiny edge
  case but is acceptable since application code enforces the gate.

## References

- MBA-023 schema reconcile foundation (table renames feed this story).
- MBA-031 ETL (passage-string highlights → offset highlights, content
  longtext → typed blocks).
- Admin MB-011 (Sabbath School full CRUD; already authored against the
  rich model).
- Admin MB-015 (alignment story for the new API shape).
- Frontend MB-018 (public lesson reader rendering).
- Symfony DDL: `sb_trimester`, `sb_lesson`, `sb_section`, `sb_content`,
  `sb_answer`, `sb_favorite`, `sb_highlight` from production DDL
  2026-05-02.
