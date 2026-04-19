# Story: MBA-017-sabbath-school

## Title
Sabbath School — lessons, answers, highlights, favorites

## Status
`draft`

## Description
Sabbath School is a structured weekly study program: users progress
through dated lessons with daily segments, answer reflective questions,
highlight passages, and favorite entries for review. The Symfony
implementation is the heaviest user-facing domain after Reading Plans.

Symfony source (inventory identifies ~4 endpoints):
- `SabbathSchoolController::lessons()` — list lessons (by language,
  by date)
- `SabbathSchoolController::lesson()` — lesson detail with segments
- `SabbathSchoolController::saveAnswer()` — persist a user's answer
- `SabbathSchoolController::toggleHighlight()` — mark a highlight on
  a passage
- `SabbathSchoolController::toggleFavorite()` — favorite a lesson or
  segment

Re-read the Symfony controller before architecting — the inventory
count was approximate.

## Acceptance Criteria

### Lesson catalog
1. `GET /api/v1/sabbath-school/lessons?language={iso2}` returns
   paginated lessons in the requested language, newest first. Default
   30/page.
2. `GET /api/v1/sabbath-school/lessons/{lesson}` returns the lesson
   detail with segments.
   - Response: `{ data: { id, title, week_start, week_end, language,
     segments: [{ id, day, title, content, passages, questions: [{ id,
     prompt }, ...] }, ...] } }`.
3. Protected by `api-key-or-sanctum`.
4. `Cache-Control: public, max-age=3600` on both.

### User answers
5. `POST /api/v1/sabbath-school/questions/{question}/answer` — body
   `{ content }` saves the caller's answer. Upserts on
   `(user_id, question_id)` — one answer per user per question; a
   subsequent POST overwrites.
6. `GET /api/v1/sabbath-school/questions/{question}/answer` — returns
   the caller's answer (or `404` if none).
7. `DELETE /api/v1/sabbath-school/questions/{question}/answer` —
   removes the caller's answer.
8. All three require Sanctum; enforce ownership via the authenticated
   user id — never accept `user_id` from the request body.

### Highlights
9. `POST /api/v1/sabbath-school/highlights/toggle` — body
   `{ segment_id, passage }`, toggles a highlight. Returns `201` on
   create, `200` on delete.
10. `GET /api/v1/sabbath-school/highlights?segment_id={id}` — list
    the caller's highlights for a segment.

### Favorites
11. `POST /api/v1/sabbath-school/favorites/toggle` — body
    `{ lesson_id, segment_id? }`. If `segment_id` omitted, the whole
    lesson is favorited; otherwise the specific segment.
12. `GET /api/v1/sabbath-school/favorites` — paginated list of the
    caller's favorites.

### Tests
13. Feature tests: lesson listing with language, lesson detail, answer
    CRUD (upsert behavior), cross-user answer access denied,
    highlight toggle, favorite toggle with and without segment_id.
14. Unit tests for each Action.

## Scope

### In Scope
- All endpoints listed above.
- `SabbathSchoolLesson`, `SabbathSchoolSegment`,
  `SabbathSchoolQuestion`, `SabbathSchoolAnswer`,
  `SabbathSchoolHighlight`, `SabbathSchoolFavorite` models (consolidate
  if the schema allows).
- Actions, DTOs, Form Requests, API Resources, Feature tests.

### Out of Scope
- Admin authoring of lessons.
- Aggregate statistics / "you've completed N weeks".
- Group / class features (shared study with other users).
- Cross-device sync beyond what a single user already gets via
  server-side storage.

## Technical Notes

### Segment content
Likely HTML. Same trust model as devotionals (admin-authored, no
sanitization).

### Answer size limits
Match the note limit from MBA-011 (10 000 chars) unless the schema
constrains it differently. Validate at Form Request.

### Highlight passage
The `passage` field is a canonical reference string parsed via
MBA-006 at write time — reject unparseable passages `422`.

### Avoid N+1
Lesson detail eager-loads `segments.questions`. Use a
`with(['segments.questions'])` scope on the Lesson QueryBuilder.
Test with a real fixture of ~7 segments × 5 questions to confirm.

## Dependencies
- **MBA-005** (auth + users).
- **MBA-006** (reference parser for highlights / passages).

## Open Questions for Architect
1. **Lesson vs segment-level favorites.** Confirm whether the schema
   supports both simultaneously (segment favorite with lesson also
   favorited counts once or twice?). Recommend: unique on
   `(user_id, lesson_id, segment_id)` with `segment_id NULLable` and
   the sentinel approach from MBA-009.
2. **Answer editing history.** Keep history or overwrite? Symfony
   overwrites. Keep that.
3. **Language scope of highlights/favorites.** Are highlights
   language-scoped (a highlight on RO lesson vs EN) or cross-language
   (stored against the passage only)? Inspect the schema.
