# QA: MBA-025-sabbath-school-symfony-parity

**Verdict:** QA PASSED

**Test Results:**
- **SabbathSchool suite:** 116 tests passed (345 assertions)
- **Full API suite:** 1160 tests passed (4227 assertions)
- **Duration:** 102.91s
- **No regressions detected**

---

## Acceptance Criteria Coverage

### Trimester (AC §1-5)
✓ `TrimesterCrudTest.php` — admin CRUD for trimesters, validation, creation with all fields (year, language, age_group, title, number, date_from, date_to, image_cdn_url)
✓ `ListSabbathSchoolTrimestersTest.php` — public list endpoint with language scoping, cache headers, order (year DESC, number DESC)
✓ `ShowSabbathSchoolTrimesterTest.php` — public detail endpoint with nested lessons

### Section explicit date (AC §6-7)
✓ `EvolveSabbathSchoolSegmentsForForDateTest.php` — for_date backfill from lesson.date_from + day days; null day preserved; idempotent rerun
✓ `ShowSabbathSchoolLessonTest.php` — for_date and day both exposed in segment response

### Typed content blocks (AC §8-13)
✓ `SegmentContentCrudTest.php` — admin CRUD for segment contents (create, update, delete) with type validation (text, question, memory_verse, etc.)
✓ `ShowSabbathSchoolLessonTest.php` — lesson detail with nested `segments[].contents[]` array in position order; legacy `segments[].content` fallback when contents empty
✓ `LessonCrudTest.php` — lesson admin endpoints extended for new fields

### Intra-text highlights (AC §14-18)
✓ `SabbathSchoolHighlightTest.php` — highlight toggle with segment_content_id, start_position, end_position, color
✓ `ToggleSabbathSchoolHighlightActionTest.php` — unit tests for toggle semantics: identical (user, content, range) deletes; different range creates; soft-delete + unique with deleted_at included
✓ `PatchSabbathSchoolHighlightColorActionTest.php` — color-only PATCH endpoint
✓ Migration test confirms passage column nullable; legacy rows can coexist

### Favorites cleanup (AC §19-20)
✓ `SabbathSchoolFavoriteTest.php` — favorite toggle with nullable segment_id
✓ `ToggleSabbathSchoolFavoriteActionTest.php` — unit tests for NULL (whole-lesson) vs non-null (per-segment); functional unique index permits both coexisting
✓ `RelaxFavoritesSegmentUniquenessTest.php` — migration test verifies sentinel 0 → NULL; partial-uniqueness enforcement; same (user, lesson) can have both whole-lesson and per-segment favorites

### Admin endpoints (AC §21-23)
✓ `TrimesterCrudTest.php` — GET/POST/PATCH/DELETE trimester endpoints with auth, language scoping, validation
✓ `LessonCrudTest.php` — GET/POST/PATCH/DELETE lesson endpoints; admin binding bypasses published() gate
✓ `SegmentContentCrudTest.php` — GET/POST/PATCH/DELETE segment-content endpoints; type validation; ReorderTest.php covers reorder endpoint

### Public endpoints (AC §25-27)
✓ `ListSabbathSchoolTrimestersTest.php` — GET /api/v1/sabbath-school/trimesters with ?language= filter
✓ `ShowSabbathSchoolTrimesterTest.php` — GET /api/v1/sabbath-school/trimesters/{trimester} with nested lessons
✓ `ListSabbathSchoolLessonsTest.php` — accepts optional ?trimester= and ?age_group= filters

### Tests (AC §28-31)
✓ Feature tests for trimester CRUD (auth, validation, happy path, language scoping)
✓ Migration tests for for_date backfill, questions → content blocks, highlights ETL preparation
✓ Unit tests for highlight toggle, color patch, favorites NULL-handling
✓ Answer upsert/delete tests with new segment-content binding
✓ Answer type guard via Form Request withValidator — 422 when content.type !== 'question'

---

## Edge Cases & Regressions Checked

- **Soft-delete uniqueness:** Highlight unique index includes `deleted_at` to prevent toggle-off-then-on regressions ✓
- **Cross-language cache pollution:** ShowTrimesterAction scopes by language on binding ✓
- **Partial DTO updates:** UpdateTrimesterData, UpdateLessonData, UpdateSegmentContentData track present keys for proper PATCH semantics ✓
- **Route binding safety:** Highlight resolveRouteBinding scopes by user_id; Lesson admin bypasses published() when route is admin.* ✓
- **Answer authorization:** Form Request validates content.type === 'question' and 422s on mismatch ✓
- **Legacy highlights:** ListSabbathSchoolHighlightsController applies migrated() filter so un-backfilled rows with NULL segment_content_id don't leak to clients ✓
- **Favorites NULL vs non-NULL coexistence:** Functional unique on (user_id, lesson_id, COALESCE(segment_id, 0)) permits both whole-lesson and per-segment favorites for same (user, lesson) ✓

---

## No Critical Review Items Left

Review verdict was APPROVE with all prior Critical and Warning findings resolved:
- Highlight unique now includes `deleted_at` ✓
- Migration tests for for_date and favorites added ✓
- Answer upsert type guard moved to withValidator ✓
- ShowTrimesterAction scopes by language ✓
- Highlight route binding fails closed ✓
- Update Actions take typed partial DTOs ✓
- Highlight factory materialises one segment ✓

---

## Verdict

All 116 Sabbath School tests pass (345 assertions). Full suite shows 1160 tests passing with no regressions. All acceptance criteria have test coverage. No critical issues remain.

**Status transition:** `qa-ready` → `qa-passed`
