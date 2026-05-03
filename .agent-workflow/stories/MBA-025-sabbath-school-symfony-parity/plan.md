# Plan: MBA-025-sabbath-school-symfony-parity

## Approach

Land Sabbath School Symfony parity in three layers on top of the MBA-023
reconcile renames: (1) idempotent schema-harmonisation migrations that
both **create** the new tables on fresh dev/CI installs and **reshape**
the renamed Symfony tables in production (column adds, renames, FK
rewires), (2) a richer domain layer (`SabbathSchoolTrimester`,
`SabbathSchoolSegmentContent`) plus reshaped `Highlight` / `Favorite` /
`Answer` actions, (3) an extended HTTP surface that adds trimester
public + admin endpoints, segment-content admin CRUD, and rewires the
highlight + answer endpoints onto the new identifiers. The `passage` /
`segment_id=0` legacy columns survive through this story (nullable
coexisting columns, plus a `_legacy` archive table) so MBA-031 can ETL
without a flag day; MBA-032 finalises NOT NULL flips and column drops
after mobile cutover.

## Open questions — resolutions

1. **Symfony / Laravel column-name divergence post-rename.** MBA-023
   only renames *tables* via `ReconcileTableHelper::rename`, not
   columns. So after MBA-023, production has Symfony column names
   (`lesson_id`, `section_id`, `for_date`, `date_from`, `date_to`)
   while fresh dev has Laravel names (`sabbath_school_lesson_id`,
   `sabbath_school_segment_id`, `day`, `week_start`, `week_end`).
   Resolution: every reshape migration calls
   `ReconcileTableHelper::renameColumnIfPresent` for both directions,
   then idempotently adds any missing columns. Final shape is the
   Laravel-Symfony hybrid documented in **Schema changes** below.
2. **`sabbath_school_trimesters` create-or-reshape.** Production has it
   from MBA-023 rename (Symfony shape); fresh dev does not.
   Migration `if (! Schema::hasTable(...)) Schema::create(...)`,
   else use `Schema::table` to add any missing columns. Same pattern
   for `sabbath_school_segment_contents` (rename of `sb_content`).
3. **`sabbath_school_answers` FK rewire safe in dev.** In dev, existing
   answer rows reference `sabbath_school_questions.id`; after the
   column rename + FK target swap to `sabbath_school_segment_contents`,
   those FKs would dangle on dev seed/test data. Mitigation: drop the
   old FK constraint **before** rename, leave the new FK off until
   MBA-031 backfills (column nullable in dev/CI for the rollout
   window). Dev tests get rewritten to author answers via
   segment-content rows; the legacy answer factory is updated to
   create a `type='question'` segment-content under the hood.
4. **Highlight reshape: drop `passage` deferred to MBA-032.** Per AC §14
   the canonical shape removes `passage` and requires
   `segment_content_id` / `start_position` / `end_position` /
   `color` NOT NULL. Doing both in this story requires data ETL that
   lives in MBA-031. Resolution: this story adds the four new columns
   **nullable**, keeps `passage` nullable, creates the empty
   `sabbath_school_highlights_legacy` archive table, and rewires the
   API contract to require the new fields on write. MBA-032 flips NOT
   NULL and drops `passage` after MBA-031 has backfilled. Document
   this split in `improvements.md` follow-ups.
5. **Public list endpoint filters out un-migrated highlight rows.**
   Until MBA-031 runs, some highlight rows have NULL new-fields.
   `ListSabbathSchoolHighlightsController` filters
   `whereNotNull('segment_content_id')` so the API never serves a
   half-shaped row.
6. **Favourites partial UNIQUE on MySQL 8.** True partial indexes are
   Postgres-only. MySQL 8 functional-index equivalent: drop the
   existing 3-col unique, add a unique index over
   `(user_id, sabbath_school_lesson_id, COALESCE(sabbath_school_segment_id, 0))`
   to cover the whole-lesson case, plus a unique
   `(user_id, sabbath_school_segment_id) WHERE sabbath_school_segment_id IS NOT NULL`
   approximated by a unique on
   `(user_id, COALESCE(sabbath_school_segment_id, 0))` filtered to
   non-null at the application layer — actually the cleanest single
   index is `(user_id, sabbath_school_lesson_id, COALESCE(sabbath_school_segment_id, 0))`
   which collapses NULL to the sentinel `0` at index time only,
   preserving the column's NULL semantics for application code.
   Application-level toggle action enforces "no duplicate (user,
   segment) across lessons" since a segment belongs to exactly one
   lesson, so the composite index is sufficient.
7. **Trimester "published" semantics.** AC §1 enumerates trimester
   columns and does **not** include `published_at`. AC §25 says "list
   published trimesters" — interpreted as Symfony parity, where every
   trimester row is implicitly published once it exists. No
   `published_at` column added. Public list returns every trimester for
   the requested language ordered `(year DESC, number DESC)`. Confirm
   with product if a draft state is later needed.
8. **Answer endpoints relocate to segment-content.** Existing routes
   `/sabbath-school/questions/{question}/answer` no longer make sense
   once `sabbath_school_answers.segment_content_id` is the FK. New
   routes: `/sabbath-school/segment-contents/{content}/answer` (Show /
   Upsert / Delete). Form Request validates `content.type === 'question'`
   so non-question content blocks 422. Old `{question}` paths are
   removed; mobile cutover coordinates the path change with the body
   change. Action / Controller / FormRequest names retain the
   `…SabbathSchoolAnswer…` prefix (sibling parity preserved).
9. **Lesson admin CRUD added.** AC §22 says "lesson endpoints extended
   for new fields" but no admin lesson endpoints exist today (MBA-022
   only landed `lessons/{lesson}/segments/reorder`). Admin MB-011's
   editor needs writes for `age_group`, `memory_verse`,
   `image_cdn_url`, `number`, `trimester_id`, plus core fields. This
   story adds full admin lesson CRUD: `GET / POST / PATCH / DELETE
   /api/v1/admin/sabbath-school/lessons[/{lesson}]`.
10. **Question admin reorder retired (AC §24).** Drop the route, the
    `ReorderSegmentQuestionsController`, the
    `ReorderSegmentQuestionsAction`, and the `ReorderSegmentQuestions`
    feature test. Admin reorders questions via the new
    `segments/{segment}/contents/reorder` endpoint instead. Append a
    deferred-extractions tripwire row noting the removal so a future
    sweep doesn't reintroduce a parallel surface.
11. **`SabbathSchoolQuestion` model survives this story.** AC §11 +
    "Out of Scope" make clear: the *table* is dropped only after
    MBA-031 ETL completes (sequenced in MBA-032). For this story,
    keep the model and table; remove the public/admin question
    surfaces; the model becomes legacy-only (consumed by MBA-031 ETL).
    No `@deprecated` tag — the model class is genuinely still in use
    by the ETL.
12. **`InvalidSabbathSchoolPassageException` deleted.** The new
    highlight Action no longer parses passages (Reference parser
    dependency removed). Exception class and its consumer test cases
    are deleted; the highlight write path now validates structurally
    (Form Request) only.

## Domain layout

