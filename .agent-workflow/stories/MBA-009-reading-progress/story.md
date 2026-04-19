# Story: MBA-009-reading-progress

## Title
Reading progress — track chapters/verses a user has read

## Status
`draft`

## Description
Per-user reading tracker. Separate from Reading Plans (MBA-001–004): this
is the "have I read this chapter yet" checklist the client uses to show
greyed-out verses once a user has marked them. It is the user's own
bookmark layer, independent of any plan subscription.

Symfony source:
- `ProgressController::index()` — list the user's progress entries
- `ProgressController::toggle()` — flip the read state of a chapter/verse
- `ProgressController::reset()` — wipe all progress

Maps to the Symfony `reading_progress` table (confirmed in inventory;
re-verify the schema before migrating).

## Acceptance Criteria

### List progress
1. `GET /api/v1/reading-progress` returns the authenticated user's
   progress entries, paginated (default 100 per page, max 500).
2. Supports `?version={abbr}` and `?book={abbr}` filters.
3. Response: `{ data: [{ version, book, chapter, verse, read_at }, ...],
   meta, links }`. `verse` is nullable (a null verse means "whole chapter").
4. Requires Sanctum (`auth:sanctum`). API key alone is NOT enough — this
   is per-user data.

### Toggle progress
5. `POST /api/v1/reading-progress/toggle` accepts
   `{ version, book, chapter, verse? }`.
6. If no entry exists for the `(user, version, book, chapter, verse)`
   tuple, insert one with `read_at = now()` and return `201`.
7. If an entry exists, delete it and return `200` with
   `{ deleted: true }`.
8. `verse` omitted ⇒ chapter-level toggle; all verse-level rows for that
   chapter are cleared in the same transaction (chapter-level trumps
   verse-level).
9. Validates `(version, book, chapter, verse)` against the catalog via
   MBA-006 / MBA-007. `422` on invalid.
10. Owner only — Sanctum required; no cross-user writes possible.

### Reset
11. `DELETE /api/v1/reading-progress` wipes every entry for the caller.
12. Returns `204 No Content`.
13. Supports optional `?version={abbr}` to reset a single version only.

### Tests
14. Feature tests: list (owner-only), toggle insert, toggle delete,
    chapter-level clears verse-level, invalid reference rejection, reset
    all, reset by version, 401 on anonymous list/toggle/reset.
15. Unit tests for `ToggleReadingProgressAction` and
    `ResetReadingProgressAction`.

## Scope

### In Scope
- Three endpoints listed above.
- `ReadingProgress` Eloquent model.
- Actions, DTOs, Form Requests, API Resource, Feature tests.
- Query scope `forUser($user)` on the model.

### Out of Scope
- Reading Plan progress (handled under subscription days in MBA-001-004).
- Statistics / aggregates ("you have read 45% of the Bible") — a
  dashboarding concern, deferred.
- Bulk upload of progress from legacy Symfony sessions — already in the
  shared DB, nothing to import.

## Technical Notes

### Schema note
The Symfony `reading_progress` table likely has columns close to
`user_id, version, book, chapter, verse, read_at`. Confirm via
`database-schema` before writing the model. If column names are
camelCase, apply the same rename decision as MBA-005 (snake_case
project-wide).

### Toggle semantics and concurrency
A client double-tapping toggle in quick succession should not create
duplicate rows. Enforce a unique index on
`(user_id, version, book, chapter, verse)` with `verse IS NULL` handled
explicitly (MySQL treats `NULL`s as distinct under unique). Options:
- Store `verse = 0` as the "whole chapter" sentinel (simplest).
- Use a generated column for uniqueness.

Recommend the sentinel; Architect to confirm.

### Chapter-level vs verse-level
The chapter-level toggle clearing verse-level rows is the Symfony behavior
— keep it. If a user marks `GEN 1` as read, any previously-marked
`GEN 1:5`, `GEN 1:7` entries are collapsed away.

## Dependencies
- **MBA-005** (User + auth).
- **MBA-007** (book/chapter validation).
- Optional: **MBA-006** for verse validation. Without it, we rely on plain
  integer range checks.

## Open Questions for Architect
1. **Whole-chapter sentinel.** `verse = 0` vs `verse IS NULL` with a
   composite unique key. Recommend `0`.
2. **Delete-by-version reset.** Product-wise, should reset also clear
   every version (current spec: only the requested one, or all if no
   param)? Confirm default.
3. **Soft-delete.** Is there value keeping history? Likely no — Symfony
   hard-deletes. Keep behavior.
