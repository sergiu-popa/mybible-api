# Story: MBA-007-bible-catalog

## Title
Bible catalog — versions, books, and chapters endpoints

## Status
`planned`

## Description
Expose the read-only Bible catalog used by clients to render the version
picker, book list, and chapter navigator. Four Symfony endpoints fold into
this story.

Symfony source:
- `BibleController::index()` — list versions
- `BibleController::jsonExport()` — full dump for a version
- `BookController::booksByLanguage()` — books filtered by UI language
- `BookController::chapters()` — chapter count + metadata for a book

All endpoints are read-only, cacheable, and currently served under
`/public/api/*` with HTTP Basic. In Laravel they move behind the
`api-key-or-sanctum` middleware — same endpoint works for anonymous clients
that ship an `X-Api-Key` header or for logged-in users with a bearer token.

## Acceptance Criteria

### List Bible versions
1. `GET /api/v1/bible-versions` returns all available Bible versions.
2. Response: paginated `{ data: [{ id, name, abbreviation, language }, ...],
   meta, links }`. Default page size 50 (versions are few; cap at 100).
3. Supports `?language=ro` filter.
4. Protected by `api-key-or-sanctum` middleware.
5. Cache headers: `Cache-Control: public, max-age=3600`. ETag derived from
   the max `updated_at` across the versions table.

### Full version export
6. `GET /api/v1/bible-versions/{version}/export` returns every verse of the
   version as a structured JSON dump, grouped `books > chapters > verses`.
7. Response shape is the same Symfony ships today — documented in the
   architecture doc for this story. Paginating a full-version export is
   meaningless, so this endpoint returns the full document.
8. `Cache-Control: public, max-age=86400` + strong ETag. A response for a
   version that has not changed must be cheap to re-serve.
9. `404` when the version abbreviation or id does not exist.

### Books by language
10. `GET /api/v1/books?language={iso2}` returns the 66-book list localized
    for the given language (long name, short name, order).
11. `language` defaults to the request's resolved `Language` attribute
    (`ResolveRequestLanguage` middleware) if the query param is omitted.
12. Response: `{ data: [{ id, abbreviation, name, testament, position,
    chapter_count }, ...] }`. Not paginated — the set is bounded at 66.

### Chapters for a book
13. `GET /api/v1/books/{book}/chapters` returns chapter metadata for a
    book: `{ data: [{ number, verse_count }, ...] }`.
14. `book` is resolved by id OR by abbreviation (`GEN`, `JHN`). Route-model
    binding handles both.
15. `404` when the book is unknown.

### Error shape
16. All errors render via the standard `{ message, errors }` envelope wired
    in `bootstrap/app.php`.

### Tests
17. Feature tests cover: list versions (with language filter), full export
    (small fixture version), books by language (defaulting and explicit),
    chapters for book (by id and by abbreviation), 404 paths.
18. ETag round-trip test: second request with `If-None-Match` matching the
    first response's ETag returns `304`.

## Scope

### In Scope
- Four new read-only endpoints as listed.
- Eloquent models for `BibleVersion`, `Book`, `Chapter`, `Verse`.
- QueryBuilders/scopes for language filtering and eager loading.
- API Resources for each response shape.
- Response caching with ETag.

### Out of Scope
- Admin endpoints for managing versions (admin app handles those
  separately and is not in scope).
- Writing or editing verses.
- Daily verse and verse-range lookup (handled in MBA-008).

## Technical Notes

### Schema clarification NEEDED
The Symfony inventory showed both `Book` and `Version` entities mapping to
the same `bible` table, which smells like single-table inheritance or a
reporting error. Before writing models, the Architect must inspect the
existing table in the shared DB via `database-schema` and confirm whether
`bible` holds versions, books, or some union, and whether a separate
`bible_books` / similar table exists.

### Full export performance
`GET /api/v1/bible-versions/{version}/export` can return 30k+ rows.

- Stream the JSON response (`response()->stream`) to avoid loading
  everything into memory.
- Consider a cached pre-rendered JSON blob in object storage for each
  version — MVP can skip, but flag as a follow-up if response time > 1s.

### Read routes hit twice
With `api-key-or-sanctum`, anonymous API-key clients and logged-in users
both hit these routes. Rate limit generously (500 rpm per client) since
these are read-only.

## Dependencies
- **MBA-005** (User schema + auth foundation).
- No dependency on MBA-006 (no reference parsing here).

## Open Questions for Architect
1. **Schema reality check.** Inspect `bible` / `verse` tables in the
   shared DB and confirm the model-to-table mapping before writing
   migrations or Eloquent models. If Laravel migrations for these tables
   must NOT run (tables already exist from Symfony), document that in
   architecture and use `Schema::hasTable()` guards or skip migrations
   for these tables entirely.
2. **ETag computation cost.** If `max(updated_at)` across books is cheap,
   use that. If it requires a full scan, cache the ETag itself for 1 hour.
3. **Does `export` need authentication at all?** Symfony served it
   publicly with HTTP Basic. If product wants it fully public, move it
   outside `api-key-or-sanctum` — but then add aggressive rate limiting.
