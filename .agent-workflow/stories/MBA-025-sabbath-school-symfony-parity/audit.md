# Audit: MBA-025-sabbath-school-symfony-parity

**Verdict:** PASS

Final holistic pass after Review (APPROVE) and QA (PASSED). Re-walked the
8 schema migrations, the reshaped highlight + favorite + answer
write-paths, the new Trimester / Lesson / Segment-Content admin surface,
the partial-PATCH DTOs, the cache key + tag invalidation flow, the
Resource fallback shape on segments, and the migration / unit / feature
test coverage. Ran `make lint` (clean), `make stan` (no errors), and the
full API test suite (**1160 tests, 4227 assertions, 45.29s**) — no
regressions. The story is good to ship; the schema cleanup queue tracked
in the deferred-extractions tripwire is correctly scoped to MBA-031 +
MBA-032.

---

## Findings

| # | Issue | Location | Severity | Status | Resolution |
|---|---|---|---|---|---|
| 1 | `ShowSabbathSchoolTrimesterController` reads the route param as a raw string and casts to `int`, bypassing implicit route-model binding so the language-scoped `findOrFail` in `ShowTrimesterAction` is the only fetch. Non-numeric ids fall through to a 404 via `findOrFail(0)` instead of a 404 from the route regex. | `app/Http/Controllers/Api/V1/SabbathSchool/ShowSabbathSchoolTrimesterController.php:22` | Suggestion | Skipped | Intentional design choice — language scoping requires post-binding scoping, and re-binding then re-querying would double the cost. Adding `->where('trimester', '[0-9]+')` to the route would be a marginal cosmetic win; deferred as it's strictly stylistic. |
| 2 | `UpdateLessonData::from()` short-circuits on `=== null` for `trimesterId`, `memoryVerse`, `imageCdnUrl`, `publishedAt`. The `present` array still includes the key, so `toArray()` still emits `null` — explicit-null PATCH (e.g. unpublish) does work. The constraint is the FormRequest's `nullable` rule, not the DTO. | `app/Domain/SabbathSchool/DataTransferObjects/UpdateLessonData.php:48-59` | Suggestion | Skipped | Verified by reading the DTO end-to-end: explicit `null` round-trips correctly via `array_intersect_key($map, array_flip($this->present))`. Review's worry about "DTO more restrictive than the validator implies" does not bite in practice. |
| 3 | Admin list controllers (`ListAdminLessonsController`, `ListAdminTrimestersController`, `ListSegmentContentsController`) build queries inline — bends the "Controllers contain no business logic" rule but matches the public `ListSabbathSchoolLessonsController` precedent. | `app/Http/Controllers/Api/V1/Admin/SabbathSchool/ListAdminLessonsController.php:16-44` (and siblings) | Suggestion | Skipped | Already acknowledged in `review.md` as in-line with existing repo precedent, not a regression introduced by this story. Cleanup target for a future sweep. |
| 4 | Highlight UNIQUE `(user_id, segment_content_id, start_position, end_position, deleted_at)` allows multiple live rows under MySQL semantics (`NULL ≠ NULL` in unique constraints). | `database/migrations/2026_05_03_002005_evolve_sabbath_school_highlights_for_offsets.php:60-66` | Suggestion | Skipped | The serialisation point is `ToggleSabbathSchoolHighlightAction` which wraps the find + create in `DB::transaction` + `lockForUpdate`. Including `deleted_at` is necessary so toggle-off-then-on can recreate (covered by `test_toggling_off_then_on_recreates_the_highlight`). Tightening this further (functional unique on `(user, content, start, end)` filtered to `deleted_at IS NULL`) would re-break the soft-delete restore path. Current shape is correct. |
| 5 | `ToggleSabbathSchoolHighlightAction::execute()` writes `passage = null` explicitly on every create. | `app/Domain/SabbathSchool/Actions/ToggleSabbathSchoolHighlightAction.php:48` | Suggestion | Skipped | Necessary while the legacy `passage` column is kept NOT NULL on fresh installs prior to migration 002005's relaxation. Removed by MBA-032 alongside the column drop. Already tracked in the deferred-extractions tripwire. |
| 6 | `UpdateSegmentContentAction` calls `$content->segment()->value('sabbath_school_lesson_id')` — single column-only query per write. | `app/Domain/SabbathSchool/Actions/UpdateSegmentContentAction.php:18` | Suggestion | Skipped | Single column-only round-trip is cheap, and the alternative (eager-loading from the controller) would couple the controller to the cache flush concern. Already noted in `review.md`. |
| 7 | `SabbathSchoolFavorite` casts `sabbath_school_segment_id` to `'integer'` after the column became nullable. | `app/Domain/SabbathSchool/Models/SabbathSchoolFavorite.php:42-47` | Suggestion | Skipped | Verified via tests: Laravel 11 `'integer'` cast preserves `null`. `whole_lesson` flag in the Resource resolves correctly off the nullable cast (`SabbathSchoolFavoriteTest` exercises both NULL and non-NULL paths). |

No Critical findings. No Warnings. Each Suggestion accounted for above.

---

## Dimensions checked

- **Architecture compliance.** Beyond CRUD layers (Actions/DTOs/QB) respected; partial-PATCH DTOs (`UpdateTrimesterData::$present`) are a pragmatic deviation from §31's plan-of-record but were the right call — preserves explicit-null PATCH semantics without leaking `$guarded = []` to raw payloads.
- **Code quality.** Strict types, explicit return types, final classes throughout. PHPDoc property blocks on models match the new schema. No dead branches found.
- **API design.** Verbs and status codes line up: 201 on create (lesson, trimester, segment content), 204 on delete, 200 on list/show/update. Form Requests + Resources used everywhere. JSON error envelope honoured by the global handler. `/api/v1` prefix preserved.
- **Security.** Highlight route binding fails closed when `request()->user()` is null; cross-user PATCH 404s. Answer upsert validates `content.type === 'question'` via `withValidator()->after(...)` so non-question payloads return 422, not 403. Admin endpoints gated by `auth:sanctum + admin`.
- **Performance.** `withLessonDetail()` eager-loads `segments.segmentContents`. Trimester/lesson list responses cached via `CachedRead` with language-scoped keys + tag invalidation. No N+1 added (lesson detail test asserts the budget).
- **Test coverage.** 116 SabbathSchool tests cover trimester CRUD (admin + public), lesson CRUD, segment-content CRUD + reorder, highlight toggle + PATCH, favourites NULL/non-NULL coexistence, answer rebinding, migration backfills (`for_date`, favourites sentinel → NULL). Soft-delete + unique-index regression covered by `test_toggling_off_then_on_recreates_the_highlight`.

---

## Verification

- `make lint` — PASS (938 files).
- `make stan` — PASS (no errors, 914 files).
- `make test-api filter=SabbathSchool` — 116 passed, 345 assertions, 8.76s.
- `make test-api` — 1160 passed, 4227 assertions, 45.29s, no regressions.

---

## Verdict

All Critical/Warning resolved (none introduced this pass). Every Suggestion accounted for. Status transition: `qa-passed` → `done`.