```
app/Domain/SabbathSchool/
├── Models/
│   ├── SabbathSchoolTrimester.php                      # NEW
│   ├── SabbathSchoolLesson.php                         # EVOLVED — trimester relation, new attrs, withSegmentContents detail
│   ├── SabbathSchoolSegment.php                        # EVOLVED — for_date, segmentContents() hasMany
│   ├── SabbathSchoolSegmentContent.php                 # NEW — type/title/position/content + segment belongsTo
│   ├── SabbathSchoolHighlight.php                      # EVOLVED — segment_content_id + start/end/color
│   ├── SabbathSchoolFavorite.php                       # EVOLVED — segment_id nullable, sentinel removed
│   ├── SabbathSchoolAnswer.php                         # EVOLVED — segment_content_id FK, segmentContent() relation
│   └── SabbathSchoolQuestion.php                       # KEPT (legacy; no API surface)
├── QueryBuilders/
│   ├── SabbathSchoolTrimesterQueryBuilder.php          # NEW — forLanguage(Language), withLessons()
│   ├── SabbathSchoolLessonQueryBuilder.php             # EVOLVED — forTrimester(int), forAgeGroup(string), withLessonDetail() now eager-loads segments.segmentContents (replaces segments.questions)
│   ├── SabbathSchoolHighlightQueryBuilder.php          # EVOLVED — forSegment(int) via JOIN segment_contents; forSegmentContent(int); migrated() (whereNotNull('segment_content_id'))
│   ├── SabbathSchoolFavoriteQueryBuilder.php           # EVOLVED — forLessonAndSegment now treats segment_id NULL as whole-lesson; forWholeLesson(int $lessonId)
│   └── SabbathSchoolAnswerQueryBuilder.php             # EVOLVED — forSegmentContent(int) replaces forQuestion(int)
├── DataTransferObjects/
│   ├── ToggleSabbathSchoolHighlightData.php            # EVOLVED — replaces passage with segmentContentId + startPosition + endPosition + color
│   ├── PatchSabbathSchoolHighlightData.php             # NEW — color (only mutable attribute)
│   ├── ToggleSabbathSchoolFavoriteData.php             # EVOLVED — segmentId becomes ?int (NULL = whole lesson)
│   ├── UpsertSabbathSchoolAnswerData.php               # EVOLVED — replace question with segmentContent
│   ├── TrimesterData.php                               # NEW — year/language/age_group/title/number/date_from/date_to/image_cdn_url
│   ├── LessonData.php                                  # NEW — language/title/age_group/trimester_id/memory_verse/image_cdn_url/number/date_from/date_to/published_at
│   └── SegmentContentData.php                          # NEW — type/title/position/content
├── Actions/
│   ├── CreateTrimesterAction.php                       # NEW
│   ├── UpdateTrimesterAction.php                       # NEW
│   ├── DeleteTrimesterAction.php                       # NEW
│   ├── ListTrimestersAction.php                        # NEW (cached, language-scoped)
│   ├── ShowTrimesterAction.php                         # NEW (cached, eager-loads lessons)
│   ├── CreateLessonAction.php                          # NEW
│   ├── UpdateLessonAction.php                          # NEW
│   ├── DeleteLessonAction.php                          # NEW
│   ├── ListSabbathSchoolLessonsAction.php              # EVOLVED — accepts ?trimester=&age_group= filters in cache key
│   ├── ShowSabbathSchoolLessonAction.php               # EVOLVED — eager-loads segments.segmentContents (Resource fallback to segments.content if a segment has no contents rows)
│   ├── CreateSegmentContentAction.php                  # NEW
│   ├── UpdateSegmentContentAction.php                  # NEW
│   ├── DeleteSegmentContentAction.php                  # NEW
│   ├── ReorderSegmentContentsAction.php                # NEW (replaces ReorderSegmentQuestionsAction)
│   ├── ReorderLessonSegmentsAction.php                 # KEPT
│   ├── ToggleSabbathSchoolHighlightAction.php          # EVOLVED — no parser; identical (user, segment_content, range) deletes; otherwise creates
│   ├── PatchSabbathSchoolHighlightColorAction.php      # NEW
│   ├── ToggleSabbathSchoolFavoriteAction.php           # EVOLVED — handles NULL segmentId (whole-lesson)
│   ├── UpsertSabbathSchoolAnswerAction.php             # EVOLVED — keyed by segment_content_id
│   └── DeleteSabbathSchoolAnswerAction.php             # EVOLVED — keyed by segment_content_id
├── Support/
│   ├── SabbathSchoolCacheKeys.php                      # EVOLVED — add trimestersList(language), trimester(id, language), lessonsList key adds trimesterId+ageGroup
│   ├── SabbathSchoolFavoriteSentinel.php               # DELETE — sentinel replaced by NULL semantics
│   └── SegmentContentType.php                          # NEW (enum: text, question, memory_verse, passage, prayer, discussion, summary)
└── Exceptions/
    └── InvalidSabbathSchoolPassageException.php        # DELETE — passage parsing removed from highlight write path

app/Http/Controllers/Api/V1/SabbathSchool/              # public namespace
├── ListSabbathSchoolTrimestersController.php           # NEW
├── ShowSabbathSchoolTrimesterController.php            # NEW
├── ListSabbathSchoolLessonsController.php              # EVOLVED — accepts ?trimester=&age_group=
├── ShowSabbathSchoolLessonController.php               # KEPT (Resource shape changes; controller unchanged)
├── ListSabbathSchoolHighlightsController.php           # EVOLVED — applies migrated() filter
├── ToggleSabbathSchoolHighlightController.php          # EVOLVED — new request shape
├── PatchSabbathSchoolHighlightController.php           # NEW
├── ListSabbathSchoolFavoritesController.php            # KEPT
├── ToggleSabbathSchoolFavoriteController.php           # EVOLVED — accepts NULL segment_id
├── ShowSabbathSchoolAnswerController.php               # EVOLVED — binds {content}
├── UpsertSabbathSchoolAnswerController.php             # EVOLVED — binds {content}
└── DeleteSabbathSchoolAnswerController.php             # EVOLVED — binds {content}

app/Http/Controllers/Api/V1/Admin/SabbathSchool/        # admin namespace
├── ListAdminTrimestersController.php                   # NEW
├── CreateTrimesterController.php                       # NEW
├── UpdateTrimesterController.php                       # NEW
├── DeleteTrimesterController.php                       # NEW
├── ListAdminLessonsController.php                      # NEW
├── CreateLessonController.php                          # NEW
├── UpdateLessonController.php                          # NEW
├── DeleteLessonController.php                          # NEW
├── ListSegmentContentsController.php                   # NEW
├── CreateSegmentContentController.php                  # NEW
├── UpdateSegmentContentController.php                  # NEW
├── DeleteSegmentContentController.php                  # NEW
├── ReorderSegmentContentsController.php                # NEW (replaces ReorderSegmentQuestionsController; that file is deleted)
└── ReorderLessonSegmentsController.php                 # KEPT

app/Http/Requests/SabbathSchool/                        # public
├── ListSabbathSchoolTrimestersRequest.php              # NEW
├── ShowSabbathSchoolTrimesterRequest.php               # NEW (no body — language via middleware)
├── ListSabbathSchoolLessonsRequest.php                 # EVOLVED — adds trimester/age_group nullable rules + accessor methods
├── ShowSabbathSchoolLessonRequest.php                  # KEPT
├── ListSabbathSchoolHighlightsRequest.php              # KEPT
├── ToggleSabbathSchoolHighlightRequest.php             # EVOLVED — segment_content_id + start_position + end_position + color
├── PatchSabbathSchoolHighlightRequest.php              # NEW — color only
├── ToggleSabbathSchoolFavoriteRequest.php              # EVOLVED — segment_id nullable
├── ListSabbathSchoolFavoritesRequest.php               # KEPT
├── ShowSabbathSchoolAnswerRequest.php                  # EVOLVED — bound to {content}; authorize() checks user
├── UpsertSabbathSchoolAnswerRequest.php                # EVOLVED — content + bound to {content}
└── DeleteSabbathSchoolAnswerRequest.php                # EVOLVED — bound to {content}

app/Http/Requests/Admin/SabbathSchool/                  # admin (NEW directory)
├── ListAdminTrimestersRequest.php                      # NEW
├── CreateTrimesterRequest.php                          # NEW
├── UpdateTrimesterRequest.php                          # NEW
├── DeleteTrimesterRequest.php                          # NEW (empty body — gate via admin mw)
├── ListAdminLessonsRequest.php                         # NEW
├── CreateLessonRequest.php                             # NEW
├── UpdateLessonRequest.php                             # NEW
├── DeleteLessonRequest.php                             # NEW
├── ListSegmentContentsRequest.php                      # NEW
├── CreateSegmentContentRequest.php                     # NEW
├── UpdateSegmentContentRequest.php                     # NEW
└── DeleteSegmentContentRequest.php                     # NEW

app/Http/Resources/SabbathSchool/
├── SabbathSchoolTrimesterResource.php                  # NEW — id/year/language/age_group/title/number/date_from/date_to/image_cdn_url; whenLoaded(lessons) → SabbathSchoolLessonSummaryResource collection
├── SabbathSchoolTrimesterSummaryResource.php           # NEW (used inside lesson detail when nested)
├── SabbathSchoolLessonResource.php                     # EVOLVED — adds trimester_id, age_group, memory_verse, image_cdn_url, number, date_from/date_to (deprecates week_start/week_end), nests segments → contents[] (legacy content fallback)
├── SabbathSchoolLessonSummaryResource.php              # EVOLVED — adds age_group, number, image_cdn_url, date_from/date_to
├── SabbathSchoolSegmentResource.php                    # EVOLVED — adds for_date (preferred) + day (deprecated); nests contents[] from new model; preserves content/passages legacy
├── SabbathSchoolSegmentContentResource.php             # NEW — id/type/title/position/content
├── SabbathSchoolHighlightResource.php                  # EVOLVED — segment_id (kept), segment_content_id, start_position, end_position, color (drops passage)
├── SabbathSchoolFavoriteResource.php                   # EVOLVED — segment_id NULL → whole_lesson true
└── SabbathSchoolAnswerResource.php                     # EVOLVED — segment_content_id replaces sabbath_school_question_id

database/migrations/                                     # NEW (timestamp slice 2026_05_03_002000+ — after MBA-023 ordering)
├── 2026_05_03_002000_create_or_evolve_sabbath_school_trimesters_table.php
├── 2026_05_03_002001_evolve_sabbath_school_lessons_for_trimester_and_metadata.php
├── 2026_05_03_002002_evolve_sabbath_school_segments_for_for_date.php
├── 2026_05_03_002003_create_or_evolve_sabbath_school_segment_contents_table.php
├── 2026_05_03_002004_rewire_sabbath_school_answers_to_segment_contents.php
├── 2026_05_03_002005_evolve_sabbath_school_highlights_for_offsets.php
├── 2026_05_03_002006_create_sabbath_school_highlights_legacy_table.php
└── 2026_05_03_002007_relax_sabbath_school_favorites_segment_uniqueness.php

database/factories/
├── SabbathSchoolTrimesterFactory.php                   # NEW
├── SabbathSchoolSegmentContentFactory.php              # NEW (states: text(), question(), memoryVerse(), atPosition(int), forSegment(SabbathSchoolSegment))
├── SabbathSchoolLessonFactory.php                      # EVOLVED — adds age_group, number, date_from/date_to (alongside week_start/week_end during the rollout)
├── SabbathSchoolSegmentFactory.php                     # EVOLVED — adds for_date; keeps day for back-compat
├── SabbathSchoolHighlightFactory.php                   # EVOLVED — drops passage; adds segment_content_id, start, end, color (default "#FFEB3B")
├── SabbathSchoolAnswerFactory.php                      # EVOLVED — references segment_content via factory()
└── SabbathSchoolFavoriteFactory.php                    # EVOLVED — wholeLesson() uses NULL; forSegment() asserts non-null
```

