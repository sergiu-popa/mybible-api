# Plan: MBA-024-commentary-domain

## Approach

Build a fresh `App\Domain\Commentary` namespace on top of the MBA-023
reconcile (tables already renamed `commentaries` / `commentary_texts`).
Two evolutionary migrations add the publication + translation columns on
`commentaries` and the verse-range columns + USFM widening + indices on
`commentary_texts`, reusing `BackfillLegacyBookAbbreviationsAction` from
MBA-023 and `ReconcileTableHelper::renameColumnIfPresent` to fold the
Symfony `commentary` FK column into `commentary_id`. The HTTP layer
follows the existing public/admin split (precedent: `ReadingPlan`,
`HymnalBook`, `EducationalResource`): public read endpoints scoped by
`ResolveRequestLanguage` and gated by a `resolveRouteBinding()` override
that applies `published()` only when binding `slug`; admin write
endpoints under `/api/v1/admin` gated by the existing `super-admin`
middleware (per AC §20).

## Open questions — resolutions

1. **MBA-023 left `commentary_texts` columns un-renamed.** The reconcile
   migration only renamed the *table*; the FK column is still Symfony's
   `commentary`. AC §2 references `commentary_id` (FK-keyed composite).
   Resolution: this story's `commentary_texts` evolution migration calls
   `ReconcileTableHelper::renameColumnIfPresent('commentary_texts',
   'commentary', 'commentary_id')` first, then proceeds with the widening,
   backfill, and index work. Same migration ensures the FK constraint
   and the legacy `chapter_idx` is dropped before the new composite is
   added.
2. **Slug binding on a model that also supports admin id binding.**
   `Commentary::resolveRouteBinding($value, $field)` applies
   `->published()` only when `$field === 'slug'` (the public
   route-key). Admin routes use `{commentary}` (defaults to `id`) which
   skips the published filter, so admins can fetch and mutate drafts
   without a separate model class. Same pattern recommended in §5b for
   models needing dual public-strict / admin-permissive binding.
3. **Two Resource classes vs. one with `when()`.** Two: `CommentaryResource`
   (public — `slug`, `name`, `abbreviation`, `language`,
   `source_commentary`) and `AdminCommentaryResource` (admin — adds `id`
   and `is_published`). Same split for `CommentaryTextResource` /
   `AdminCommentaryTextResource` (admin adds `id` for PATCH/DELETE
   targeting). Splitting keeps each shape statically inspectable and
   matches the precedent in `EducationalResources` and `SabbathSchool`
   (separate `*ListResource` / `*DetailResource` classes).
4. **Slug namespace is global, not per-language.** AC §1 specifies
   `slug VARCHAR(255) UNIQUE` (no composite with `language`). Operator
   must disambiguate translations explicitly (e.g. `sda` source +
   `sda-ro` translation). Backfill from `Str::slug(strtolower(abbreviation))`
   may collide if two Symfony rows differ only in `language`; the
   migration appends `-{language}` for collisions deterministically.
5. **Reorder body shape.** Position is scoped to `(commentary, book,
   chapter)`, so the body needs `{book, chapter, ids[]}` rather than the
   bare `{ids}` shape used by `ReorderRequest`. Dedicated
   `ReorderCommentaryTextsRequest` accepts the triple; the action
   asserts every id belongs to the (commentary, book, chapter) tuple.
6. **Admin `CommentaryClient` URL drift.** The existing admin client
   (`apps/admin/app/Support/Api/CommentaryClient.php`) addresses
   commentaries by `abbreviation`, expects `/books`, `/check`,
   `/import` paths, and PUT-by-coordinates for verses — none of which
   match this story's AC §11–19. The story is the authority; admin
   client realignment lands in admin MB-017. Not a blocker for this
   story but flagged for the engineer so admin endpoints aren't
   reverse-engineered from the broken client.
7. **`language` widening on `commentaries`.** MBA-023's
   `standardise_language_column_widths` did **not** include `commentaries`
   (verified by reading the migration). This story's commentaries
   migration resizes `language` to `CHAR(2)` itself — fed by MBA-023's
   `BackfillLegacyLanguageCodesAction` which already covers
   `commentaries.language` via the standard target list (engineer must
   add `commentaries` if missing from that list, otherwise resize will
   truncate `ron`/`eng`).
