# Plan: MBA-027-symfony-parity-catch-all

## Approach

Seven independent domain catch-ups land in one coordinated story. Each
section is structurally additive: a numbered evolutionary migration adds
columns / tables (with deterministic in-migration backfill where the
data is Laravel-only or trivially derivable), the domain layer extends
with new models/QBs/Actions, and HTTP endpoints follow the
existing public/admin split (`api-key-or-sanctum` + `resolve-language`
on public reads; `auth:sanctum` + `super-admin` on admin writes,
matching the MBA-024 / MBA-022 precedents). Symfony-derived data
movement (Symfony `devotional_type` rows, `mobile_version` rows,
`collection_topic.collection_id`, `collection_topic.image_cdn_url`)
defers to the MBA-031 ETL — these migrations only seed the minimum
needed for schema integrity and tests.

## Open questions — resolutions

1. **AC §36 migration tests vs. MBA-031 ETL deferral.** AC §2 / §12 /
   §15 / §33 say MBA-031 backfills; AC §36 says migration tests assert
   the backfill. Resolution: this story's migrations include a *minimum
   deterministic* in-migration backfill so the schema can transition
   safely (`devotional_types` seeded with `adults`/`kids` from the
   existing enum, `devotionals.type_id` UPDATEd by joining string
   `type`, Olympiad UUIDs assigned, news `language='ro'` /
   `published_at = created_at` WHERE NULL, `mobile_versions` seeded
   from `config/mobile.php` if the table is empty). MBA-031 then
   layers Symfony-specific cross-table movement (collection
   parent linkage, `image_cdn_url`, additional Symfony-defined
   devotional types) on top. Migration tests assert the in-migration
   backfill only; the ETL is tested in MBA-031.
2. **Backwards-compatible endpoint shape during deprecation window.**
   AC §5 keeps the `?type=adults` enum string accepted alongside the
   new `type` slug; the resolver tries the slug-as-enum-string first
   (since Adults/Kids are also valid slugs) and falls through to a
   slug lookup against `devotional_types`. AC §18 keeps `qr_codes.url`
   alongside the new `destination` column for the deprecation window
   (both populated by admin writes; reads serve `destination ?? url`);
   final drop is MBA-032 §6. AC §20 keeps the
   `GET /qr-codes?reference=` endpoint; the reference column is now
   nullable (`WHERE reference = ? AND reference IS NOT NULL` in the
   existing query builder).
3. **`olympiad_questions.chapters_from`/`chapters_to` widening.**
   Existing migration declares them `NOT NULL`. AC §23 adds a
   `chapter` column for single-chapter mode where `chapters_from` /
   `chapters_to` are NULL. Resolution: the Olympiad migration changes
   both columns to nullable. Existing range-based questions continue
   to populate them; new chapter-only questions populate `chapter`
   instead. The `OlympiadQuestionQueryBuilder::matchingTheme()` method
   added in this story unifies the two modes for attempt validation.
4. **Olympiad attempt → question set "lock".** AC §27 says start
   returns "attempt id + question UUIDs to lock the set" and attempts
   persist only `(book, chapters_label, language)` plus `total`. There
   is no join table between attempt and questions. Resolution: the
   attempt's `(book, chapters_label, language)` deterministically
   addresses a *theme*; submitted answers are validated to belong to
   that theme via `OlympiadQuestionQueryBuilder::matchingTheme()`.
   `total` is the question count at start time, captured on the
   attempt row. If new questions are added mid-attempt, they don't
   appear in `total` — score remains computable as
   `count(attempt_answers WHERE is_correct = true)`. Locking the
   *exact* question set was considered (a `seed` column or a join
   table) and rejected as scope creep — the story doesn't ask for it.
5. **Olympiad answer-submission UUID vs id.** Story exposes UUIDs
   externally (AC §23, §24); the request bodies for submit accept
   `question_uuid` + `selected_answer_uuid` (NULL for skip). The
   server resolves UUIDs to internal ids before persisting. The
   `(attempt_id, olympiad_question_id)` composite key uses internal
   ids — UUID is API surface only.
6. **Devotional types `language CHAR(2) NULL` resolution order.**
   AC §1 says `language NULL = global`. When an admin defines both a
   global `slug='youth'` and a language-specific `(slug='youth',
   language='ro')`, the public lookup must prefer the language-specific
   row. Resolution: `DevotionalTypeQueryBuilder::forSlugAndLanguage()`
   filters by slug, then by `(language = ? OR language IS NULL)` and
   orders `language IS NULL ASC LIMIT 1` so the specific row wins.
7. **Bootstrap drift when mobile versions move to DB.** The current
   `ShowAppBootstrapAction` reads
   `config('mobile.{ios,android}.latest_version')` directly. If this
   story moves the source of truth to `mobile_versions` and leaves
   bootstrap on config, the two endpoints will drift the moment an
   admin updates a row. Resolution: bootstrap reads through the new
   `MobileVersionsRepository::latestVersionFor(string $platform)`
   instead — same source as `GET /mobile/version` — so the response
   stays identical to the existing config-backed shape from the mobile
   client's POV.
8. **`Storage::disk('public')` for news image vs. CDN URL for
   collection topic.** News `image_url` is a path on the `public`
   disk (existing `NewsResource` resolves via
   `Storage::disk('public')->url(...)`). Collection topic
   `image_cdn_url` is a Symfony-shape *full URL* (not a path). The new
   `CollectionTopicResource` exposes it as `image_url` verbatim — no
   storage resolution. Admin upload flow: admin uses the existing
   `presign` endpoint to PUT to S3, then PATCHes the topic with the
   resulting CDN URL string. API does not synthesise URLs from keys
   for collection topics.
9. **`qr_codes` admin write + scan endpoint coexistence.** AC §21's
   scan endpoint is anonymous (`api-key-or-sanctum`); AC §22's admin
   CRUD is super-admin. Both bind `{qr}` as a numeric id; the
   anonymous scan does not need to scope-bind beyond the id. Scan
   emits a Laravel event `QrCodeScanned` carrying QR metadata; a
   listener subscriber lands in MBA-030 (analytics foundation). For
   this story, the event is dispatched but unsubscribed — verified by
   `Event::fake()` in the feature test. No analytics persistence here.
10. **`collections` slug binding scope.** `{collection:slug}` is
    public — there is no draft/published distinction on collections in
    the story. Plain Eloquent route-model binding suffices (no
    `resolveRouteBinding` override needed). The nested
    `{collection:slug}/topics/{topic}` route uses
    `Route::scopeBindings()` so a topic from another collection 404s.

## Domain layout

