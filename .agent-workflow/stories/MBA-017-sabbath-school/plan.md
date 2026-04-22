# Plan: MBA-017-sabbath-school

## Approach

Port Sabbath School as a single `App\Domain\SabbathSchool` domain with six models (lesson, segment, question, answer, highlight, favorite). Public lesson catalog endpoints use `api-key-or-sanctum` + `resolve-language`, and route-model bind scoped to published lessons; caller-data endpoints (answers, highlights, favorites) require Sanctum and enforce ownership via Form Request `authorize()`. Highlight/favorite toggle endpoints follow the MBA-009 sentinel pattern so `(user_id, lesson_id, segment_id)` remains unique when `segment_id` is omitted. Passage strings on highlights are validated through the existing `App\Domain\Reference\Parser\ReferenceParser` at write time; parse failures surface as `422`.

## Open questions — resolutions

1. **Lesson vs segment-level favorites.** Adopt the MBA-009 sentinel: `segment_id = 0` for "whole lesson", non-zero for a specific segment. Unique index on `(user_id, lesson_id, segment_id)`. Two favorites (lesson and one of its segments) are allowed and count as two rows — that matches the Symfony UX where a user can bookmark both the week and a specific day.
2. **Answer editing history.** Overwrite on conflict. Matches Symfony; history is out of scope. Store a single row per `(user_id, question_id)` with `updated_at` tracking the last edit.
3. **Language scope of highlights/favorites.** Highlights stored against the segment (language-scoped implicitly via the segment's lesson). Favorites stored against lesson/segment ids — also language-scoped implicitly. No separate language column is stored on the user-data tables.
4. **Toggle response envelopes.** `POST /highlights/toggle` and `POST /favorites/toggle` return `201` with the created row's resource on insert, `200` with `{ deleted: true }` on delete — mirroring MBA-009 semantics.
5. **Lesson route key.** `{lesson}` binds on `id` (Symfony PK). Lessons are dated content and do not have a human slug. Use `scopeBindings()` where needed; the resolver is the lesson model's published scope (`resolveRouteBinding` with a `published()` query builder).

## Domain layout

```
app/Domain/SabbathSchool/
├── Models/
│   ├── SabbathSchoolLesson.php
│   ├── SabbathSchoolSegment.php
│   ├── SabbathSchoolQuestion.php
│   ├── SabbathSchoolAnswer.php
│   ├── SabbathSchoolHighlight.php
│   └── SabbathSchoolFavorite.php
├── QueryBuilders/
│   ├── SabbathSchoolLessonQueryBuilder.php       # published(), forLanguage(Language), withLessonDetail()
│   ├── SabbathSchoolAnswerQueryBuilder.php       # forUser(User), forQuestion(int)
│   ├── SabbathSchoolHighlightQueryBuilder.php    # forUser(User), forSegment(int)
│   └── SabbathSchoolFavoriteQueryBuilder.php     # forUser(User)
├── Actions/
│   ├── UpsertSabbathSchoolAnswerAction.php
│   ├── DeleteSabbathSchoolAnswerAction.php
│   ├── ToggleSabbathSchoolHighlightAction.php
│   └── ToggleSabbathSchoolFavoriteAction.php
├── DataTransferObjects/
│   ├── UpsertSabbathSchoolAnswerData.php
│   ├── ToggleSabbathSchoolHighlightData.php
│   └── ToggleSabbathSchoolFavoriteData.php
├── Support/
│   └── SabbathSchoolFavoriteSentinel.php          # public const WHOLE_LESSON = 0
└── Exceptions/
    └── InvalidSabbathSchoolPassageException.php   # wraps InvalidReferenceException for 422 mapping
```

## Key types

| Type | Role |
|---|---|
| `SabbathSchoolLesson` | Fields: `id`, `language`, `title`, `week_start`, `week_end`, `published_at`. Scopes via QueryBuilder. `segments()` HasMany. `resolveRouteBinding()` limits to `published()`. |
| `SabbathSchoolSegment` | Fields: `id`, `lesson_id`, `day`, `title`, `content`, `passages` (json). `lesson()` BelongsTo, `questions()` HasMany ordered by `position`. |
| `SabbathSchoolQuestion` | Fields: `id`, `segment_id`, `position`, `prompt`. `segment()` BelongsTo. |
| `SabbathSchoolAnswer` | Fields: `id`, `user_id`, `question_id`, `content`, `created_at`, `updated_at`. Unique `(user_id, question_id)`. |
| `SabbathSchoolHighlight` | Fields: `id`, `user_id`, `segment_id`, `passage` (canonical string), timestamps. |
| `SabbathSchoolFavorite` | Fields: `id`, `user_id`, `lesson_id`, `segment_id` (0 = whole lesson), timestamps. Unique `(user_id, lesson_id, segment_id)`. |
| `SabbathSchoolLessonQueryBuilder` | `published()` — `published_at IS NOT NULL AND <= now()`; `forLanguage(Language)`; `withLessonDetail()` — eager loads `segments.questions` (N+1 guard per AC). |
| `SabbathSchoolAnswerQueryBuilder` | `forUser(User)`, `forQuestion(int $questionId)`. |
| `SabbathSchoolHighlightQueryBuilder` | `forUser(User)`, `forSegment(int)`. |
| `SabbathSchoolFavoriteQueryBuilder` | `forUser(User)`. |
| `UpsertSabbathSchoolAnswerAction` | `execute(User, SabbathSchoolQuestion, UpsertSabbathSchoolAnswerData): SabbathSchoolAnswer` — `updateOrCreate` on `(user_id, question_id)`. Returns the row and a flag indicating insert vs update (for 201 vs 200 in controller). |
| `DeleteSabbathSchoolAnswerAction` | `execute(User, SabbathSchoolQuestion): void` — no-op-safe delete (controller is responsible for 404 when the answer doesn't exist). |
| `ToggleSabbathSchoolHighlightAction` | `execute(User, ToggleSabbathSchoolHighlightData): ToggleResult` — result names whether a row was created (for 201) or deleted (for 200). Parses `passage` with `ReferenceParser::parseOne()`; rethrows as `InvalidSabbathSchoolPassageException` (mapped to 422 by exception handler). |
| `ToggleSabbathSchoolFavoriteAction` | `execute(User, ToggleSabbathSchoolFavoriteData): ToggleResult` — missing `segment_id` stored as sentinel `0`. |
| `SabbathSchoolFavoriteSentinel` | `public const int WHOLE_LESSON = 0;` Single named source for the sentinel. Consumed by the action, form request, resource, and migration seed literals. |
| `InvalidSabbathSchoolPassageException` | Extends `\RuntimeException`; wraps `InvalidReferenceException` so the HTTP handler can render `422` without leaking Domain internals. |

## HTTP endpoints

| Method | Path | Middleware | Controller | FormRequest | Resource |
|---|---|---|---|---|---|
| GET | `/api/v1/sabbath-school/lessons` | `api-key-or-sanctum`, `resolve-language` | `ListSabbathSchoolLessonsController` | `ListSabbathSchoolLessonsRequest` | `SabbathSchoolLessonSummaryResource` (collection) |
| GET | `/api/v1/sabbath-school/lessons/{lesson}` | `api-key-or-sanctum`, `resolve-language` | `ShowSabbathSchoolLessonController` | `ShowSabbathSchoolLessonRequest` | `SabbathSchoolLessonResource` |
| GET | `/api/v1/sabbath-school/questions/{question}/answer` | `auth:sanctum` | `ShowSabbathSchoolAnswerController` | `ShowSabbathSchoolAnswerRequest` | `SabbathSchoolAnswerResource` |
| POST | `/api/v1/sabbath-school/questions/{question}/answer` | `auth:sanctum` | `UpsertSabbathSchoolAnswerController` | `UpsertSabbathSchoolAnswerRequest` | `SabbathSchoolAnswerResource` |
| DELETE | `/api/v1/sabbath-school/questions/{question}/answer` | `auth:sanctum` | `DeleteSabbathSchoolAnswerController` | `DeleteSabbathSchoolAnswerRequest` | — (204) |
| POST | `/api/v1/sabbath-school/highlights/toggle` | `auth:sanctum` | `ToggleSabbathSchoolHighlightController` | `ToggleSabbathSchoolHighlightRequest` | `SabbathSchoolHighlightResource` on insert; `{ deleted: true }` on delete |
| GET | `/api/v1/sabbath-school/highlights` | `auth:sanctum` | `ListSabbathSchoolHighlightsController` | `ListSabbathSchoolHighlightsRequest` | `SabbathSchoolHighlightResource` (collection) |
| POST | `/api/v1/sabbath-school/favorites/toggle` | `auth:sanctum` | `ToggleSabbathSchoolFavoriteController` | `ToggleSabbathSchoolFavoriteRequest` | `SabbathSchoolFavoriteResource` on insert; `{ deleted: true }` on delete |
| GET | `/api/v1/sabbath-school/favorites` | `auth:sanctum` | `ListSabbathSchoolFavoritesController` | `ListSabbathSchoolFavoritesRequest` | `SabbathSchoolFavoriteResource` (collection) |

Route-model binding notes:
- `{lesson}`: the model's `resolveRouteBinding` applies `published()`. Chosen over a scoped-binding closure because lesson publication is a business invariant, not a request-scoped filter — same pattern as `ReadingPlan`.
- `{question}`: default binding on `id`. Scoping is not required because any authenticated user may answer any published question (answer rows are scoped by `user_id`, not by question visibility). The Form Request additionally guards that the question's parent lesson is `published()` so answers cannot be attached to draft content.

Caching:
- `Cache-Control: public, max-age=3600` header applied to lesson listing and detail controllers only. Added either via a dedicated `CacheResponse` middleware or `->header()` on the returned response — pick whichever is simpler given existing code (no such middleware exists yet, so set it inline in the controller).

## Data & migrations

New tables (snake_case per MBA-005 convention; reconcile any legacy camelCase during port):

1. `sabbath_school_lessons` — `id`, `language` (enum or string), `title`, `week_start` (date), `week_end` (date), `published_at` (nullable timestamp), timestamps. Index on `(language, published_at)` for the catalog listing.
2. `sabbath_school_segments` — `id`, `lesson_id` FK cascade, `day` (tinyint 0–6), `title`, `content` (longtext, HTML per technical notes), `passages` (json), `position` (for stable ordering), timestamps. Index on `(lesson_id, position)`.
3. `sabbath_school_questions` — `id`, `segment_id` FK cascade, `position`, `prompt`, timestamps. Index on `(segment_id, position)`.
4. `sabbath_school_answers` — `id`, `user_id` FK cascade, `question_id` FK cascade, `content` (text), timestamps. Unique `(user_id, question_id)`.
5. `sabbath_school_highlights` — `id`, `user_id` FK cascade, `segment_id` FK cascade, `passage` (string, canonical form e.g. `GEN.1:1.VDC`), timestamps. Index on `(user_id, segment_id)`.
6. `sabbath_school_favorites` — `id`, `user_id` FK cascade, `lesson_id` FK cascade, `segment_id` (unsigned int, default 0, `0` = whole lesson sentinel), timestamps. Unique `(user_id, lesson_id, segment_id)`. No FK on `segment_id` (sentinel 0 would fail); document in migration comment.

Legacy data: if the Symfony `sabbath_school_*` tables already exist in the shared DB, the port must either (a) create new tables with a one-off copy migration, or (b) rename/reshape the existing tables per MBA-005 reconciliation. Engineer to inspect with `database-schema` on first implementation pass.

## Tasks

- [ ] 1. Inspect the legacy `sabbath_school_*` tables via `mcp__laravel-boost__database-schema` and confirm the column list for each table. Note any camelCase columns that need renaming to snake_case.
- [ ] 2. Create migrations for `sabbath_school_lessons`, `sabbath_school_segments`, `sabbath_school_questions` with the columns and indexes listed in Data & migrations. Include FKs with cascade.
- [ ] 3. Create migrations for `sabbath_school_answers`, `sabbath_school_highlights`, `sabbath_school_favorites` with the unique indexes and sentinel default on `segment_id`.
- [ ] 4. Create `SabbathSchoolLesson` model with `segments()` HasMany, `resolveRouteBinding()` scoped to `published()`, and `newEloquentBuilder()` returning `SabbathSchoolLessonQueryBuilder`.
- [ ] 5. Create `SabbathSchoolSegment` and `SabbathSchoolQuestion` models with their BelongsTo / HasMany relations and positional ordering on `questions()`.
- [ ] 6. Create `SabbathSchoolAnswer`, `SabbathSchoolHighlight`, `SabbathSchoolFavorite` models with their respective QueryBuilders and relations.
- [ ] 7. Create `SabbathSchoolLessonQueryBuilder` with `published()`, `forLanguage(Language)`, and `withLessonDetail()`. Verify `withLessonDetail()` eager-loads `segments.questions` via a DB-query count assertion in the feature test for AC 2.
- [ ] 8. Create `SabbathSchoolAnswerQueryBuilder`, `SabbathSchoolHighlightQueryBuilder`, `SabbathSchoolFavoriteQueryBuilder` with the scopes listed in Key types.
- [ ] 9. Create `SabbathSchoolFavoriteSentinel` with `public const int WHOLE_LESSON = 0`.
- [ ] 10. Create `InvalidSabbathSchoolPassageException` and wire it into `bootstrap/app.php` to render as `422` with the standard error envelope.
- [ ] 11. Create DTOs `UpsertSabbathSchoolAnswerData`, `ToggleSabbathSchoolHighlightData`, `ToggleSabbathSchoolFavoriteData` as readonly classes.
- [ ] 12. Create `UpsertSabbathSchoolAnswerAction` implementing `updateOrCreate` semantics; return the row with an "inserted" flag for the controller's status-code decision.
- [ ] 13. Create `DeleteSabbathSchoolAnswerAction` that deletes the caller's single answer row (no-op safe).
- [ ] 14. Create `ToggleSabbathSchoolHighlightAction`. Parse `passage` via `App\Domain\Reference\Parser\ReferenceParser::parseOne()`; rethrow as `InvalidSabbathSchoolPassageException`. Toggle on `(user_id, segment_id, passage)`.
- [ ] 15. Create `ToggleSabbathSchoolFavoriteAction`. Coalesce missing `segment_id` to `SabbathSchoolFavoriteSentinel::WHOLE_LESSON`. Toggle on `(user_id, lesson_id, segment_id)`.
- [ ] 16. Create `ListSabbathSchoolLessonsRequest` (pagination + language attribute read from `ResolveRequestLanguage::ATTRIBUTE_KEY`), `ShowSabbathSchoolLessonRequest` (no body; language attribute reader).
- [ ] 17. Create `ShowSabbathSchoolAnswerRequest`, `UpsertSabbathSchoolAnswerRequest` (validates `content` max 10000; `authorize` confirms question's lesson is published), `DeleteSabbathSchoolAnswerRequest`.
- [ ] 18. Create `ToggleSabbathSchoolHighlightRequest` (validates `segment_id` exists, `passage` is a non-empty string — full parse runs in the Action), `ListSabbathSchoolHighlightsRequest` (required `segment_id`).
- [ ] 19. Create `ToggleSabbathSchoolFavoriteRequest` (validates `lesson_id` exists, `segment_id` optional and must belong to the given lesson), `ListSabbathSchoolFavoritesRequest`.
- [ ] 20. Create API resources `SabbathSchoolLessonSummaryResource` (list shape: id, title, week_start, week_end, language), `SabbathSchoolLessonResource` (detail shape per AC 2 including `segments.questions`).
- [ ] 21. Create `SabbathSchoolAnswerResource`, `SabbathSchoolHighlightResource`, `SabbathSchoolFavoriteResource` with the fields listed in Key types; exclude `user_id` from the payload.
- [ ] 22. Create `ListSabbathSchoolLessonsController` and `ShowSabbathSchoolLessonController`; both set `Cache-Control: public, max-age=3600` on the response.
- [ ] 23. Create `ShowSabbathSchoolAnswerController`, `UpsertSabbathSchoolAnswerController` (201 on insert, 200 on update), `DeleteSabbathSchoolAnswerController` (204).
- [ ] 24. Create `ToggleSabbathSchoolHighlightController` and `ListSabbathSchoolHighlightsController`. Toggle returns `201` + resource on insert, `200` + `{ deleted: true }` on delete.
- [ ] 25. Create `ToggleSabbathSchoolFavoriteController` and `ListSabbathSchoolFavoritesController` with the same status code contract.
- [ ] 26. Wire routes in `routes/api.php` under the `v1` prefix. Group lesson catalog under `api-key-or-sanctum` + `resolve-language`; group caller-data endpoints under `auth:sanctum`. Use `scopeBindings()` where `{question}` nests under a parent in future but not needed here (flat paths).
- [ ] 27. Create factories for each of the six models under `database/factories/`.
- [ ] 28. Feature test: lesson listing filters by `language`, paginates at 30, returns newest first; `Cache-Control` header set; 200 under api-key and 200 under Sanctum.
- [ ] 29. Feature test: lesson detail returns the nested `segments.questions` shape from AC 2; DB query count assertion proves `withLessonDetail()` avoids N+1 on a 7-segment × 5-question fixture.
- [ ] 30. Feature test: answer upsert — first POST returns 201, second POST overwrites and returns 200 with the new `content`; GET returns the caller's answer; DELETE returns 204.
- [ ] 31. Feature test: answer endpoints reject cross-user access (User B cannot read, overwrite, or delete User A's answer — returns 404, never 403 with identifying info).
- [ ] 32. Feature test: highlight toggle — first POST returns 201 with resource, second POST with the same `segment_id` + `passage` returns 200 + `{ deleted: true }`; list endpoint returns only caller's highlights.
- [ ] 33. Feature test: highlight rejects unparseable `passage` as 422 via `InvalidSabbathSchoolPassageException`.
- [ ] 34. Feature test: favorite toggle with `segment_id` omitted stores sentinel 0 (whole lesson); toggling again removes it; toggling with a `segment_id` creates a second row independent of the whole-lesson favorite.
- [ ] 35. Feature test: anonymous (no auth) requests to every caller-data endpoint return 401.
- [ ] 36. Unit test `UpsertSabbathSchoolAnswerAction`: insert branch, update branch, content is trimmed/untrimmed as spec'd.
- [ ] 37. Unit test `ToggleSabbathSchoolHighlightAction`: insert branch, delete branch, invalid passage wraps `InvalidReferenceException` as `InvalidSabbathSchoolPassageException`.
- [ ] 38. Unit test `ToggleSabbathSchoolFavoriteAction`: sentinel insert, sentinel delete, segment-scoped insert alongside sentinel row.
- [ ] 39. Run `make lint-fix`, `make stan`, then the SabbathSchool filter `make test filter=SabbathSchool`; finally `make test` before handing off.

## Risks & notes

- **Legacy schema shape.** The Symfony `sabbath_school_*` tables exist in the shared DB but the local test DB has none of them. Task 1 must inspect the real schema before migrations are authored; mismatched column names are the most likely source of rework. The schema in §Data & migrations is the target shape, not a verbatim port.
- **`passages` column on segments.** Stored as `json` array of canonical strings. Parsing these into `Reference` objects at read time is not required by this story — the client renders them as strings or routes them through a future linkify pass. Do not introduce a read-time parser until a consumer appears.
- **Highlight passage parsing at write time.** Uses `ReferenceParser::parseOne()` — strict single-reference form. If the Symfony UI sent multi-reference strings (`GEN.1:1;2:3`), this will reject them. Confirm by inspecting a handful of `sabbath_school_highlight` rows during Task 1; if multi-refs are present, switch to `ReferenceParser::parse()` and store the canonical string of the first match (or defer a decision).
- **Favorites sentinel interacts with MySQL unique semantics.** Because `segment_id = 0` is a real value (not NULL), the unique index `(user_id, lesson_id, segment_id)` behaves correctly under MySQL without needing a generated column. No FK on `segment_id` — comment in the migration.
- **Cache-Control on lesson endpoints.** Setting `max-age=3600` on responses that also accept a Sanctum token means intermediaries can cache personal-data-free responses. The lesson payload contains no per-user data — safe. Keep `public` cache directive.
- **Cross-user answer access returns 404, not 403.** Hiding existence is the preferred posture for per-user resources. Document in the Form Request's `failedAuthorization()` override or in the controller's 404 path.
- **Answer content length limit.** 10 000 chars (matches MBA-011 notes limit per story technical notes). Validated at the Form Request; no schema limit beyond `TEXT` (65 535 bytes).
- **Owner-`authorize()` tripwire.** This story adds four more owner-gated Form Requests (`UpsertSabbathSchoolAnswerRequest`, `DeleteSabbathSchoolAnswerRequest`, and implicitly the toggle requests if they ever resolve by id). Deferred Extractions §7 is at 4/5 for the owner-authorize pattern; extract the shared check into a trait or base Form Request **before** adding the 5th instance. Track explicitly in this story if the count crosses the threshold.
- **Language resolution precedent.** Lesson list/show use `ResolveRequestLanguage::ATTRIBUTE_KEY` via `$request->attributes->get(ResolveRequestLanguage::ATTRIBUTE_KEY, Language::En)` in the Form Request — matches ReadingPlans precedent. Do not read from `$request->query('language')` directly.