## Schema changes

| Table | Change | Notes |
|---|---|---|
| `sabbath_school_trimesters` | create-if-missing OR rename-cols-if-present | Final cols: `id`, `year VARCHAR(4)`, `language CHAR(2)`, `age_group VARCHAR(50)`, `title VARCHAR(128)`, `number SMALLINT`, `date_from DATE`, `date_to DATE`, `image_cdn_url TEXT NULL`, timestamps. Add UNIQUE `(language, age_group, date_from, date_to)`. |
| `sabbath_school_lessons` | rename `lesson_id`→ — n/a (no FK rename); rename `week_start`→`date_from`, `week_end`→`date_to` (Symfony parity) | Use `ReconcileTableHelper::renameColumnIfPresent`; idempotent both ways. |
| `sabbath_school_lessons` | + `trimester_id BIGINT UNSIGNED NULL` FK → `sabbath_school_trimesters(id)` ON DELETE CASCADE | Indexed. |
| `sabbath_school_lessons` | + `age_group VARCHAR(50) NULL` initially, backfill `'adult'` for legacy rows, then `change()` to NOT NULL | Backfill via `DB::table('sabbath_school_lessons')->whereNull('age_group')->update(...)` inside the same migration. |
| `sabbath_school_lessons` | + `memory_verse TEXT NULL` | |
| `sabbath_school_lessons` | + `image_cdn_url TEXT NULL` | |
| `sabbath_school_lessons` | + `number SMALLINT UNSIGNED NULL`, backfill via row-number per `(language, age_group, trimester_id, year)` ordered by `date_from`, then `change()` NOT NULL | Order ranking ensures determinism in fresh-dev rebuilds. |
| `sabbath_school_lessons` | + UNIQUE `(language, age_group, trimester_id, date_from, date_to)` named `sabbath_school_lessons_lesson_unique` | Re-asserts Symfony `lesson_unique`. Owned by this story per MBA-023's note. |
| `sabbath_school_segments` | rename `lesson_id`→`sabbath_school_lesson_id` (Symfony→Laravel direction; idempotent) | |
| `sabbath_school_segments` | + `for_date DATE NULL`, backfill from `lesson.date_from + day days` for non-null `day` rows | |
| `sabbath_school_segments` | `day → unsignedTinyInteger NULL` (relax NOT NULL) | Symfony does not have `day`; production rename leaves the column absent — add it nullable so the model still works. |
| `sabbath_school_segments` | + `passages JSON NULL` if missing | Production lacks the column post-rename; add nullable for back-compat. Future story may drop. |
| `sabbath_school_segment_contents` | create-if-missing OR rename `section_id`→`segment_id` if present | Final cols: `id`, `segment_id BIGINT UNSIGNED NOT NULL` FK → `sabbath_school_segments(id)` ON DELETE CASCADE, `type VARCHAR(50) NOT NULL`, `title VARCHAR(128) NULL`, `position SMALLINT UNSIGNED NOT NULL DEFAULT 0`, `content LONGTEXT NOT NULL`, timestamps. Index `(segment_id, position)`. |
| `sabbath_school_answers` | drop FK on legacy column (whichever exists) | Pre-condition for safe rename; helper `Schema::hasColumn` gates each branch. |
| `sabbath_school_answers` | rename `sabbath_school_question_id`→`segment_content_id` (dev) **or** `question_id`→`segment_content_id` (prod) | `ReconcileTableHelper::renameColumnIfPresent` covers both. |
| `sabbath_school_answers` | column type re-aligned to `BIGINT UNSIGNED NULL` | Nullable through MBA-031 ETL window; MBA-032 flips NOT NULL. |
| `sabbath_school_answers` | drop legacy unique `(user_id, sabbath_school_question_id)`; add unique `(user_id, segment_content_id)` | Old unique referenced the renamed column anyway; drop+recreate is safer for cross-env idempotency. |
| `sabbath_school_answers` | (no FK reintroduced this story) | MBA-031 backfills, MBA-032 re-adds the FK to `sabbath_school_segment_contents(id)` ON DELETE CASCADE. Tracked in deferred extractions tripwire. |
| `sabbath_school_highlights` | + `segment_content_id BIGINT UNSIGNED NULL` FK → `sabbath_school_segment_contents(id)` ON DELETE CASCADE | Nullable through ETL window. |
| `sabbath_school_highlights` | + `start_position INT UNSIGNED NULL`, + `end_position INT UNSIGNED NULL`, + `color VARCHAR(9) NULL` | Nullable; new write path requires non-null at API layer. |
| `sabbath_school_highlights` | `passage VARCHAR(255) NULL` (relax NOT NULL) | Kept for ETL; MBA-032 drops. |
| `sabbath_school_highlights` | + UNIQUE `(user_id, segment_content_id, start_position, end_position)` named `sabbath_school_highlights_user_content_range_unique` | Tolerates NULLs (existing pre-ETL rows coexist). |
| `sabbath_school_highlights` | + INDEX `(segment_content_id)` | Hot path for list-by-segment join. |
| `sabbath_school_highlights_legacy` | NEW table mirror of `sabbath_school_highlights` pre-reshape | Cols: `id`, `user_id` (unsigned int), `sabbath_school_segment_id BIGINT UNSIGNED`, `passage VARCHAR(255)`, `created_at`, `archived_at`. Empty at end of this story; populated by MBA-031 for unparseable rows. |
| `sabbath_school_favorites` | `sabbath_school_segment_id` → `BIGINT UNSIGNED NULL` (relax NOT NULL, drop default 0) | |
| `sabbath_school_favorites` | UPDATE rows where `sabbath_school_segment_id = 0` SET = NULL | One-shot inside the same migration. |
| `sabbath_school_favorites` | drop unique `sabbath_school_favorites_user_lesson_segment_unique`; add functional unique `(user_id, sabbath_school_lesson_id, COALESCE(sabbath_school_segment_id, 0))` named `sabbath_school_favorites_user_lesson_seg_func_unique` | MySQL 8 functional index — collapses NULL to 0 for uniqueness without restoring sentinel data. Raw SQL inside `DB::statement` because Blueprint doesn't expose functional indexes natively. |

