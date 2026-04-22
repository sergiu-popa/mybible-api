# Story: MBA-011-notes

## Title
Personal notes on Bible passages

## Status
`planned`

## Description
Users can attach private notes to specific passages. Lighter-weight than
favorites — no categories, no color — just `passage + content`.

Symfony source:
- `NoteController::index/store/destroy()`

Maps to the Symfony `note` table.

## Acceptance Criteria

### CRUD
1. `GET /api/v1/notes` — paginated list of the caller's notes, newest
   first. Supports `?book={abbr}` filter.
2. `POST /api/v1/notes` — create with `{ reference, content }`.
   - `content`: required, max 10 000 characters.
   - `reference`: validated via MBA-006 parser; invalid ⇒ `422`.
3. `PATCH /api/v1/notes/{note}` — owner can update `content`. `reference`
   is immutable. `403` if not owner.
4. `DELETE /api/v1/notes/{note}` — owner only. Returns `204`.

### Authorization
5. Sanctum required on all four endpoints.
6. Ownership enforced via Form Request `authorize()`.

### Tests
7. Feature tests: full CRUD, cross-user access blocked, invalid reference
    on create, `content` length limits, pagination.
8. Unit tests for `CreateNoteAction`, `UpdateNoteAction`,
    `DeleteNoteAction`.

## Scope

### In Scope
- One resource route (`notes`) with full CRUD.
- `Note` Eloquent model.
- Actions, DTOs, Form Requests, API Resource, Feature tests.

### Out of Scope
- Sharing notes.
- Rich-text content (server stores plain text; client can render
  Markdown if it wants).
- Attachments.
- Note categories (add a follow-up story if the product asks).

## Technical Notes

### content storage
Plain text only. If `content` carries HTML, strip it at validation time
(use `strip_tags` in a Form Request rule, or preferably a dedicated
sanitizer Rule). We do not render content in any server-side HTML
context, so XSS is a client-side concern — but stripping avoids the
client needing to sanitize on display.

### Reference immutability
Same rationale as favorites (MBA-010). Avoid the failure mode where a
client accidentally re-points a note to a different passage.

## Dependencies
- **MBA-005** (auth + users).
- **MBA-006** (reference parser).

## Open Questions for Architect
1. **Content format.** Plain text vs Markdown vs HTML. Recommend plain
   text stored, Markdown rendered client-side. Product sign-off.
2. **Length limit.** 10 000 chars is a guess; Symfony has no enforced
   limit. Pick a number that matches product expectation.
3. **Soft delete.** Notes are data users care about losing. Worth
   soft-deleting for a 30-day window? Recommend yes, with a daily
   cleanup job. Out of scope for this story — flag as follow-up.