```
app/Domain/Devotional/
├── Models/
│   ├── DevotionalType.php                           # NEW — slug/title/position/language; route key = slug
│   └── Devotional.php                               # MOD — drop string `type` enum cast (column kept until MBA-032); add type_id, audio_cdn_url, audio_embed, video_embed; add type() belongsTo
├── Enums/
│   └── DevotionalType.php                           # MOD — kept for backwards compat resolver only; renamed to LegacyDevotionalType (or kept; see task 1)
├── QueryBuilders/
│   ├── DevotionalQueryBuilder.php                   # MOD — replace ofType(DevotionalType) with ofTypeId(int)
│   └── DevotionalTypeQueryBuilder.php               # NEW — forLanguage, forSlugAndLanguage, ordered
├── DataTransferObjects/
│   ├── FetchDevotionalData.php                      # MOD — type: int (was enum)
│   ├── CreateDevotionalTypeData.php                 # NEW — slug/title/position/language
│   └── UpdateDevotionalTypeData.php                 # NEW — partial fields
├── Actions/
│   ├── FetchDevotionalAction.php                    # MOD — keys cache by type_id, not enum
│   ├── ResolveDevotionalTypeAction.php              # NEW — slug+language → DevotionalType (handles legacy enum strings + slug)
│   ├── CreateDevotionalTypeAction.php               # NEW
│   ├── UpdateDevotionalTypeAction.php               # NEW
│   ├── DeleteDevotionalTypeAction.php               # NEW — guards FK RESTRICT, throws ValidationException if type has devotionals
│   └── ReorderDevotionalTypesAction.php             # NEW — handle(list<int> $ids)
└── Support/
    └── DevotionalCacheKeys.php                      # MOD — show() keyed by type_id

app/Domain/Mobile/
├── Models/
│   └── MobileVersion.php                            # NEW — platform/kind/version/released_at/release_notes/store_url
├── QueryBuilders/
│   └── MobileVersionQueryBuilder.php                # NEW — forPlatform, ofKind
├── DataTransferObjects/
│   ├── CreateMobileVersionData.php                  # NEW
│   └── UpdateMobileVersionData.php                  # NEW
├── Actions/
│   ├── ShowAppBootstrapAction.php                   # MOD — reads via MobileVersionsRepository, not config
│   ├── ListMobileVersionsAction.php                 # NEW — by-platform list, cached 5 min
│   ├── ShowMobileVersionAction.php                  # NEW — DB-backed replacement of config lookup
│   ├── CreateMobileVersionAction.php                # NEW
│   ├── UpdateMobileVersionAction.php                # NEW
│   └── DeleteMobileVersionAction.php                # NEW
└── Support/
    ├── MobileVersionsRepository.php                 # NEW — latestVersionFor(platform), minRequiredFor(platform); cached
    └── MobileCacheKeys.php                          # MOD — add version() key + tagsForVersions()

app/Domain/Collections/
├── Models/
│   ├── Collection.php                               # NEW — slug/name/language/position; route key = slug
│   ├── CollectionTopic.php                          # MOD — add collection_id (BelongsTo Collection nullable), image_cdn_url; resolveRouteBinding stays as-is for legacy `{topic}` path
│   └── CollectionReference.php                      # unchanged
├── QueryBuilders/
│   ├── CollectionQueryBuilder.php                   # NEW — forLanguage, ordered, withTopicsCount
│   └── CollectionTopicQueryBuilder.php              # MOD — withinCollection(int $collectionId)
├── DataTransferObjects/
│   ├── CreateCollectionData.php                     # NEW
│   ├── UpdateCollectionData.php                     # NEW
│   ├── CreateCollectionTopicData.php                # NEW (collection_id optional)
│   └── UpdateCollectionTopicData.php                # NEW
├── Actions/
│   ├── ListCollectionsAction.php                    # NEW — public list of parent collections by language
│   ├── ShowCollectionAction.php                     # NEW — collection with nested topics
│   ├── CreateCollectionAction.php                   # NEW
│   ├── UpdateCollectionAction.php                   # NEW
│   ├── DeleteCollectionAction.php                   # NEW — orphans topics (FK SET NULL), invalidates topic cache
│   ├── CreateCollectionTopicAction.php              # NEW (admin)
│   ├── UpdateCollectionTopicAction.php              # NEW (admin)
│   └── DeleteCollectionTopicAction.php              # NEW (admin)
└── Support/
    └── CollectionsCacheKeys.php                     # MOD — add collectionsList(), collection(slug)

app/Domain/QrCode/
├── Models/
│   └── QrCode.php                                   # MOD — add place/base_url/source/destination/name/content/description; reference now nullable; imageUrl unchanged
├── QueryBuilders/
│   └── QrCodeQueryBuilder.php                       # MOD — forReference now scopes `whereNotNull('reference')`
├── DataTransferObjects/
│   ├── CreateQrCodeData.php                         # NEW
│   └── UpdateQrCodeData.php                         # NEW
├── Events/
│   └── QrCodeScanned.php                            # NEW — broadcast: place/source/destination/qr_id/scanned_at
├── Actions/
│   ├── ShowQrCodeByReferenceAction.php              # MOD — renamed from ShowQrCodeAction; behavior unchanged
│   ├── RecordQrCodeScanAction.php                   # NEW — dispatches QrCodeScanned event
│   ├── ListAdminQrCodesAction.php                   # NEW (admin paginated list)
│   ├── CreateQrCodeAction.php                       # NEW
│   ├── UpdateQrCodeAction.php                       # NEW
│   └── DeleteQrCodeAction.php                       # NEW
└── Support/
    └── QrCodeCacheKeys.php                          # MOD — adminList()

app/Domain/Olympiad/
├── Models/
│   ├── OlympiadQuestion.php                         # MOD — add uuid, verse, chapter, is_reviewed; chapters_from/_to nullable
│   ├── OlympiadAnswer.php                           # MOD — add uuid
│   ├── OlympiadAttempt.php                          # NEW — user_id/book/chapters_label/language/score/total/started_at/completed_at; resolveRouteBinding scopes to current user
│   └── OlympiadAttemptAnswer.php                    # NEW — composite PK (attempt_id, olympiad_question_id)
├── QueryBuilders/
│   ├── OlympiadQuestionQueryBuilder.php             # MOD — forVerse(?int), matchingTheme(book, ChapterRange, Language)
│   └── OlympiadAttemptQueryBuilder.php              # NEW — forUser, forFilters(language?, book?, chaptersLabel?), newestFirst
├── DataTransferObjects/
│   ├── StartOlympiadAttemptData.php                 # NEW — user/book/ChapterRange/language
│   ├── SubmitOlympiadAnswerLine.php                 # NEW — questionUuid + selectedAnswerUuid (nullable)
│   ├── SubmitOlympiadAnswersData.php                # NEW — attempt + list<SubmitOlympiadAnswerLine>
│   └── ListOlympiadAttemptsFilter.php               # NEW
├── Exceptions/
│   ├── OlympiadAttemptAlreadyFinishedException.php  # NEW — 422 in handler
│   └── OlympiadAttemptThemeMismatchException.php    # NEW — 422; reused by submit + finish
├── Actions/
│   ├── StartOlympiadAttemptAction.php               # NEW — captures total + question UUIDs
│   ├── SubmitOlympiadAttemptAnswersAction.php       # NEW — idempotent upsert per (attempt, question)
│   ├── FinishOlympiadAttemptAction.php              # NEW — sets completed_at, computes score
│   ├── ListUserOlympiadAttemptsAction.php           # NEW — paginated user history
│   └── ListAdminOlympiadAttemptsAction.php          # NEW — paginated admin metrics
└── Support/
    └── OlympiadCacheKeys.php                        # unchanged (attempts are user-scoped, no public cache)

app/Domain/Notes/
├── Models/
│   └── Note.php                                     # MOD — add color (cast string|null)
├── DataTransferObjects/
│   ├── CreateNoteData.php                           # MOD — add color
│   └── UpdateNoteData.php                           # MOD — add color + colorProvided
└── Actions/
    ├── CreateNoteAction.php                         # MOD — persist color
    └── UpdateNoteAction.php                         # MOD — partial color update

app/Domain/Favorites/
├── Models/
│   └── Favorite.php                                 # MOD — add color
├── DataTransferObjects/
│   ├── CreateFavoriteData.php                       # MOD — add color
│   └── UpdateFavoriteData.php                       # MOD — add color + colorProvided
└── Actions/
    ├── CreateFavoriteAction.php                     # MOD
    └── UpdateFavoriteAction.php                     # MOD

app/Domain/News/
├── Models/
│   └── News.php                                     # MOD — resolveRouteBinding applies published() scope
├── QueryBuilders/
│   └── NewsQueryBuilder.php                         # unchanged
├── Actions/
│   └── ShowNewsAction.php                           # NEW — cached single-news fetch
└── Support/
    └── NewsCacheKeys.php                            # MOD — add show(int $id)

app/Http/Rules/
└── HexColor.php                                     # NEW — invokable rule; #RRGGBB or #RRGGBBAA

app/Http/Controllers/Api/V1/
├── Devotionals/
│   └── ShowDevotionalController.php                 # MOD — uses ResolveDevotionalTypeAction
├── Mobile/
│   ├── ShowMobileVersionController.php              # MOD — ShowMobileVersionAction (DB)
│   └── ShowAppBootstrapController.php               # unchanged (action internal change)
├── Collections/
│   ├── ListCollectionsController.php                # NEW — GET /api/v1/collections
│   ├── ShowCollectionController.php                 # NEW — GET /api/v1/collections/{collection:slug}
│   ├── ListCollectionTopicsController.php           # unchanged (legacy; routes regrouped)
│   └── ShowCollectionTopicController.php            # MOD — supports /collections/{collection:slug}/topics/{topic} via scopeBindings
├── QrCode/
│   ├── ShowQrCodeController.php                     # MOD — uses renamed action
│   └── RecordQrCodeScanController.php               # NEW — POST /api/v1/qr-codes/{qr}/scans
├── Olympiad/
│   ├── StartOlympiadAttemptController.php           # NEW
│   ├── SubmitOlympiadAttemptAnswersController.php   # NEW
│   ├── FinishOlympiadAttemptController.php          # NEW
│   └── ListUserOlympiadAttemptsController.php       # NEW
├── Notes/
│   ├── StoreNoteController.php                      # unchanged (request DTO carries color)
│   └── UpdateNoteController.php                     # unchanged
├── Favorites/
│   ├── CreateFavoriteController.php                 # unchanged
│   └── UpdateFavoriteController.php                 # unchanged
└── News/
    ├── ListNewsController.php                       # unchanged
    └── ShowNewsController.php                       # NEW — GET /api/v1/news/{news}

app/Http/Controllers/Api/V1/Admin/
├── Devotionals/
│   ├── ListDevotionalTypesController.php            # NEW
│   ├── CreateDevotionalTypeController.php           # NEW
│   ├── UpdateDevotionalTypeController.php           # NEW
│   ├── DeleteDevotionalTypeController.php           # NEW
│   └── ReorderDevotionalTypesController.php         # NEW
├── Mobile/
│   ├── ListMobileVersionsController.php             # NEW (admin)
│   ├── CreateMobileVersionController.php            # NEW
│   ├── UpdateMobileVersionController.php            # NEW
│   └── DeleteMobileVersionController.php            # NEW
├── Collections/
│   ├── ListAdminCollectionsController.php           # NEW
│   ├── CreateCollectionController.php               # NEW
│   ├── UpdateCollectionController.php               # NEW
│   ├── DeleteCollectionController.php               # NEW
│   ├── ListAdminCollectionTopicsController.php      # NEW
│   ├── CreateCollectionTopicController.php          # NEW
│   ├── UpdateCollectionTopicController.php          # NEW
│   └── DeleteCollectionTopicController.php          # NEW
├── QrCode/
│   ├── ListAdminQrCodesController.php               # NEW
│   ├── CreateQrCodeController.php                   # NEW
│   ├── UpdateQrCodeController.php                   # NEW
│   └── DeleteQrCodeController.php                   # NEW
└── Olympiad/
    └── ListAdminOlympiadAttemptsController.php      # NEW

app/Http/Requests/                                   # one Request per controller, sibling-named
app/Http/Resources/
├── Devotionals/
│   ├── DevotionalResource.php                       # MOD — emit type slug + audio_cdn_url, audio_embed, video_embed
│   └── DevotionalTypeResource.php                   # NEW
├── Mobile/
│   └── MobileVersionResource.php                    # MOD — adds released_at, release_notes, store_url; preserves existing config-shape keys
├── Collections/
│   ├── CollectionResource.php                       # NEW (list + nested form)
│   ├── CollectionDetailResource.php                 # NEW (with topics[])
│   ├── CollectionTopicResource.php                  # MOD — adds image_url
│   └── CollectionTopicDetailResource.php            # MOD — adds image_url
├── QrCode/
│   ├── QrCodeResource.php                           # MOD — adds place/source/destination/name/content/description
│   └── QrCodeListItemResource.php                   # MOD — admin list shape; shares parent
├── Olympiad/
│   ├── OlympiadQuestionResource.php                 # MOD — uuid/verse/chapter/is_reviewed
│   ├── OlympiadAnswerResource.php                   # MOD — uuid
│   ├── OlympiadAttemptResource.php                  # NEW — id/book/chapters_label/language/score/total/started_at/completed_at
│   └── OlympiadAttemptStartResource.php             # NEW — attempt + question_uuids[]
├── Notes/
│   └── NoteResource.php                             # MOD — adds color
├── Favorites/
│   └── FavoriteResource.php                         # MOD — adds color
└── News/
    ├── NewsResource.php                             # unchanged (already exposes content)
    └── NewsDetailResource.php                       # NEW — emits content always (list resource may collapse later)

database/migrations/                                 # timestamp slice 2026_05_03_002000+ (after MBA-024)
├── 2026_05_03_002000_evolve_devotional_types_table.php
├── 2026_05_03_002001_extend_devotionals_for_type_fk_and_media.php
├── 2026_05_03_002002_evolve_mobile_versions_table_and_seed.php
├── 2026_05_03_002003_create_collections_and_extend_collection_topics.php
├── 2026_05_03_002004_extend_qr_codes_for_full_symfony_shape.php
├── 2026_05_03_002005_extend_olympiad_questions_and_answers.php
├── 2026_05_03_002006_create_olympiad_attempts_and_attempt_answers.php
├── 2026_05_03_002007_add_color_to_notes_and_favorites.php
└── 2026_05_03_002008_backfill_news_language_and_published_at.php

database/factories/                                  # NEW
├── DevotionalTypeFactory.php
├── MobileVersionFactory.php
├── CollectionFactory.php
├── OlympiadAttemptFactory.php
└── OlympiadAttemptAnswerFactory.php
```