8. **No SoftDeletes on either model.** Symfony has none; story doesn't
   ask for any; admin deletes are hard. Matches `Note`/`Olympiad`
   precedent.

## Domain layout

```
app/Domain/Commentary/
├── Models/
│   ├── Commentary.php                                   # NEW — slug binding + published-on-slug routebinding
│   └── CommentaryText.php                               # NEW
├── QueryBuilders/
│   ├── CommentaryQueryBuilder.php                       # NEW — published(), forLanguage(Language)
│   └── CommentaryTextQueryBuilder.php                   # NEW — forBookChapter(book, chapter), coveringVerse(book, chapter, verse)
├── DataTransferObjects/
│   ├── CommentaryData.php                               # NEW — slug/language/name/abbreviation/source_commentary_id
│   └── CommentaryTextData.php                           # NEW — book/chapter/position/verse_from/verse_to/verse_label/content
└── Actions/
    ├── CreateCommentaryAction.php                       # NEW — handle(CommentaryData): Commentary
    ├── UpdateCommentaryAction.php                       # NEW — handle(Commentary, CommentaryData): Commentary
    ├── SetCommentaryPublicationAction.php               # NEW — handle(Commentary, bool $published): void
    ├── CreateCommentaryTextAction.php                   # NEW — handle(Commentary, CommentaryTextData): CommentaryText
    ├── UpdateCommentaryTextAction.php                   # NEW — handle(CommentaryText, CommentaryTextData): CommentaryText
    ├── DeleteCommentaryTextAction.php                   # NEW — handle(CommentaryText): void
    └── ReorderCommentaryTextsAction.php                 # NEW — handle(Commentary, string $book, int $chapter, list<int> $ids): void

app/Http/Controllers/Api/V1/Commentary/                  # NEW — public namespace
├── ListPublishedCommentariesController.php
├── ListCommentaryChapterTextsController.php
└── ListCommentaryVerseTextsController.php

app/Http/Controllers/Api/V1/Admin/Commentary/            # NEW — admin namespace
├── ListCommentariesController.php
├── CreateCommentaryController.php
├── UpdateCommentaryController.php
├── PublishCommentaryController.php
├── UnpublishCommentaryController.php
├── ListCommentaryTextsController.php
├── CreateCommentaryTextController.php
├── UpdateCommentaryTextController.php
├── DeleteCommentaryTextController.php
└── ReorderCommentaryTextsController.php

app/Http/Requests/Commentary/                            # NEW — public
├── ListPublishedCommentariesRequest.php
├── ListCommentaryChapterTextsRequest.php                # validates {book} USFM-3 + {chapter}
└── ListCommentaryVerseTextsRequest.php                  # validates {book} USFM-3 + {chapter} + {verse}

app/Http/Requests/Admin/Commentary/                      # NEW — admin (every write has its own)
├── ListCommentariesRequest.php
├── CreateCommentaryRequest.php
├── UpdateCommentaryRequest.php
├── PublishCommentaryRequest.php                         # empty body — gate via super-admin mw + authorize() = true
├── UnpublishCommentaryRequest.php
├── ListCommentaryTextsRequest.php                       # validates ?book=&chapter= filters
├── CreateCommentaryTextRequest.php
├── UpdateCommentaryTextRequest.php
├── DeleteCommentaryTextRequest.php
└── ReorderCommentaryTextsRequest.php                    # body: {book, chapter, ids[]}

app/Http/Resources/Commentary/                           # NEW
├── CommentaryResource.php                               # public — slug/name/abbreviation/language/source_commentary
├── AdminCommentaryResource.php                          # admin — adds id, is_published
├── CommentaryTextResource.php                           # public — position/verse_from/verse_to/verse_label/content
└── AdminCommentaryTextResource.php                      # admin — adds id

database/migrations/                                     # NEW (timestamp slice 2026_05_03_001000+ — after MBA-023 backfills)
├── 2026_05_03_001000_add_publication_metadata_to_commentaries_table.php
└── 2026_05_03_001001_evolve_commentary_texts_for_verse_ranges.php

database/factories/                                      # NEW
├── CommentaryFactory.php                                # name array, abbreviation, slug, language, draft state + published()/translationOf() states
└── CommentaryTextFactory.php                            # book/chapter/position with verse_from/verse_to backfilled = position
```

## Schema changes