## HTTP endpoints

| Verb | Path | Controller | Request | Resource | Auth / middleware |
|---|---|---|---|---|---|
| GET | `/api/v1/sabbath-school/trimesters` | `ListSabbathSchoolTrimestersController` | `ListSabbathSchoolTrimestersRequest` | `SabbathSchoolTrimesterResource::collection` | `api-key-or-sanctum` + `resolve-language` + `throttle:public-anon` + `cache.headers:public;max_age=3600;etag` |
| GET | `/api/v1/sabbath-school/trimesters/{trimester}` | `ShowSabbathSchoolTrimesterController` | `ShowSabbathSchoolTrimesterRequest` | `SabbathSchoolTrimesterResource` (with `lessons`) | same as above |
| GET | `/api/v1/sabbath-school/lessons` | `ListSabbathSchoolLessonsController` (existing) | `ListSabbathSchoolLessonsRequest` (extended: `?trimester=`, `?age_group=`) | paginated `SabbathSchoolLessonSummaryResource` | unchanged |
| GET | `/api/v1/sabbath-school/lessons/{lesson}` | `ShowSabbathSchoolLessonController` (existing) | `ShowSabbathSchoolLessonRequest` | `SabbathSchoolLessonResource` (segments → contents[] with content fallback) | unchanged |
| GET | `/api/v1/sabbath-school/highlights?segment_id=` | `ListSabbathSchoolHighlightsController` (existing) | `ListSabbathSchoolHighlightsRequest` | `SabbathSchoolHighlightResource::collection` | `auth:sanctum` + `throttle:per-user` |
| POST | `/api/v1/sabbath-school/highlights/toggle` | `ToggleSabbathSchoolHighlightController` (evolved) | `ToggleSabbathSchoolHighlightRequest` (new body) | `SabbathSchoolHighlightResource` or `{deleted:true}` | same |
| PATCH | `/api/v1/sabbath-school/highlights/{highlight}` | `PatchSabbathSchoolHighlightController` | `PatchSabbathSchoolHighlightRequest` (color only) | `SabbathSchoolHighlightResource` | same; route-model binding scoped to caller (`->whereUserId($userId)` inside `resolveRouteBinding`) |
| GET | `/api/v1/sabbath-school/favorites` | `ListSabbathSchoolFavoritesController` (existing) | `ListSabbathSchoolFavoritesRequest` | `SabbathSchoolFavoriteResource::collection` | unchanged |
| POST | `/api/v1/sabbath-school/favorites/toggle` | `ToggleSabbathSchoolFavoriteController` (evolved) | `ToggleSabbathSchoolFavoriteRequest` (segment_id nullable) | `SabbathSchoolFavoriteResource` or `{deleted:true}` | same |
| GET | `/api/v1/sabbath-school/segment-contents/{content}/answer` | `ShowSabbathSchoolAnswerController` (rebound) | `ShowSabbathSchoolAnswerRequest` | `SabbathSchoolAnswerResource` or 204 | `auth:sanctum` |
| POST | `/api/v1/sabbath-school/segment-contents/{content}/answer` | `UpsertSabbathSchoolAnswerController` | `UpsertSabbathSchoolAnswerRequest` | `SabbathSchoolAnswerResource` (201/200) | same |
| DELETE | `/api/v1/sabbath-school/segment-contents/{content}/answer` | `DeleteSabbathSchoolAnswerController` | `DeleteSabbathSchoolAnswerRequest` | 204 | same |
| GET | `/api/v1/admin/sabbath-school/trimesters` | `ListAdminTrimestersController` | `ListAdminTrimestersRequest` (?language=) | `SabbathSchoolTrimesterResource::collection` | `auth:sanctum` + `admin` |
| POST | `/api/v1/admin/sabbath-school/trimesters` | `CreateTrimesterController` | `CreateTrimesterRequest` | `SabbathSchoolTrimesterResource` (201) | same |
| PATCH | `/api/v1/admin/sabbath-school/trimesters/{trimester}` | `UpdateTrimesterController` | `UpdateTrimesterRequest` | `SabbathSchoolTrimesterResource` | same |
| DELETE | `/api/v1/admin/sabbath-school/trimesters/{trimester}` | `DeleteTrimesterController` | `DeleteTrimesterRequest` | 204 | same |
| GET | `/api/v1/admin/sabbath-school/lessons` | `ListAdminLessonsController` | `ListAdminLessonsRequest` (?language=&trimester=&age_group=&published=) | paginated `SabbathSchoolLessonSummaryResource` | same |
| POST | `/api/v1/admin/sabbath-school/lessons` | `CreateLessonController` | `CreateLessonRequest` | `SabbathSchoolLessonResource` (201) | same |
| PATCH | `/api/v1/admin/sabbath-school/lessons/{lesson}` | `UpdateLessonController` | `UpdateLessonRequest` | `SabbathSchoolLessonResource` | same; admin binding bypasses `published()` (see binding strategy below) |
| DELETE | `/api/v1/admin/sabbath-school/lessons/{lesson}` | `DeleteLessonController` | `DeleteLessonRequest` | 204 | same |
| POST | `/api/v1/admin/sabbath-school/lessons/{lesson}/segments/reorder` | `ReorderLessonSegmentsController` (existing) | `ReorderRequest` | `{message: "Reordered."}` | same; lesson binding bypasses `published()` |
| GET | `/api/v1/admin/sabbath-school/segments/{segment}/contents` | `ListSegmentContentsController` | `ListSegmentContentsRequest` | `SabbathSchoolSegmentContentResource::collection` | same |
| POST | `/api/v1/admin/sabbath-school/segments/{segment}/contents` | `CreateSegmentContentController` | `CreateSegmentContentRequest` | `SabbathSchoolSegmentContentResource` (201) | same |
| PATCH | `/api/v1/admin/sabbath-school/segment-contents/{content}` | `UpdateSegmentContentController` | `UpdateSegmentContentRequest` | `SabbathSchoolSegmentContentResource` | same |
| DELETE | `/api/v1/admin/sabbath-school/segment-contents/{content}` | `DeleteSegmentContentController` | `DeleteSegmentContentRequest` | 204 | same |
| POST | `/api/v1/admin/sabbath-school/segments/{segment}/contents/reorder` | `ReorderSegmentContentsController` | `ReorderRequest` | `{message: "Reordered."}` | same |
| ~~POST~~ | ~~`/api/v1/admin/sabbath-school/segments/{segment}/questions/reorder`~~ | **REMOVED** | — | — | retired per AC §24 |
| ~~GET/POST/DELETE~~ | ~~`/api/v1/sabbath-school/questions/{question}/answer`~~ | **REMOVED** | — | — | replaced by `segment-contents/{content}/answer` per resolution §8 |