## Schema changes

| Table | Change | Notes |
|---|---|---|
| `devotional_types` | `+ slug VARCHAR(64) UNIQUE` | Backfill rows `('adults', 'Adults', 1, NULL)` and `('kids', 'Kids', 2, NULL)` if absent. |
| `devotional_types` | `+ title VARCHAR(128)` | — |
| `devotional_types` | `+ position UNSIGNED SMALLINT DEFAULT 0` | — |
| `devotional_types` | `+ language CHAR(2) NULL` | NULL = global. |
| `devotional_types` | `+ timestamps` | — |
| `devotionals` | `+ type_id BIGINT UNSIGNED FK ON DELETE RESTRICT` | Backfill: `UPDATE devotionals d JOIN devotional_types t ON t.slug = d.type AND t.language IS NULL SET d.type_id = t.id`. Then `ALTER COLUMN type_id NOT NULL`. |
| `devotionals` | `+ audio_cdn_url TEXT NULL` | — |
| `devotionals` | `+ audio_embed LONGTEXT NULL` | — |
| `devotionals` | `+ video_embed LONGTEXT NULL` | — |
| `devotionals` | `+ UNIQUE (language, type_id, date)` | Replaces regression flagged in MBA-023 §7. |
| `devotionals` | keep `type` column | Backwards compat; drop in MBA-032 §3. |
| `mobile_versions` | `+ platform VARCHAR(16)` | — |
| `mobile_versions` | `+ kind VARCHAR(16)` | Open-ended string per AC §8 technical note. |
| `mobile_versions` | `+ version VARCHAR(25)` | — |
| `mobile_versions` | `+ released_at TIMESTAMP NULL` | — |
| `mobile_versions` | `+ release_notes JSON NULL` | — |
| `mobile_versions` | `+ store_url VARCHAR(255) NULL` | — |
| `mobile_versions` | `+ timestamps` | — |
| `mobile_versions` | `+ UNIQUE (platform, kind)` | — |
| `mobile_versions` | seed from `config/mobile.php` | One-shot if `count() === 0`: `(ios, min_required, $cfg.ios.minimum_supported_version)`, `(ios, latest, $cfg.ios.latest_version)`, mirror for android. `store_url` = `update_url`. `release_notes = NULL`. |
| `collections` | new table — `id`, `slug VARCHAR(255) UNIQUE`, `name VARCHAR(255)`, `language CHAR(2)`, `position UNSIGNED INT DEFAULT 0`, timestamps | Backfill at MBA-031. |
| `collections` | `+ INDEX (language, position)` | — |
| `collection_topics` | `+ collection_id BIGINT UNSIGNED NULL FK ON DELETE SET NULL` | Backfill at MBA-031. |
| `collection_topics` | `+ image_cdn_url TEXT NULL` | Backfill at MBA-031. |
| `qr_codes` | `+ place VARCHAR(255) NOT NULL DEFAULT ''` | Default empty during deprecation; admin writes populate. Default removed in MBA-032 §6. |
| `qr_codes` | `+ base_url VARCHAR(255) NOT NULL DEFAULT ''` | Same. |
| `qr_codes` | `+ source VARCHAR(255) NOT NULL DEFAULT ''` | Same. |
| `qr_codes` | `+ destination VARCHAR(255) NOT NULL DEFAULT ''` | Backfill `destination = url` for existing rows; default removed by MBA-032 alongside `url` drop. |
| `qr_codes` | `+ name VARCHAR(50) NOT NULL DEFAULT ''` | — |
| `qr_codes` | `+ content LONGTEXT NOT NULL` | Backfill `content = url`. |
| `qr_codes` | `+ description LONGTEXT NULL` | — |
| `qr_codes` | `reference NOT NULL → NULL` | Reference-keyed lookups still served; non-verse rows now allowed. |
| `qr_codes` | `+ UNIQUE (place, source)` | After backfill so the empty defaults don't collide on more than one pre-existing row. Skipped if `count > 1` rows have `place=''` (engineer asserts). |
| `qr_codes` | keep `url` column | Backwards compat; drop in MBA-032 §6. |
| `olympiad_questions` | `+ uuid CHAR(36) UNIQUE` | Backfill `Str::uuid()` per row. NOT NULL after backfill. |
| `olympiad_questions` | `+ verse SMALLINT UNSIGNED NULL` | — |
| `olympiad_questions` | `+ chapter SMALLINT UNSIGNED NULL` | Single-chapter mode marker. |
| `olympiad_questions` | `chapters_from NOT NULL → NULL` | Required to support `chapter`-mode rows. |
| `olympiad_questions` | `chapters_to NOT NULL → NULL` | Same. |
| `olympiad_questions` | `+ is_reviewed BOOLEAN DEFAULT false` | — |
| `olympiad_questions` | `+ INDEX (is_reviewed)` | Editorial workflow filter. |
| `olympiad_answers` | `+ uuid CHAR(36) UNIQUE` | Backfill. |
| `olympiad_attempts` | new table — see AC §25 | `INDEX (user_id, completed_at DESC)`, `INDEX (language, book, chapters_label)` for admin metrics. |
| `olympiad_attempt_answers` | new table — see AC §26 | PK `(attempt_id, olympiad_question_id)`. |
| `notes` | `+ color VARCHAR(9) NULL` | — |
| `favorites` | `+ color VARCHAR(9) NULL` | — |
| `news` | `language='ro'` WHERE NULL | One-shot `UPDATE` in migration. |
| `news` | `published_at = created_at` WHERE NULL | One-shot. |