| Table | Change | Notes |
|---|---|---|
| `commentaries` | `+ slug VARCHAR(255) UNIQUE NOT NULL` | Backfilled from `Str::slug(strtolower(abbreviation))`, suffixed `-{language}` on collision. |
| `commentaries` | `+ is_published BOOLEAN DEFAULT false NOT NULL` | Defaults drafts hidden — operator publishes via dedicated endpoint. |
| `commentaries` | `+ source_commentary_id BIGINT UNSIGNED NULLABLE` | FK → `commentaries.id` ON DELETE SET NULL. Indexed. |
| `commentaries` | `language → CHAR(2)` | Resize after MBA-023 backfill widens `commentaries` to its target list (engineer extends if missing). |
| `commentary_texts` | rename `commentary → commentary_id` | Via `ReconcileTableHelper::renameColumnIfPresent`. FK re-asserted. |
| `commentary_texts` | `book → VARCHAR(8)` + USFM-3 backfill | Reuses `BackfillLegacyBookAbbreviationsAction`. |
| `commentary_texts` | `+ verse_from SMALLINT UNSIGNED NULLABLE` | Backfill `= position`. |
| `commentary_texts` | `+ verse_to SMALLINT UNSIGNED NULLABLE` | Backfill `= position`. |
| `commentary_texts` | `+ verse_label VARCHAR(20) NULLABLE` | Backfill `= position::TEXT`. |
| `commentary_texts` | drop legacy `chapter_idx` | If present after rename. |
| `commentary_texts` | `+ UNIQUE (commentary_id, book, chapter, position)` | Replaces Symfony `commentary_text_unique`. |
| `commentary_texts` | `+ INDEX (commentary_id, book, chapter, verse_from, verse_to)` | Hot path for `coveringVerse()`. |

## HTTP endpoints

| Verb | Path | Controller | Request | Resource | Auth |
|---|---|---|---|---|---|
| GET | `/api/v1/commentaries` | `ListPublishedCommentariesController` | `ListPublishedCommentariesRequest` | `CommentaryResource::collection` | `api-key-or-sanctum` + `resolve-language` + `cache.headers:public;max_age=3600;etag` |
| GET | `/api/v1/commentaries/{commentary:slug}/{book}/{chapter}` | `ListCommentaryChapterTextsController` | `ListCommentaryChapterTextsRequest` | `CommentaryTextResource::collection` | same + `cache.headers:public;max_age=3600;etag` |
| GET | `/api/v1/commentaries/{commentary:slug}/{book}/{chapter}/{verse}` | `ListCommentaryVerseTextsController` | `ListCommentaryVerseTextsRequest` | `CommentaryTextResource::collection` | same |
| GET | `/api/v1/admin/commentaries` | `ListCommentariesController` | `ListCommentariesRequest` | `AdminCommentaryResource::collection` | `auth:sanctum` + `super-admin` |
| POST | `/api/v1/admin/commentaries` | `CreateCommentaryController` | `CreateCommentaryRequest` | `AdminCommentaryResource` | same |
| PATCH | `/api/v1/admin/commentaries/{commentary}` | `UpdateCommentaryController` | `UpdateCommentaryRequest` | `AdminCommentaryResource` | same |
| POST | `/api/v1/admin/commentaries/{commentary}/publish` | `PublishCommentaryController` | `PublishCommentaryRequest` | `AdminCommentaryResource` | same |
| POST | `/api/v1/admin/commentaries/{commentary}/unpublish` | `UnpublishCommentaryController` | `UnpublishCommentaryRequest` | `AdminCommentaryResource` | same |
| GET | `/api/v1/admin/commentaries/{commentary}/texts` | `ListCommentaryTextsController` | `ListCommentaryTextsRequest` | `AdminCommentaryTextResource::collection` | same |
| POST | `/api/v1/admin/commentaries/{commentary}/texts` | `CreateCommentaryTextController` | `CreateCommentaryTextRequest` | `AdminCommentaryTextResource` | same + `scopeBindings` on the group |
| PATCH | `/api/v1/admin/commentaries/{commentary}/texts/{text}` | `UpdateCommentaryTextController` | `UpdateCommentaryTextRequest` | `AdminCommentaryTextResource` | same + `scopeBindings` |
| DELETE | `/api/v1/admin/commentaries/{commentary}/texts/{text}` | `DeleteCommentaryTextController` | `DeleteCommentaryTextRequest` | 204 No Content | same + `scopeBindings` |
| POST | `/api/v1/admin/commentaries/{commentary}/texts/reorder` | `ReorderCommentaryTextsController` | `ReorderCommentaryTextsRequest` | `{ message: "Reordered." }` | same |