**Route-model binding strategy** (per project guidelines §5b):

- `SabbathSchoolLesson::resolveRouteBinding($value, $field)` already
  applies `published() + withLessonDetail()` for the public endpoint.
  Admin routes bind via a separate guard: register the admin route
  group with `Route::scopeBindings()` disabled and use
  `->withoutScopedBindings()->where('lesson', '[0-9]+')`, then resolve
  via `SabbathSchoolLesson::query()->withoutGlobalScopes()->findOrFail`
  in the controller. *Better*: override
  `resolveRouteBinding($value, $field)` to inspect
  `request()->routeIs('admin.*')` and skip `published()` when the
  caller is on an admin route — this mirrors MBA-024's `Commentary`
  precedent (slug-only `published()`). Pick the route-name guard
  variant; same lesson model serves both routes without a parallel
  class.
- `SabbathSchoolTrimester::resolveRouteBinding` — no draft state, so
  default implicit binding suffices on both public and admin sides.
- `SabbathSchoolSegmentContent::resolveRouteBinding` — no
  visibility scoping; default binding suffices. Admin update/delete
  uses bare `{content}`. Public answer routes also bind on `{content}`
  but Form Request additionally validates `content.type === 'question'`
  and 422s otherwise.
- `SabbathSchoolHighlight::resolveRouteBinding` — override to scope
  by `where('user_id', request()->user()?->id)` so cross-user PATCH
  attempts 404 (not 403 — the row is invisible to the caller).

## Tasks