## HTTP endpoints

### Devotional types

| Verb | Path | Controller | Request | Resource | Auth |
|---|---|---|---|---|---|
| GET | `/api/v1/devotionals?date&language&type` | `ShowDevotionalController` (mod) | `ShowDevotionalRequest` (mod) | `DevotionalResource` (mod) | `api-key-or-sanctum` + `resolve-language` (existing) |
| GET | `/api/v1/admin/devotional-types` | `ListDevotionalTypesController` | `ListDevotionalTypesRequest` | `DevotionalTypeResource::collection` | `auth:sanctum` + `super-admin` |
| POST | `/api/v1/admin/devotional-types` | `CreateDevotionalTypeController` | `CreateDevotionalTypeRequest` | `DevotionalTypeResource` (201) | same |
| PATCH | `/api/v1/admin/devotional-types/{type}` | `UpdateDevotionalTypeController` | `UpdateDevotionalTypeRequest` | `DevotionalTypeResource` | same |
| DELETE | `/api/v1/admin/devotional-types/{type}` | `DeleteDevotionalTypeController` | `DeleteDevotionalTypeRequest` | 204 | same |
| POST | `/api/v1/admin/devotional-types/reorder` | `ReorderDevotionalTypesController` | `ReorderRequest` (existing) | `{message}` | same |

### Mobile versions

| Verb | Path | Controller | Request | Resource | Auth |
|---|---|---|---|---|---|
| GET | `/api/v1/mobile/version?platform=` | `ShowMobileVersionController` (mod) | `ShowMobileVersionRequest` (existing) | `MobileVersionResource` (mod) | `api-key-or-sanctum` + `cache.headers:public;max_age=300` |
| GET | `/api/v1/admin/mobile-versions` | `ListMobileVersionsController` | `ListMobileVersionsRequest` | `MobileVersionResource::collection` | `auth:sanctum` + `super-admin` |
| POST | `/api/v1/admin/mobile-versions` | `CreateMobileVersionController` | `CreateMobileVersionRequest` | `MobileVersionResource` (201) | same |
| PATCH | `/api/v1/admin/mobile-versions/{version}` | `UpdateMobileVersionController` | `UpdateMobileVersionRequest` | `MobileVersionResource` | same |
| DELETE | `/api/v1/admin/mobile-versions/{version}` | `DeleteMobileVersionController` | `DeleteMobileVersionRequest` | 204 | same |

### Collections