Route-model binding strategy: `Commentary::resolveRouteBinding($value,
$field)` applies `published()` when `$field === 'slug'` and skips it
otherwise, so public `{commentary:slug}` routes 404 on drafts while
admin `{commentary}` (id-bound) routes serve drafts. Nested `{text}`
under `{commentary}` is wrapped in `Route::scopeBindings()` so a
`{text}` from another commentary 404s.

## Tasks

- [x] 1. Write `2026_05_03_001000_add_publication_metadata_to_commentaries_table.php` — add `slug` (nullable), `is_published`, `source_commentary_id` (FK SET NULL), resize `language` → `CHAR(2)`; backfill `slug` from `Str::slug(strtolower(abbreviation))` with `-{language}` suffix on collision; alter `slug` NOT NULL UNIQUE.
- [x] 2. Write `2026_05_03_001001_evolve_commentary_texts_for_verse_ranges.php` — rename `commentary` → `commentary_id` via `ReconcileTableHelper::renameColumnIfPresent`; widen `book` to `VARCHAR(8)`; run `BackfillLegacyBookAbbreviationsAction->handle('commentary_texts', 'book')`; add `verse_from`, `verse_to`, `verse_label`; backfill all three from `position`; drop legacy `chapter_idx` if present; add `UNIQUE (commentary_id, book, chapter, position)` and `INDEX (commentary_id, book, chapter, verse_from, verse_to)`; ensure FK on `commentary_id`.
- [x] 3. Add `Commentary` model (`name` array cast, `is_published` bool cast, slug as route key, `sourceCommentary()` belongsTo, `texts()` hasMany ordered by `position`, override `resolveRouteBinding` to apply `published()` only when `$field === 'slug'`).
- [x] 4. Add `CommentaryQueryBuilder` with `published()` (where `is_published = true`) and `forLanguage(Language $language)`.
- [x] 5. Add `CommentaryText` model (cast `verse_from`/`verse_to`/`chapter`/`position` to int, `commentary()` belongsTo) with `CommentaryTextQueryBuilder`.
- [x] 6. Add `CommentaryTextQueryBuilder` with `forBookChapter(string $book, int $chapter)` and `coveringVerse(string $book, int $chapter, int $verse)` (where `verse_from <= $v AND (verse_to IS NULL OR verse_to >= $v)`).
- [x] 7. Add `CommentaryFactory` (with `published()` and `translationOf(Commentary)` states) and `CommentaryTextFactory` (with `verse_from = verse_to = position` defaults).
- [x] 8. Add `CommentaryData` and `CommentaryTextData` readonly DTOs with `fromRequest()` / `from(array)` constructors.
- [x] 9. Add `CommentaryResource` (public — `slug`, `name` resolved via `LanguageResolver`, `abbreviation`, `language`, `source_commentary` nested when present) and `AdminCommentaryResource` (extends shape with `id`, `is_published`).
- [x] 10. Add `CommentaryTextResource` (public — `position`, `verse_from`, `verse_to`, `verse_label`, `content`) and `AdminCommentaryTextResource` (adds `id`).
- [x] 11. Implement `ListPublishedCommentariesController` + `ListPublishedCommentariesRequest` + route in `routes/api.php` (`api-key-or-sanctum` + `resolve-language` + `cache.headers`); query uses `published()->forLanguage($language)->orderBy('name')`. Feature test covers happy path, drafts hidden, language scoping.
- [x] 12. Implement `ListCommentaryChapterTextsController` + request (validate book USFM via `BibleBookCatalog::hasBook`, chapter int) + route. Returns `data[]` ordered by `position ASC`. Feature test covers happy path, 404 on unpublished commentary, 404 on unknown commentary.
- [x] 13. Implement `ListCommentaryVerseTextsController` + request + route (uses `coveringVerse`). Feature test covers single-verse hit, multi-verse hit, miss returns empty `data[]`.
- [x] 14. Implement `CreateCommentaryAction` + `CreateCommentaryRequest` (validate `slug` unique, `language` ISO-2 enum, `name` required array, `abbreviation` required, `source_commentary_id` exists when present) + `CreateCommentaryController` + route under admin group. Feature test covers 401, 403 (non-super), 422, happy path.
- [x] 15. Implement `UpdateCommentaryAction` + `UpdateCommentaryRequest` (slug unique except current, optional fields) + `UpdateCommentaryController` + route. Feature test covers slug-collision 422 and partial update.
- [x] 16. Implement `SetCommentaryPublicationAction` + `Publish`/`UnpublishCommentaryRequest` (empty bodies) + `Publish`/`UnpublishCommentaryController` + routes. Feature test asserts publish/unpublish round-trip and that public list reflects state change.
- [x] 17. Implement `ListCommentariesController` (admin) + `ListCommentariesRequest` (validate optional `?language=`, `?published=`) + route. Returns drafts and published. Feature test asserts drafts visible to super-admin and 403 for non-super.
- [x] 18. Implement `ListCommentaryTextsController` + `ListCommentaryTextsRequest` (validate required `?book=` USFM, `?chapter=`) + route. Paginated. Feature test asserts shape and filters.
- [x] 19. Implement `CreateCommentaryTextAction` + `CreateCommentaryTextRequest` (book USFM, chapter int, position int unique within (commentary, book, chapter), verse_from/to optional smallint, verse_label optional string, content required) + `CreateCommentaryTextController` + route. Feature test covers 422 on duplicate position and happy path.
- [x] 20. Implement `UpdateCommentaryTextAction` + `UpdateCommentaryTextRequest` (partial update) + `UpdateCommentaryTextController` + scoped route. Feature test covers happy path and 404 on text from a different commentary.
- [x] 21. Implement `DeleteCommentaryTextAction` + `DeleteCommentaryTextRequest` + `DeleteCommentaryTextController` + scoped route. Returns 204. Feature test covers happy path and 404 on cross-commentary text id.
- [x] 22. Implement `ReorderCommentaryTextsAction` (asserts every id belongs to `(commentary_id, book, chapter)`, updates positions in transaction) + `ReorderCommentaryTextsRequest` (validates `book`, `chapter`, `ids[]`) + `ReorderCommentaryTextsController` + route. Feature test covers happy path and 422 on cross-tuple ids.
- [x] 23. Add unit test `CommentaryTextQueryBuilderTest::coveringVerse()` covering: single-verse block (`verse_from = verse_to`), multi-verse block (`1..3`), open-ended block (`verse_to NULL`), miss (verse outside range). Pure SQL semantics not exhaustively covered by feature tests.
- [x] 24. Run `make lint-fix` + `make stan` + `make test-api filter=Commentary` + `make test-api`; confirm full suite passes before handing off.