- [x] 1. Write `2026_05_03_002000_create_or_evolve_sabbath_school_trimesters_table.php` — branch on `Schema::hasTable`; create with full schema (id/year VARCHAR(4)/language CHAR(2)/age_group VARCHAR(50)/title VARCHAR(128)/number SMALLINT/date_from DATE/date_to DATE/image_cdn_url TEXT NULL/timestamps + UNIQUE), or `Schema::table` to add any missing columns + the UNIQUE.
- [x] 2. Write `2026_05_03_002001_evolve_sabbath_school_lessons_for_trimester_and_metadata.php` — `renameColumnIfPresent` for `week_start↔date_from` / `week_end↔date_to`; add `trimester_id` FK, `age_group` (NULL → backfill `'adult'` → NOT NULL), `memory_verse`, `image_cdn_url`, `number` (NULL → backfill via per-tuple row-number → NOT NULL); add UNIQUE `(language, age_group, trimester_id, date_from, date_to)`.
- [x] 3. Write `2026_05_03_002002_evolve_sabbath_school_segments_for_for_date.php` — `renameColumnIfPresent('sabbath_school_segments', 'lesson_id', 'sabbath_school_lesson_id')`; add `for_date DATE NULL`, `day TINYINT UNSIGNED NULL` (if absent), `passages JSON NULL` (if absent); backfill `for_date = lesson.date_from + day days` via correlated UPDATE for non-null `day`; relax `day` to nullable.
- [x] 4. Write `2026_05_03_002003_create_or_evolve_sabbath_school_segment_contents_table.php` — branch on `Schema::hasTable`; create with full schema (id, segment_id BIGINT UNSIGNED FK CASCADE, type VARCHAR(50), title VARCHAR(128) NULL, position SMALLINT UNSIGNED, content LONGTEXT, timestamps + index `(segment_id, position)`), or `renameColumnIfPresent('section_id'→'segment_id')` then add any missing columns + index.
- [x] 5. Write `2026_05_03_002004_rewire_sabbath_school_answers_to_segment_contents.php` — drop legacy FK on whichever column exists; `renameColumnIfPresent` covers both `sabbath_school_question_id→segment_content_id` and `question_id→segment_content_id`; widen to `BIGINT UNSIGNED NULL`; drop legacy unique `sabbath_school_answers_user_question_unique`; add unique `(user_id, segment_content_id)` named `sabbath_school_answers_user_content_unique`. **Do not** re-add a FK on `segment_content_id` (deferred to MBA-032).
- [x] 6. Write `2026_05_03_002005_evolve_sabbath_school_highlights_for_offsets.php` — add `segment_content_id BIGINT UNSIGNED NULL` FK CASCADE, `start_position INT UNSIGNED NULL`, `end_position INT UNSIGNED NULL`, `color VARCHAR(9) NULL`; relax `passage` to nullable; add UNIQUE `(user_id, segment_content_id, start_position, end_position)` named `sabbath_school_highlights_user_content_range_unique`; add INDEX `(segment_content_id)`.
- [x] 7. Write `2026_05_03_002006_create_sabbath_school_highlights_legacy_table.php` — empty table mirror of pre-reshape highlights schema (id, user_id INT UNSIGNED, sabbath_school_segment_id BIGINT UNSIGNED, passage VARCHAR(255), created_at, archived_at); index `(user_id)`. Migration is reversible (drop on down).
- [x] 8. Write `2026_05_03_002007_relax_sabbath_school_favorites_segment_uniqueness.php` — relax `sabbath_school_segment_id` to `BIGINT UNSIGNED NULL` and drop default; `UPDATE … SET sabbath_school_segment_id = NULL WHERE sabbath_school_segment_id = 0`; drop existing 3-col unique; add functional unique via `DB::statement` over `(user_id, sabbath_school_lesson_id, COALESCE(sabbath_school_segment_id, 0))`.
- [x] 9. Add `App\Domain\SabbathSchool\Support\SegmentContentType` enum (`text`, `question`, `memory_verse`, `passage`, `prayer`, `discussion`, `summary`) with `values(): array<string>` helper for Form Request `Rule::in`.
- [x] 10. Add `SabbathSchoolTrimester` model (cast `year`/`age_group` to string, `number` to int, `date_from`/`date_to` to date), `lessons()` HasMany ordered by `date_from ASC`, `newEloquentBuilder()` returns custom QB.
- [x] 11. Add `SabbathSchoolTrimesterQueryBuilder` with `forLanguage(Language)` and `withLessons()` (eager-load `lessons` ordered by `date_from`).
- [x] 12. Add `SabbathSchoolSegmentContent` model (cast `position` to int, `type` to enum-string, `segment()` belongsTo); attach factory.
- [x] 13. Evolve `SabbathSchoolLesson` model — add `belongsTo(SabbathSchoolTrimester::class, 'trimester_id')`; cast `date_from`/`date_to` (replacing `week_start`/`week_end` as primary, keep accessors that proxy if needed); add `age_group`/`number`/`memory_verse`/`image_cdn_url` properties; **update** `resolveRouteBinding` to skip `published()` when `request()->routeIs('*admin*')`.
- [x] 14. Evolve `SabbathSchoolSegment` model — add `for_date` cast to date; add `segmentContents()` HasMany ordered by `position`; relation alias for the deprecated `questions()` is **removed** (no longer used after AC §24 retirement).
- [x] 15. Evolve `SabbathSchoolHighlight` model — drop `passage` from `@property` block, add `segment_content_id`/`start_position`/`end_position`/`color`; add `segmentContent()` belongsTo; override `resolveRouteBinding` to scope by `request()->user()?->id`.
- [x] 16. Evolve `SabbathSchoolFavorite` model — drop sentinel reference; cast `sabbath_school_segment_id` to nullable int; remove `SabbathSchoolFavoriteSentinel` import.
- [x] 17. Evolve `SabbathSchoolAnswer` model — replace `sabbath_school_question_id` property with `segment_content_id`; rename `question()` to `segmentContent()` (belongsTo `SabbathSchoolSegmentContent`).
- [x] 18. Delete `SabbathSchoolFavoriteSentinel` and `InvalidSabbathSchoolPassageException` classes; remove their imports across the codebase.
- [x] 19. Evolve `SabbathSchoolLessonQueryBuilder` — `withLessonDetail()` switches eager-load to `segments.segmentContents` (was `segments.questions`); add `forTrimester(int $trimesterId)` and `forAgeGroup(string $ageGroup)` consumed by §31 and §32.
- [x] 20. Evolve `SabbathSchoolHighlightQueryBuilder` — replace `forPassage(string)` with `forContentRange(int $segmentContentId, int $start, int $end)`; replace `forSegment(int $segmentId)` to JOIN `segment_contents` (`->join('sabbath_school_segment_contents as ssc', 'ssc.id', '=', 'sabbath_school_highlights.segment_content_id')->where('ssc.segment_id', $segmentId)`); add `migrated()` (`whereNotNull('segment_content_id')`); add `forSegmentContent(int)`. Used by §44, §45, §46.
- [x] 21. Evolve `SabbathSchoolFavoriteQueryBuilder` — `forLessonAndSegment(int $lessonId, ?int $segmentId)`: when null, scope `whereNull('sabbath_school_segment_id')`; when non-null, scope `where('sabbath_school_segment_id', $segmentId)`. Add `forWholeLesson(int)` consumed by §41.
- [x] 22. Evolve `SabbathSchoolAnswerQueryBuilder` — `forSegmentContent(int $contentId)` replaces `forQuestion(int)`. Used by §47, §48.
- [x] 23. Evolve `SabbathSchoolCacheKeys` — add `trimestersList(Language $language)`, `tagsForTrimestersList()`, `trimester(int $id, Language $language)`, `tagsForTrimester(int)`; extend `lessonsList` key to include `trimesterId` + `ageGroup` so filtered list responses don't collide. Update `tagsForLessonsList` to add `ss:trimesters` so trimester writes can invalidate.
- [x] 24. Add `SabbathSchoolTrimesterFactory` (year random `Y`, language En, age_group default `'adult'`, title sentence, number 1–13, date_from random Sunday in window, date_to date_from + 90 days, image_cdn_url null) with `forLanguage(Language)` / `forAgeGroup(string)` / `withImage()` states.
- [x] 25. Add `SabbathSchoolSegmentContentFactory` — defaults: type='text', title null, position 0, content `<p>{paragraph}</p>`; states `text()`, `question(string $prompt = null)` (sets type + content), `memoryVerse()`, `forSegment(SabbathSchoolSegment)`, `atPosition(int)`.
- [x] 26. Update `SabbathSchoolLessonFactory` — add `age_group` (default `'adult'`), `number` (default 1), `date_from` mirroring `week_start`, `date_to` mirroring `week_end`; add states `forTrimester(SabbathSchoolTrimester)`, `forAgeGroup(string)`, `numbered(int)`. Keep `week_start`/`week_end` populated through the rollout window so any test still using them passes.
- [x] 27. Update `SabbathSchoolSegmentFactory` — add `for_date` (default = lesson.week_start + day days), `day` becomes nullable (still defaults). State `forDate(CarbonImmutable)` lets tests author Symfony-style segments without `day`.
- [x] 28. Update `SabbathSchoolHighlightFactory` — drop `passage` default, add `segment_content_id` via `SabbathSchoolSegmentContent::factory()->question()`, `start_position` 0, `end_position` 16, `color` `'#FFEB3B'`; states `forSegmentContent(SabbathSchoolSegmentContent)` and `withColor(string)`. `passage` left as null by default so legacy column stays empty in fresh tests.
- [x] 29. Update `SabbathSchoolAnswerFactory` — replace `sabbath_school_question_id` default with `segment_content_id` via `SabbathSchoolSegmentContent::factory()->question()`; state `forSegmentContent(SabbathSchoolSegmentContent)`.
- [x] 30. Update `SabbathSchoolFavoriteFactory` — default `sabbath_school_segment_id` to NULL (whole lesson); add states `wholeLesson()` (NULL) and `forSegment(SabbathSchoolSegment)` (non-null id). Remove sentinel import.
- [x] 31. Add `TrimesterData`, `LessonData`, `SegmentContentData` readonly DTOs with public-property constructors and `static fromRequest(FormRequest)` factory methods.
- [x] 32. Implement `CreateTrimesterAction`, `UpdateTrimesterAction`, `DeleteTrimesterAction` (each `final class` with `execute(...)` returning the model or void). On write, flush trimester list cache via `Cache::tags(SabbathSchoolCacheKeys::tagsForTrimestersList())->flush()` (use existing `CachedRead` invalidation precedent).
- [x] 33. Implement `ListTrimestersAction` (cached, language-scoped) and `ShowTrimesterAction` (cached, eager-loads lessons via `withLessons()`); both follow `ListSabbathSchoolLessonsAction` precedent (use `CachedRead`, key from `SabbathSchoolCacheKeys`).
- [x] 34. Implement `CreateLessonAction`, `UpdateLessonAction`, `DeleteLessonAction` mirroring trimester actions; flush both lesson-list and lesson-detail tags on write.
- [x] 35. Implement `CreateSegmentContentAction`, `UpdateSegmentContentAction`, `DeleteSegmentContentAction` (final classes with `execute(...)` taking the parent segment for create, the content for update/delete; flush parent lesson cache tag on write).
- [x] 36. Implement `ReorderSegmentContentsAction` mirroring `ReorderLessonSegmentsAction`: assert every `id` belongs to `(segment_id)`; transaction-wrapped position update.
- [x] 37. Evolve `ListSabbathSchoolLessonsAction` — accept optional `?int $trimesterId` and `?string $ageGroup`; thread into both the cache key and the QueryBuilder via new scopes; preserve language/page/per-page semantics.
- [x] 38. Evolve `ShowSabbathSchoolLessonAction` — `withLessonDetail()` now eager-loads `segments.segmentContents`. Resource fallback (segment.content text when contents[] empty) lives in the Resource toArray, not the Action — Action just hands off the eager-loaded model.
- [x] 39. Evolve `ToggleSabbathSchoolHighlightAction` — drop `ReferenceParser` constructor dep; signature `execute(ToggleSabbathSchoolHighlightData)`; lookup is `forUser($user)->forContentRange($segmentContentId, $start, $end)->lockForUpdate()->first()`; identical → `delete()` (soft) → `ToggleResult::deleted()`; otherwise `create([...])` with `passage = null` (legacy column).
- [x] 40. Implement `PatchSabbathSchoolHighlightColorAction` — `execute(SabbathSchoolHighlight $h, string $color): SabbathSchoolHighlight` updates `color` only and returns the fresh model.
- [x] 41. Evolve `ToggleSabbathSchoolFavoriteAction` — `segmentId` becomes `?int`; lookup uses `forUser($user)->forLessonAndSegment($lessonId, $segmentId)->withTrashed()->lockForUpdate()->first()`; toggle semantics unchanged (live → soft-delete; soft-deleted → restore + touch; missing → create); creates persist NULL when `segmentId === null`.
- [x] 42. Evolve `UpsertSabbathSchoolAnswerAction` — `data->segmentContent` replaces `data->question`; lookup via `forUser($user)->forSegmentContent($id)`; create persists `segment_content_id`. Same for `DeleteSabbathSchoolAnswerAction`.
- [x] 43. Implement `SabbathSchoolTrimesterResource` (id/year/language/age_group/title/number/date_from/date_to/image_cdn_url, `lessons` via `whenLoaded` → `SabbathSchoolLessonSummaryResource::collection`) and `SabbathSchoolTrimesterSummaryResource` (drops nested lessons; used inside lesson detail when nested).
- [x] 44. Evolve `SabbathSchoolLessonResource` — add `trimester_id`, `age_group`, `memory_verse`, `image_cdn_url`, `number`, `date_from`, `date_to`; keep `week_start`/`week_end` keys aliased to `date_from`/`date_to` for the rollout window with a `// TODO MBA-032: drop` inline comment; segments collection unchanged but its Resource shape evolves.
- [x] 45. Evolve `SabbathSchoolSegmentResource` — add `for_date` (preferred) and keep `day` (deprecated); add `contents` via `SabbathSchoolSegmentContentResource::collection($this->whenLoaded('segmentContents'))`; preserve `content` and `passages` as legacy fields surfaced only when `contents[]` is empty (Resource fallback per AC §13).
- [x] 46. Add `SabbathSchoolSegmentContentResource` (id/type/title/position/content).
- [x] 47. Evolve `SabbathSchoolHighlightResource` — output `segment_id` (kept), `segment_content_id`, `start_position`, `end_position`, `color`, `created_at`. Drop `passage`.
- [x] 48. Evolve `SabbathSchoolFavoriteResource` — `segment_id` from nullable column; `whole_lesson` true when null. Remove sentinel import.
- [x] 49. Evolve `SabbathSchoolAnswerResource` — replace `sabbath_school_question_id` with `segment_content_id`.
- [x] 50. Evolve `ToggleSabbathSchoolHighlightRequest` — rules: `segment_content_id` required exists; `start_position` required int min 0; `end_position` required int gt:start_position; `color` required regex `/^#[0-9A-Fa-f]{6}([0-9A-Fa-f]{2})?$/`. Replace `toData()` accordingly.
- [x] 51. Add `PatchSabbathSchoolHighlightRequest` — rules: `color` required hex regex. `authorize()` returns `$this->user() !== null` (route binding override already scopes by user). Provides `color()` accessor.
- [x] 52. Evolve `ToggleSabbathSchoolFavoriteRequest` — `segment_id` becomes `nullable, integer, exists:sabbath_school_segments,id`; if non-null, custom rule asserts `segment.sabbath_school_lesson_id === $request->lesson_id` (Form Request layer per existing precedent). Add `toData()` returning `segmentId` as `?int`.
- [x] 53. Update `ListSabbathSchoolLessonsRequest` — add nullable `trimester` (exists), `age_group` (in `Trimester::AGE_GROUPS` enum or simple string), accessors `trimesterId(): ?int`, `ageGroup(): ?string`.
- [x] 54. Rewrite `Show/Upsert/DeleteSabbathSchoolAnswerRequest` — bind to `{content}`; authorize() asserts user; `Upsert` rules: `content` required string min:1; `segmentContent(): SabbathSchoolSegmentContent` accessor returns the route-bound model; `Upsert` Request additionally validates `content.type === 'question'` via `withValidator()` 422 if not.
- [x] 55. Add public Trimester Form Requests (`List`, `Show`) and admin Trimester Form Requests (`List`, `Create`, `Update`, `Delete`); same for admin Lesson and Segment-Content Form Requests. Each in its conventional namespace per Domain layout above.
- [x] 56. Implement public Trimester controllers (`ListSabbathSchoolTrimestersController`, `ShowSabbathSchoolTrimesterController`); both use the resolved language from `ResolveRequestLanguage::ATTRIBUTE_KEY` and call `ListTrimestersAction` / `ShowTrimesterAction`. Public list emits `Cache-Control: public, max-age=3600` per existing precedent.
- [x] 57. Implement admin Trimester controllers (`ListAdmin`, `Create`, `Update`, `Delete`).
- [x] 58. Implement admin Lesson controllers (`ListAdminLessonsController`, `CreateLessonController`, `UpdateLessonController`, `DeleteLessonController`).
- [x] 59. Implement admin Segment-Content controllers (`ListSegmentContentsController`, `CreateSegmentContentController`, `UpdateSegmentContentController`, `DeleteSegmentContentController`, `ReorderSegmentContentsController`). The Reorder controller reuses the existing `App\Http\Requests\Admin\ReorderRequest`.
- [x] 60. Update `ToggleSabbathSchoolHighlightController` for new payload + add `PatchSabbathSchoolHighlightController`. Update `ListSabbathSchoolHighlightsController` to call `migrated()` scope so un-migrated rows are filtered.
- [x] 61. Update `ToggleSabbathSchoolFavoriteController` (no functional change beyond the new DTO accepting nullable segment_id).
- [x] 62. Rebind `Show/Upsert/DeleteSabbathSchoolAnswerController` to receive `SabbathSchoolSegmentContent $content` instead of `SabbathSchoolQuestion $question`; thread into the Action.
- [x] 63. Delete `App\Http\Controllers\Api\V1\Admin\SabbathSchool\ReorderSegmentQuestionsController`, `App\Domain\SabbathSchool\Actions\ReorderSegmentQuestionsAction`, and any tests referencing them. Remove the route.
- [x] 64. Update `routes/api.php`:
  - Remove the `segments/{segment}/questions/reorder` route.
  - Remove the public `questions/{question}/answer` routes.
  - Add admin trimester CRUD inside the existing `Route::prefix('sabbath-school')` admin group.
  - Add admin lesson CRUD (List/Create/Update/Delete) — wrap in `Route::scopeBindings()` for nested ones if any nesting exists; lesson admin routes are flat (`{lesson}` only) so scopeBindings unnecessary.
  - Add admin segment-content endpoints under the same admin SS group; scopeBindings on the `{segment}/contents` nested set.
  - Add public trimester routes inside the existing public `Route::prefix('sabbath-school')` group with the same `api-key-or-sanctum` + `resolve-language` + `throttle:public-anon` middleware stack as lessons.
  - Add new answer routes under the auth:sanctum block: `segment-contents/{content}/answer` Show/Upsert/Delete.
  - Add `PATCH highlights/{highlight}` under the auth:sanctum block.