| Verb | Path | Controller | Request | Resource | Auth |
|---|---|---|---|---|---|
| GET | `/api/v1/collections?language=` | `ListCollectionsController` | `ListCollectionsRequest` | `CollectionResource::collection` | public read group |
| GET | `/api/v1/collections/{collection:slug}` | `ShowCollectionController` | `ShowCollectionRequest` | `CollectionDetailResource` | public read group |
| GET | `/api/v1/collections/{collection:slug}/topics/{topic}` | `ShowCollectionTopicController` (mod) | `ShowCollectionTopicRequest` (mod) | `CollectionTopicDetailResource` (mod) | public read group + `Route::scopeBindings()` |
| GET | `/api/v1/admin/collections` | `ListAdminCollectionsController` | `ListAdminCollectionsRequest` | `CollectionResource::collection` | super-admin |
| POST | `/api/v1/admin/collections` | `CreateCollectionController` | `CreateCollectionRequest` | `CollectionResource` (201) | same |
| PATCH | `/api/v1/admin/collections/{collection}` | `UpdateCollectionController` | `UpdateCollectionRequest` | `CollectionResource` | same |
| DELETE | `/api/v1/admin/collections/{collection}` | `DeleteCollectionController` | `DeleteCollectionRequest` | 204 | same |
| GET | `/api/v1/admin/collections/{collection}/topics` | `ListAdminCollectionTopicsController` | `ListAdminCollectionTopicsRequest` | `CollectionTopicResource::collection` | same + `scopeBindings` |
| POST | `/api/v1/admin/collections/{collection}/topics` | `CreateCollectionTopicController` | `CreateCollectionTopicRequest` | `CollectionTopicResource` (201) | same + `scopeBindings` |
| PATCH | `/api/v1/admin/collections/{collection}/topics/{topic}` | `UpdateCollectionTopicController` | `UpdateCollectionTopicRequest` | `CollectionTopicResource` | same + `scopeBindings` |
| DELETE | `/api/v1/admin/collections/{collection}/topics/{topic}` | `DeleteCollectionTopicController` | `DeleteCollectionTopicRequest` | 204 | same + `scopeBindings` |

Public legacy `GET /api/v1/collections` (topics list) is preserved by re-routing it under a *different* outer prefix or removing it; AC §16 says the parent-collection endpoint replaces the list-topics endpoint at the same path. Resolution: the new `ListCollectionsController` takes over `GET /api/v1/collections`; the legacy topic list is removed from the public surface (no caller — confirmed: admin/frontend only consume show, see admin MB-015 / frontend story). The `ListCollectionTopicsController` class stays because it's still mounted under admin (`/api/v1/admin/collections/{collection}/topics`).

### QR codes

| Verb | Path | Controller | Request | Resource | Auth |
|---|---|---|---|---|---|
| GET | `/api/v1/qr-codes?reference=` | `ShowQrCodeController` (mod) | `ShowQrCodeRequest` (existing) | `QrCodeResource` (mod) | existing |
| POST | `/api/v1/qr-codes/{qr}/scans` | `RecordQrCodeScanController` | `RecordQrCodeScanRequest` | 204 | `api-key-or-sanctum` + `throttle:public-anon` |
| GET | `/api/v1/admin/qr-codes` | `ListAdminQrCodesController` | `ListAdminQrCodesRequest` | `QrCodeResource::collection` | super-admin |
| POST | `/api/v1/admin/qr-codes` | `CreateQrCodeController` | `CreateQrCodeRequest` | `QrCodeResource` (201) | same |
| PATCH | `/api/v1/admin/qr-codes/{qr}` | `UpdateQrCodeController` | `UpdateQrCodeRequest` | `QrCodeResource` | same |
| DELETE | `/api/v1/admin/qr-codes/{qr}` | `DeleteQrCodeController` | `DeleteQrCodeRequest` | 204 | same |

### Olympiad attempts

| Verb | Path | Controller | Request | Resource | Auth |
|---|---|---|---|---|---|
| POST | `/api/v1/olympiad/attempts` | `StartOlympiadAttemptController` | `StartOlympiadAttemptRequest` | `OlympiadAttemptStartResource` (201) | `auth:sanctum` + `resolve-language` |
| POST | `/api/v1/olympiad/attempts/{attempt}/answers` | `SubmitOlympiadAttemptAnswersController` | `SubmitOlympiadAttemptAnswersRequest` | `OlympiadAttemptResource` | same + `scopeBindings` |
| POST | `/api/v1/olympiad/attempts/{attempt}/finish` | `FinishOlympiadAttemptController` | `FinishOlympiadAttemptRequest` | `OlympiadAttemptResource` | same |
| GET | `/api/v1/olympiad/attempts?language=&book=&chapters=` | `ListUserOlympiadAttemptsController` | `ListUserOlympiadAttemptsRequest` | `OlympiadAttemptResource::collection` | same |
| GET | `/api/v1/admin/olympiad/attempts` | `ListAdminOlympiadAttemptsController` | `ListAdminOlympiadAttemptsRequest` | `OlympiadAttemptResource::collection` | super-admin |

Route-model binding strategy: `OlympiadAttempt::resolveRouteBinding($value, $field)` filters by `user_id = auth()->id()` so a stale id from a different user 404s. Auth is required on every attempts route, so the closure can read `auth()` safely.

### News

| Verb | Path | Controller | Request | Resource | Auth |
|---|---|---|---|---|---|
| GET | `/api/v1/news/{news}` | `ShowNewsController` | `ShowNewsRequest` | `NewsDetailResource` | existing news middleware group |

`News::resolveRouteBinding` applies `published()` so unpublished rows 404. ID-bound (no slug column).

### Notes / Favorites colour

`StoreNoteRequest`, `UpdateNoteRequest`, `CreateFavoriteRequest`,
`UpdateFavoriteRequest` all gain an optional `color` field validated by
the new `HexColor` rule (matches `#RRGGBB` or `#RRGGBBAA`). DTOs carry
the new field; Actions persist it.

## Tasks

### Devotional types entity + media

- [x] 1. Write `2026_05_03_002000_evolve_devotional_types_table.php` — add `slug` (UNIQUE), `title`, `position`, `language`, timestamps if absent; seed `('adults','Adults',1,NULL)` and `('kids','Kids',2,NULL)` if neither exists; idempotent (skip seeds when slugs already present).
- [x] 2. Write `2026_05_03_002001_extend_devotionals_for_type_fk_and_media.php` — add `type_id` (nullable + FK ON DELETE RESTRICT to `devotional_types`), `audio_cdn_url`, `audio_embed`, `video_embed`; UPDATE `type_id` from join on `type` slug; alter `type_id` NOT NULL; add UNIQUE `(language, type_id, date)`. Down: reverse.
- [x] 3. Add `App\Domain\Devotional\Models\DevotionalType` (route key `slug`, casts `position` int, `name` field is `title`; relation `devotionals()` HasMany).
- [x] 4. Add `DevotionalTypeQueryBuilder` — `forLanguage(Language $language)`, `forSlugAndLanguage(string $slug, Language $language)` (orders `language IS NULL ASC` so language-specific wins), `ordered()`.
- [x] 5. Modify `Devotional` model — drop string `type` enum cast; keep `type` column readable (no cast); add `type_id` fillable + `type()` BelongsTo to `DevotionalType`; `audio_cdn_url`, `audio_embed`, `video_embed` fillable.
- [x] 6. Modify `DevotionalQueryBuilder::ofType` — replace with `ofTypeId(int $id)`; remove old `DevotionalType` enum coupling. `FetchDevotionalAction`, cache key, and DTO update accordingly.
- [x] 7. Add `ResolveDevotionalTypeAction::handle(string $typeParam, Language $language): DevotionalType` — accepts the legacy `adults`/`kids` strings AND admin-defined slugs; resolves via `forSlugAndLanguage`; throws `ModelNotFoundException` (handler maps 404) on miss.
- [x] 8. Modify `ShowDevotionalRequest::toData()` — replaces `DevotionalType::from($input)` with `ResolveDevotionalTypeAction->handle()`; DTO now carries `int $typeId`.
- [x] 9. Modify `DevotionalResource::toArray` — emit `type` as `slug` resolved via the loaded `type` relation (no enum lookup); emit `audio_cdn_url`, `audio_embed`, `video_embed` (whenNotNull).
- [x] 10. Add `DevotionalTypeResource` (id, slug, title, position, language).
- [x] 11. Add `CreateDevotionalTypeData`, `UpdateDevotionalTypeData` DTOs; `CreateDevotionalTypeAction`, `UpdateDevotionalTypeAction`, `DeleteDevotionalTypeAction` (delete throws `ValidationException` if any `devotionals.type_id` references the type — short-circuit before FK does), `ReorderDevotionalTypesAction(list<int> $ids)`.
- [x] 12. Add admin Form Requests under `App/Http/Requests/Admin/Devotionals/`: `ListDevotionalTypesRequest` (paginated), `CreateDevotionalTypeRequest` (slug regex `^[a-z0-9-]+$`, unique, title required, position int, language nullable ISO-2), `UpdateDevotionalTypeRequest` (sometimes-rules), `DeleteDevotionalTypeRequest` (empty body). Admin reorder reuses existing `ReorderRequest`.
- [x] 13. Add admin controllers under `App/Http/Controllers/Api/V1/Admin/Devotionals/` (List, Create (201), Update, Delete (204), Reorder). Routes under `admin.devotional-types.*` group with `auth:sanctum` + `super-admin`.
- [x] 14. Add `DevotionalTypeFactory` with `language()` state.
- [x] 15. Feature tests: `ShowDevotionalEndpointTest` (legacy enum string still resolves, slug resolves, language-specific type resolution wins over global, unknown slug 404, duplicate `(language, type_id, date)` is impossible). `AdminDevotionalTypesEndpointTest` (auth/403/422/CRUD/reorder/delete blocked when has devotionals).

