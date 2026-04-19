# Story: MBA-013-hymnal

## Title
Hymnal — books, songs, and personal favorites

## Status
`draft`

## Description
Clients access hymnal content (hymnbooks → songs) and can favorite songs.
Straightforward catalog + favorites pattern, parallel to MBA-012.

Symfony source:
- `HymnalController::books()` — list hymnal books
- `HymnalController::songs()` — list songs in a book
- `HymnalController::song()` — full song detail (lyrics, stanzas)
- `HymnalFavoriteController::index/toggle()` — user favorites

Maps to `hymnal_book`, `hymnal_song`, `hymnal_favorite` tables.

## Acceptance Criteria

### Hymnal catalog
1. `GET /api/v1/hymnal-books` returns paginated hymnal books.
   - Supports `?language={iso2}`.
   - Response: `{ data: [{ id, name, language, song_count }, ...] }`.
2. `GET /api/v1/hymnal-books/{book}/songs` returns paginated songs in
   the book (default 50/page, max 200).
   - Optional `?search={query}` matches against song title (and number if
     numeric).
3. `GET /api/v1/hymnal-songs/{song}` returns full song detail including
   stanzas, chorus, author, composer, copyright, and whatever metadata
   the Symfony schema carries.
4. `404` on unknown book or song.
5. All three cached with `Cache-Control: public, max-age=3600`.
6. Protected by `api-key-or-sanctum`.

### Favorites
7. `GET /api/v1/hymnal-favorites` — paginated list of the caller's
   favorite songs, with the full song embedded. Sanctum required.
8. `POST /api/v1/hymnal-favorites/toggle` — body `{ song_id }` flips
   favorite state (201 on insert, 200 on delete with `{ deleted: true }`).
9. Cross-user access blocked.

### Tests
10. Feature tests: catalog listing with language filter, song search,
    song detail, 404 paths, favorites toggle, cross-user denial.
11. Unit tests for Actions.

## Scope

### In Scope
- Five endpoints as listed.
- `HymnalBook`, `HymnalSong`, `HymnalFavorite` Eloquent models.
- Actions, DTOs, Form Requests, API Resources, Feature tests.

### Out of Scope
- Admin song authoring.
- Full-text search over lyrics (current spec searches title/number only).
- Audio file delivery for songs (not clear Symfony handles this; confirm
  with product — defer to a later story if in-scope).

## Technical Notes

### Song schema reality check
The Symfony inventory lists `hymnal_song` with columns `id, bookId,
number, title`. Lyrics and stanzas are likely serialized JSON or a
related table. Confirm via `database-schema` before writing the API
Resource. The Resource shape should be stable — don't expose raw JSON
columns; parse them into a structured `stanzas: [{ index, text }]` list.

### Search
Use a `LIKE` on title against the UI language's `hymnal_song` rows.
Full-text search isn't needed at this volume.

## Dependencies
- **MBA-005** (auth + users).

## Open Questions for Architect
1. **Stanza structure.** Confirm how stanzas are stored (JSON column,
   separate table, delimiter-joined string). The Resource needs a stable
   shape.
2. **Audio files.** In scope? If yes, which storage (S3? local)? If
   unclear, defer to a follow-up story.
3. **Book-level favorites.** Users favorite songs; do they favorite
   books? Symfony says no. Keep that.