- [x] 65. Append to `.agent-workflow/CLAUDE.md` "Deferred Extractions Tripwire": one row noting the **post-cutover schema cleanup queue** — `(passage` drop on highlights, `segment_content_id` NOT NULL flip + FK on highlights and answers, `sabbath_school_questions` table drop, `sabbath_school_segments.day` and `passages` drop, `week_start`/`week_end` legacy column drop, sentinel-style functional unique on favorites if a true partial index becomes available)`. Threshold = 0 — this is a note, not a duplication count, but it surfaces in Auditor and Improver passes.
- [x] 66. Add unit test `tests/Unit/Domain/SabbathSchool/Actions/ToggleSabbathSchoolHighlightActionTest.php` covering: identical (user, content, range) → soft-deletes existing; differing range → creates new row; PATCH-only color path is **not** exercised here (covered by the Patch action's own test). Asserts `passage` column stays null on creates.
- [x] 67. Add unit test `tests/Unit/Domain/SabbathSchool/Actions/PatchSabbathSchoolHighlightColorActionTest.php` covering: color updates without touching range; returns refreshed model; rejects via Form Request when caller doesn't own the highlight (covered through controller feature test instead — keep this test pure-Action).
- [x] 68. Add migration test `tests/Feature/Domain/SabbathSchool/Migrations/EvolveSabbathSchoolSegmentsForForDateTest.php` covering: rows with non-null `day` get `for_date = lesson.date_from + day days`; rows with null `day` keep `for_date` null; rerun is idempotent (no-op when `for_date` already set).
- [x] 69. Add migration test `tests/Feature/Domain/SabbathSchool/Migrations/RelaxFavoritesSegmentUniquenessTest.php` covering: rows with `segment_id = 0` become NULL; the functional unique index permits one whole-lesson favorite per `(user, lesson)` and one per `(user, lesson, segment_id)`; both can coexist for the same lesson.
- [x] 70. Add feature tests covering Trimester surface — `tests/Feature/Http/Api/V1/SabbathSchool/ListSabbathSchoolTrimestersTest.php` (anon allowed via api-key, language scoping, ordering `(year DESC, number DESC)`, cache-control header), `ShowSabbathSchoolTrimesterTest.php` (nested lessons present, 404 on unknown id). Same for admin: `tests/Feature/Http/Api/V1/Admin/SabbathSchool/{ListAdminTrimesters,CreateTrimester,UpdateTrimester,DeleteTrimester}Test.php` (401 / 403 / 422 / happy path).
- [x] 71. Add feature tests for admin Lesson CRUD covering 401 / 403 / 422 / happy path; assert new fields surface on the Resource.
- [x] 72. Add feature tests for admin Segment-Content CRUD + reorder (401 / 403 / 422 on bad type / 422 on cross-segment ids / happy path).
- [x] 73. Update `tests/Feature/Http/Api/V1/SabbathSchool/ShowSabbathSchoolLessonTest.php` — assert new lesson fields, assert `segments[].contents[]` shape on lessons that have content rows, assert legacy `segments[].content` fallback for lessons that don't, assert `for_date` and `day` both surface, assert N+1 budget.
- [x] 74. Update existing highlight feature tests for the new Toggle payload + add a test for the new PATCH endpoint (color update happy-path, 404 on cross-user). Update `ListSabbathSchoolHighlightsTest` to assert un-migrated rows are filtered.
- [x] 75. Update existing favourites feature tests for the nullable segment_id contract; assert whole-lesson and per-segment favourites coexist (same `(user, lesson)`).
- [x] 76. Update existing answer feature tests to bind on `{content}`; assert 422 when `{content}` exists but `type !== 'question'`.
- [x] 77. Run `make lint-fix && make stan && make test-api filter=SabbathSchool && make test-api`; confirm full suite passes before handing off.

## Risks & open questions

- **Cross-environment migration drift.** The MBA-023 reconcile only
  ran on production. Fresh dev/CI doesn't see the Symfony column
  names (`section_id`, `date_from`, `for_date`, `lesson_id`). Every
  migration here must be defensive on column names via
  `renameColumnIfPresent`. Engineer should run the full migration
  set against both a fresh DB and a copy of post-MBA-023 prod DDL
  to catch any branch the helper doesn't cover. Worth scripting a
  `make seed-prodlike-schema` if not already present.
- **Number backfill determinism.** `number SMALLINT NOT NULL` is
  backfilled via `ROW_NUMBER() OVER (PARTITION BY language, age_group,
  trimester_id, year ORDER BY date_from)`. If two lessons share an
  identical `date_from` (legacy data quality), ordering is
  non-deterministic and the next run can produce different numbers.
  Mitigation: include `id` as a final tiebreaker in the ORDER BY.
  Document in the migration's PHPDoc.
- **`age_group` default `'adult'` may collide.** Backfilling `age_group
  = 'adult'` for legacy lessons will collide on the new UNIQUE
  `(language, age_group, trimester_id, date_from, date_to)` if two
  pre-existing lessons share `(language, trimester_id, date_from,
  date_to)` because they were originally distinguished by something
  other than age_group (e.g., sub-translation). Mitigation: pre-flight
  check inside the migration — if any duplicate tuple exists after
  backfill, fail with a loud error pointing at MBA-031 (which is
  expected to clean these up first). Engineer should `make tinker`
  against post-MBA-023 prod-shape DB to verify no collisions before
  shipping.
- **Functional unique index on MySQL 8 vs MariaDB.** MariaDB 10.5+
  supports functional indexes via `((expr))` syntax; MySQL 8.0+ uses
  the same. If the prod target turns out to be MariaDB 10.4 or older,
  the favorites unique falls back to two non-functional uniques
  `(user_id, sabbath_school_lesson_id)` and
  `(user_id, sabbath_school_segment_id)` and the application enforces
  the NULL/non-NULL distinction in `ToggleSabbathSchoolFavoriteAction`.
  Engineer must verify prod DB version before shipping.
- **Resource backward-compat surface.** `SabbathSchoolLessonResource`
  surfaces both `week_start`/`date_from` for the rollout window. If
  mobile reads `week_start` and we silently drop it post-MBA-032, a
  stale mobile build breaks. Mitigation: log a deprecation header
  (`Deprecation: week_start`) on the lesson detail response so we can
  alert when older clients still read the legacy field.
- **`SabbathSchoolHighlight` SoftDeletes interaction with new UNIQUE.**
  The new unique `(user_id, segment_content_id, start_position,
  end_position)` includes soft-deleted rows on most MySQL versions
  unless `deleted_at` is in the index. If a user re-creates an
  identical highlight after a soft-delete, the insert collides.
  Mitigation: include `deleted_at` in the unique index, or make
  `Toggle` action restore-on-conflict (cleaner: include `deleted_at`).
  Engineer should add `deleted_at` to the highlight unique columns
  list.

## References

- MBA-023 plan/migrations — `ReconcileTableHelper`, table-rename
  precedent, `BackfillLegacyLanguageCodesAction`.
- MBA-024 plan — public/admin Resource split, slug-vs-id binding
  precedent, evolution-migration sequencing.
- MBA-031 story — owner of the data ETL deferred from this story
  (questions → segment_contents, passage-string → offset highlights).
- MBA-032 story — owner of the post-cutover schema cleanups
  (passage drop, NOT NULL flips, sentinel removals).
- Existing `Admin/SabbathSchool/ReorderLessonSegmentsController` +
  `App\Http\Requests\Admin\ReorderRequest` — reorder pattern.
- Symfony DDL: `sb_trimester`, `sb_lesson`, `sb_section`,
  `sb_content`, `sb_answer`, `sb_favorite`, `sb_highlight` from
  production DDL 2026-05-02.