### Mobile versions DB-backed

- [x] 16. Write `2026_05_03_002002_evolve_mobile_versions_table_and_seed.php` — add columns from AC §8; UNIQUE `(platform, kind)`; seed from `config('mobile.ios.*')` + `config('mobile.android.*')` only when `count() === 0`. Down: drop columns + seeded rows.
- [x] 17. Add `App\Domain\Mobile\Models\MobileVersion` (casts: `released_at` datetime, `release_notes` array) with `MobileVersionQueryBuilder` (`forPlatform`, `ofKind`).
- [x] 18. Add `MobileVersionsRepository` — `latestVersionFor(string $platform): ?string`, `minRequiredFor(string $platform): ?string`, `payloadFor(string $platform): array<string, mixed>` returning the existing config-shape keys (`minimum_supported_version`, `latest_version`, `update_url`, `force_update_below`). Memoized in-process; backed by 5-min Cache::remember keyed on platform + tags `['mobile-versions']`.
- [x] 19. Add `ShowMobileVersionAction` calling the repository; modify `ShowMobileVersionController` to delegate. Existing `ShowMobileVersionRequest` reused unchanged.
- [x] 20. Modify `MobileVersionResource::toArray` — keeps the existing keys (locked by mobile contract per AC §10 + comment in current resource) and adds optional admin-facing keys (`released_at`, `release_notes`, `store_url`) only when the underlying object is a `MobileVersion` model (admin path) — public `/mobile/version` path passes the repository payload array and gets the legacy shape verbatim.
- [x] 21. Modify `ShowAppBootstrapAction` — replace `config('mobile.ios.latest_version')` / `config('mobile.android.latest_version')` reads with `MobileVersionsRepository::latestVersionFor('ios'|'android')`; same fallback to `null` when no row exists. Bootstrap cache tag list gains `'mobile-versions'`.
- [x] 22. Add admin Form Requests + DTOs + Actions: `ListMobileVersionsRequest`, `CreateMobileVersionRequest` (platform `in:ios,android`, kind `in:min_required,latest` enforced via Rule (kind is open-ended in the column for future kinds, but admin writes constrain to known values), version regex semver-ish, released_at nullable date, release_notes nullable JSON, store_url URL); Update (sometimes), Delete; admin controllers; routes `admin.mobile-versions.*`.
- [x] 23. `MobileVersionFactory` with `ios()`, `android()`, `latest()`, `minRequired()` states.
- [x] 24. Feature tests: `ShowMobileVersionEndpointTest` (existing config-shape preserved; row update reflected after repo cache TTL bypass via Cache::flush in test). `AdminMobileVersionsEndpointTest` (CRUD + UNIQUE (platform, kind) returns 422). Migration test asserts seed rows exist post-migration with the expected `(platform, kind, version)` triples and that re-running the migration on a populated table is a no-op.

### Collections parent + topic image

- [ ] 25. Write `2026_05_03_002003_create_collections_and_extend_collection_topics.php` — `Schema::create('collections', ...)` with INDEX `(language, position)`; `ALTER TABLE collection_topics` adds `collection_id` (nullable FK SET NULL) and `image_cdn_url`. No data backfill (MBA-031 ETL).
- [ ] 26. Add `App\Domain\Collections\Models\Collection` (route key `slug`; HasMany `topics()` ordered by `position`) + `CollectionQueryBuilder` (`forLanguage`, `ordered`, `withTopicsCount`).
- [ ] 27. Modify `CollectionTopic` — fillable adds `collection_id`, `image_cdn_url`; `collection()` BelongsTo Collection. `CollectionTopicQueryBuilder::withinCollection(int)`.
- [ ] 28. Public Actions: `ListCollectionsAction(language, page, perPage)`, `ShowCollectionAction(Collection)` — both cached 1h with `CollectionsCacheKeys` extensions.
- [ ] 29. Public Resources + Requests + Controllers + routes:
   - `CollectionResource` (id, slug, name, language, position, topics_count via `withCount`).
   - `CollectionDetailResource` (adds nested topics: id, name, image_url=image_cdn_url, position).
   - `ListCollectionsRequest`, `ShowCollectionRequest`.
   - `ListCollectionsController`, `ShowCollectionController` mounted at `GET /api/v1/collections` and `GET /api/v1/collections/{collection:slug}`. Public read middleware group.
- [ ] 30. Re-route the existing topic-show endpoint to `GET /api/v1/collections/{collection:slug}/topics/{topic}` under `Route::scopeBindings()`; modify `ShowCollectionTopicController` to accept the `Collection` parameter and assert membership via the binding scope. Drop the legacy `GET /api/v1/collections` (topics list) public route — replaced by the parent collections list. Update `CollectionTopicResource` / `CollectionTopicDetailResource` to add `image_url`.
- [ ] 31. Admin Actions + Form Requests + Controllers for both collections (List, Create (201), Update, Delete (204)) and collection topics (nested under `{collection}`, with `scopeBindings`). `CreateCollectionTopicRequest` accepts `name`, `description`, optional `image_cdn_url` URL, optional `position` int. Routes `admin.collections.*` + `admin.collections.topics.*`.
- [ ] 32. `CollectionFactory` with `language()` state.
- [ ] 33. Feature tests: `ListCollectionsEndpointTest`, `ShowCollectionEndpointTest` (404 unknown slug, language-scoped), `ShowCollectionTopicEndpointTest` (cross-collection topic 404 via scopeBindings), `AdminCollectionsEndpointTest`, `AdminCollectionTopicsEndpointTest` (image_cdn_url accepted, FK SET NULL on collection delete leaves topics browsable).

### QR codes full Symfony model