## Risks & open questions

- **Symfony data quality:** existing rows may have `position` values that
  aren't valid verse numbers (e.g. position `0` for chapter intros).
  Backfill blindly maps `verse_from = position`; engineer should spot-check
  the production DDL for outliers and either skip non-positive positions
  or coerce to `NULL` (decision is one-line in the migration).
- **Slug-collision deterministic suffix:** appending `-{language}` works
  for the current dataset (one row per language) but is not future-proof
  if a language ever gains two commentaries. Acceptable for the backfill
  because admin can rename via PATCH; the unique constraint will block
  any future duplicate at the API layer.
- **`commentaries.language` widening:** depends on MBA-023 having
  backfilled it. If MBA-023's standardise-language target list omits
  `commentaries`, the resize in task 1 will truncate `ron`/`eng`.
  Engineer must verify before running and add `commentaries` to MBA-023's
  list if missing (one-line edit, doesn't change MBA-023's status).
- **Admin client drift:** `apps/admin/app/Support/Api/CommentaryClient.php`
  expects URLs that don't match this story's contract. Realignment is
  admin MB-017; do not let the broken client shape sneak into this
  story's endpoints.

## References

- MBA-023 plan/migrations — `_legacy_book_abbreviation_map`,
  `BackfillLegacyBookAbbreviationsAction`, `ReconcileTableHelper`.
- `ReadingPlan::resolveRouteBinding()` — published-on-slug binding precedent.
- `EducationalResources` / `SabbathSchool` — public/admin Resource and
  Controller layout precedent.
- `Admin/SabbathSchool/ReorderLessonSegmentsController` + `ReorderRequest`
  — reorder pattern (this story extends with `book`/`chapter` scope).