- [ ] 34. Write `2026_05_03_002004_extend_qr_codes_for_full_symfony_shape.php` — add `place`, `base_url`, `source`, `destination`, `name`, `content`, `description` (with the placeholder defaults from the schema table); UPDATE `destination = url`, `content = url` for existing rows; alter `reference` to nullable; add UNIQUE `(place, source)` *only* if no two existing rows would collide on the empty defaults (engineer asserts via `count(*) <= 1` pre-check).
- [ ] 35. Modify `QrCode` model — fillable extended; reference now nullable; `imageUrl()` unchanged.
- [ ] 36. Modify `QrCodeQueryBuilder::forReference` — adds `whereNotNull('reference')` so reference-keyed lookups can't accidentally match a `NULL` row.
- [ ] 37. Rename `ShowQrCodeAction` → `ShowQrCodeByReferenceAction` (semantic rename — only consumer is `ShowQrCodeController`, update the import).
- [ ] 38. Add `App\Domain\QrCode\Events\QrCodeScanned` (broadcasts `qrCodeId`, `place`, `source`, `destination`, `scannedAt`).
- [ ] 39. Add `RecordQrCodeScanAction(QrCode)` — dispatches `QrCodeScanned`. Returns void.
- [ ] 40. Add `RecordQrCodeScanController` + `RecordQrCodeScanRequest` (empty body, `authorize() = true`); route `POST /api/v1/qr-codes/{qr}/scans` returning 204; under public read middleware group.
- [ ] 41. Modify `QrCodeResource::toArray` — emit `place`, `source`, `destination` (falls back to `url` while both columns coexist), `name`, `content`, `description`; keep `reference`, `image_url` untouched. `QrCodeListItemResource` now extends the same base via inheritance or shares a `toArray` helper (whichever the engineer judges leaner; current duplicate is fine to keep).
- [ ] 42. Admin DTOs (`CreateQrCodeData`, `UpdateQrCodeData`) + Actions (`ListAdminQrCodesAction` paginated; Create/Update/Delete).
- [ ] 43. Admin Form Requests: `ListAdminQrCodesRequest`, `CreateQrCodeRequest` (place required, source required, name required ≤50, destination URL required, content required text, base_url URL nullable, description nullable, reference optional valid Bible reference, image_path optional from presigned upload `key`), `UpdateQrCodeRequest` (sometimes), `DeleteQrCodeRequest`.
- [ ] 44. Admin Controllers + routes `admin.qr-codes.*` (list, store, update, destroy).
- [ ] 45. Feature tests: `RecordQrCodeScanEndpointTest` (`Event::fake([QrCodeScanned::class])`, asserts dispatch; 404 unknown qr; 204 on success). `ShowQrCodeEndpointTest` (existing `?reference=` flow still works, NULL-reference rows aren't returned). `AdminQrCodesEndpointTest` (CRUD + UNIQUE `(place, source)` collision returns 422 + 401/403 paths).

### Olympiad parity + user attempts

- [ ] 46. Write `2026_05_03_002005_extend_olympiad_questions_and_answers.php` — add `uuid` (nullable + UNIQUE), `verse`, `chapter`, `is_reviewed` + index on `olympiad_questions`; alter `chapters_from`, `chapters_to` to nullable; add `uuid` (nullable + UNIQUE) on `olympiad_answers`; backfill `Str::uuid()` for every NULL `uuid` row; alter `uuid` columns NOT NULL.
- [ ] 47. Write `2026_05_03_002006_create_olympiad_attempts_and_attempt_answers.php` — both tables per AC §25 / §26; FKs cascade where the AC requires; indices `(user_id, completed_at)`, `(language, book, chapters_label)`.
- [ ] 48. Modify `OlympiadQuestion` model — casts `chapter` int, `verse` int, `is_reviewed` bool, `uuid` string; add `forVerse(?int)` / scope cleanup; route key untouched (admin reorder still uses id).
- [ ] 49. Modify `OlympiadAnswer` model — `uuid` cast.
- [ ] 50. Add `OlympiadQuestionQueryBuilder::matchingTheme(string $book, ChapterRange $range, Language $language)` — `WHERE language = ? AND book = ? AND ((chapters_from = ? AND chapters_to = ?) OR (range.isSingleChapter() AND chapter = ?))`. Used by submit/finish for theme-membership validation.
- [ ] 51. Add `App\Domain\Olympiad\Models\OlympiadAttempt` — fillable from AC §25; casts (`started_at`/`completed_at` datetime, `language` Language enum); `user()` BelongsTo, `answers()` HasMany `OlympiadAttemptAnswer`; override `resolveRouteBinding` to scope `where('user_id', auth()->id())`.
- [ ] 52. Add `OlympiadAttemptAnswer` model — composite PK via `protected $primaryKey = ['attempt_id', 'olympiad_question_id'];` + `$incrementing = false`; `$keyType = 'int'`; `attempt()`, `question()`, `selectedAnswer()` BelongsTo.
- [ ] 53. Add `OlympiadAttemptQueryBuilder::forUser`, `forFilters(?Language, ?string $book, ?string $chaptersLabel)`, `newestFirst`.
- [ ] 54. Add DTOs: `StartOlympiadAttemptData`, `SubmitOlympiadAnswerLine`, `SubmitOlympiadAnswersData`, `ListOlympiadAttemptsFilter`.
- [ ] 55. Add Actions:
   - `StartOlympiadAttemptAction(StartOlympiadAttemptData)` — counts theme questions, persists attempt with `total = count`, returns `OlympiadAttempt` + `list<string> $questionUuids` (the locked-in UUID list, ordered deterministically — same `OlympiadQuestionQueryBuilder` order used by the public theme endpoint).
   - `SubmitOlympiadAttemptAnswersAction(SubmitOlympiadAnswersData)` — wrapped in `DB::transaction`, idempotent upsert on `(attempt_id, olympiad_question_id)`. Throws `OlympiadAttemptAlreadyFinishedException` when `completed_at` is set, `OlympiadAttemptThemeMismatchException` when a question UUID resolves to a question outside the attempt's theme, `OlympiadAnswerNotInQuestionException` when the selected_answer_uuid isn't a child of the question.
   - `FinishOlympiadAttemptAction(OlympiadAttempt)` — sets `completed_at = now()`; computes `score = COUNT(*) FROM attempt_answers WHERE is_correct AND attempt_id = ?`; throws `OlympiadAttemptAlreadyFinishedException` if already finished.
   - `ListUserOlympiadAttemptsAction(User, ListOlympiadAttemptsFilter, page, perPage)`.
   - `ListAdminOlympiadAttemptsAction(ListOlympiadAttemptsFilter, page, perPage)`.
- [ ] 56. Register the two new exceptions in `bootstrap/app.php` exception renderer (422 status).
- [ ] 57. Modify `OlympiadQuestionResource` — add `uuid`, `verse`, `chapter`, `is_reviewed`. Modify `OlympiadAnswerResource` — add `uuid`.
- [ ] 58. Add `OlympiadAttemptResource` (id, book, chapters_label, language, score, total, started_at, completed_at, answers when loaded) and `OlympiadAttemptStartResource` (extends with `question_uuids[]`).
- [ ] 59. Add Form Requests:
   - `StartOlympiadAttemptRequest` — book USFM-3, chapters as `ChapterRange::fromSegment(string)`, language ISO-2.
   - `SubmitOlympiadAttemptAnswersRequest` — `answers[]` with `question_uuid` UUID + `selected_answer_uuid` UUID nullable; min 1.
   - `FinishOlympiadAttemptRequest` — empty body.
   - `ListUserOlympiadAttemptsRequest` — paginated, optional language/book/chapters filters.
   - `ListAdminOlympiadAttemptsRequest` — same plus optional `user_id`.
- [ ] 60. Add Controllers + routes under `olympiad.attempts.*` with `auth:sanctum` + `resolve-language` + `throttle:per-user`; `scopeBindings` on the `{attempt}/...` group; admin route under `admin.olympiad.attempts.index`.
- [ ] 61. Add `OlympiadAttemptFactory` (and `OlympiadAttemptAnswerFactory`) for tests.
- [ ] 62. Feature tests: `StartOlympiadAttemptTest`, `SubmitOlympiadAttemptAnswersTest` (idempotent re-submit overwrites prior answer; cross-theme question UUID returns 422; submit after finish 422), `FinishOlympiadAttemptTest` (score computed; second finish 422), `ListUserOlympiadAttemptsTest` (pagination + filters + cross-user 404), `AdminListOlympiadAttemptsTest` (auth + filters).
- [ ] 63. Unit test `OlympiadQuestionQueryBuilderMatchingThemeTest` covering: range-only match, chapter-only match (single-chapter range hits `chapter = X`), mismatch (book/language), `isSingleChapter()` boundary (range "5-5" matches both chapter=5 rows and chapters_from=5/_to=5 rows). Pure SQL semantics not exhaustively covered by feature tests.

### Notes & Favorites colour

- [ ] 64. Write `2026_05_03_002007_add_color_to_notes_and_favorites.php` — `notes.color VARCHAR(9) NULL`, `favorites.color VARCHAR(9) NULL`. Down drops both.
- [ ] 65. Add `App\Http\Rules\HexColor` — invokable rule matching `/^#[0-9A-Fa-f]{6}([0-9A-Fa-f]{2})?$/`.
- [ ] 66. Modify Note + Favorite models, DTOs (`Create*Data` + `Update*Data` add `?string $color` and `bool $colorProvided` for partial-update semantics on Update), Actions (persist on create; partial on update).
- [ ] 67. Modify Form Requests: `StoreNoteRequest`, `UpdateNoteRequest`, `CreateFavoriteRequest`, `UpdateFavoriteRequest` — accept `color` `nullable` + `HexColor`. Resources expose `color`.
- [ ] 68. Feature tests: extend existing `*NotesEndpointTest` and `*FavoritesEndpointTest` with cases for accepted `#RRGGBB`, accepted `#RRGGBBAA`, rejected malformed, accepted `null` clearing on update.

### News detail + language defaults

- [ ] 69. Write `2026_05_03_002008_backfill_news_language_and_published_at.php` — `UPDATE news SET language='ro' WHERE language IS NULL`; `UPDATE news SET published_at = created_at WHERE published_at IS NULL`. (`language` column is already NOT NULL CHAR(2) in current schema; this migration is defensive for any pre-MBA-023 rows that slipped through.) Migration test asserts ro and chronological order preserved.
- [ ] 70. Modify `News` model — override `resolveRouteBinding($value, $field)` to apply `published()` scope so unpublished detail 404s.
- [ ] 71. Add `ShowNewsAction(News)` — cached 5 min via `NewsCacheKeys::show($id)` tagged `['news']`. Returns array via `NewsDetailResource`.
- [ ] 72. Add `NewsDetailResource` (full content; same shape as list resource for now — left as a separate class so list can later trim `content` without breaking detail).
- [ ] 73. Add `ShowNewsRequest` (empty rules, `authorize=true`) + `ShowNewsController` + route `GET /api/v1/news/{news}` under the existing news middleware group (cache headers 5 min).
- [ ] 74. Feature test `ShowNewsEndpointTest` — happy path, 404 unpublished, 404 unknown, response shape includes `content`.

### Cleanup + gate

- [ ] 75. Run `make lint-fix` + `make stan` + `make test-api filter=Devotional`, `filter=Mobile`, `filter=Collection`, `filter=QrCode`, `filter=Olympiad`, `filter=Note`, `filter=Favorite`, `filter=News`; finally full `make test-api` before handing off.

## Risks & open questions

- **Devotional `type` enum coupling.** `App\Domain\Devotional\Enums\DevotionalType` is currently used by `Devotional` model casts, `FetchDevotionalData`, `DevotionalCacheKeys::show()` / `tagsForDevotional()`, and `ShowAppBootstrapAction` (passes `DevotionalType::Adults` / `::Kids` to `FetchDevotionalData`). The migration to `int $typeId` ripples through all of these. Bootstrap is an internal caller — engineer updates it to call `ResolveDevotionalTypeAction->handle('adults', $language)` to map the legacy slugs to ids before fetching. The enum class can be deleted at MBA-032 alongside the column drop.
- **Mobile bootstrap `version` shape.** Bootstrap currently emits `{ ios: <string>, android: <string> }` keyed by latest version only. Repository's `latestVersionFor()` returns the same string per platform; bootstrap shape unchanged. Stakeholder-visible: no.
- **`qr_codes.url` → `qr_codes.destination` deprecation window.** Two columns coexist until MBA-032 §6 drops `url`. Admin writes set both (Update Action mirrors `destination` into `url` as a transitional safeguard). Reads serve `destination ?? url` so admin flips of `destination` work even before `url` is removed. The `?reference=` lookup uses `destination` for the `url` field in the response.
- **Olympiad backwards compat for clients still on `id`.** Existing public theme endpoint serves `id` on questions / answers; this story adds `uuid` *additively*. Mobile clients can keep using `id` until they upgrade. Attempts persistence accepts UUIDs externally as the locking key — clients on stale builds simply don't get attempts persistence (anonymous behaviour).
- **`olympiad_questions.chapters_from` / `chapters_to` nullable.** A few existing `OlympiadQuestionQueryBuilder` callsites assume these are populated (`themes()` projection, `forChapterRange()` predicate). They remain populated for range-mode rows; chapter-mode rows are new and excluded from `themes()` until the admin starts adding them. Engineer should verify `themes()` still produces sensible groupings (skip rows where both columns are NULL, or coalesce to `chapter`). Decision is one line in `OlympiadQuestionQueryBuilder::themes()`.
- **`mobile_versions` seed UNIQUEness.** Seeding from config relies on the unique `(platform, kind)` pair; if MBA-031's `EtlMobileVersionsSeedJob` later inserts a Symfony row with the same `(platform, kind)`, it must use `INSERT ... ON DUPLICATE KEY UPDATE`. Out of this story's scope (MBA-031), but flagged.
- **`UNIQUE (place, source)` on existing rows.** Pre-existing `qr_codes` rows all have `place=''`, `source=''` after the column-add. Adding the UNIQUE will fail if there's more than one row. Migration pre-asserts `count > 1` rows with empty `(place, source)` and aborts with a clear error message — operator must run a one-shot SQL to clear duplicates before re-running. Acceptable for a development environment; production cutover is run cleanly once.
- **Deferred-extraction tripwire.** No new copy of the owner-`authorize()` block (Olympiad attempts use `resolveRouteBinding` scoping, not Form Request owner-checks). No new copy of `withProgressCounts()`. Counts unchanged.

## References

- MBA-024 plan — public/admin Resource split, `resolveRouteBinding` precedent for slug-bound public + id-bound admin.
- MBA-023 reconcile migrations — `ReconcileTableHelper`, table renames, `BackfillLegacyBookAbbreviationsAction` precedent.
- MBA-031 ETL story — Symfony-derived data movement (devotional types, mobile versions seed cross-check, collection parent linkage, image_cdn_url, Olympiad UUID re-validation, news language default, notes/favorites colour from Symfony rows).
- MBA-032 cleanup story — drops `devotionals.type` (string), `qr_codes.url`, `users.language` once soak window confirms no caller drift.
- `Admin/SabbathSchool/ReorderLessonSegmentsController` + `ReorderRequest` — reorder pattern for `devotional-types/reorder`.
- `EnsureSuperAdmin` middleware (`super-admin` alias) — admin gate for every write endpoint in this story.
- `PaginatesRead` trait + `ResolveRequestLanguage` middleware key — list-endpoint plumbing reused throughout.
