# QA — MBA-024-commentary-domain

## Test Coverage

**Feature Tests:** 38 tests covering all public and admin endpoints.

**Public Endpoints:**
- ✓ `GET /api/v1/commentaries` — language scoping, published filtering, response shape
- ✓ `GET /api/v1/commentaries/{slug}/{book}/{chapter}` — happy path, 404 on unpublished/unknown commentary, correct ordering by position
- ✓ `GET /api/v1/commentaries/{slug}/{book}/{chapter}/{verse}` — single-verse hit, multi-verse hit, empty result on miss

**Admin Endpoints:**
- ✓ `GET /api/v1/admin/commentaries` — drafts visible to super-admin, 403 for non-super, 401 for unauthenticated
- ✓ `POST /api/v1/admin/commentaries` — slug unique constraint, language ISO-2 validation, name required array, creation with auto-slug fallback
- ✓ `PATCH /api/v1/admin/commentaries/{id}` — slug collision detection, partial update
- ✓ `POST /api/v1/admin/commentaries/{id}/publish` — toggled publication state reflected in public list
- ✓ `POST /api/v1/admin/commentaries/{id}/unpublish` — as above
- ✓ `GET /api/v1/admin/commentaries/{id}/texts` — pagination, book/chapter filtering
- ✓ `POST /api/v1/admin/commentaries/{id}/texts` — position unique within (commentary, book, chapter), validation
- ✓ `PATCH /api/v1/admin/commentaries/{id}/texts/{text_id}` — partial update, 404 on cross-commentary text
- ✓ `DELETE /api/v1/admin/commentaries/{id}/texts/{text_id}` — returns 204, 404 on cross-commentary
- ✓ `POST /api/v1/admin/commentaries/{id}/texts/reorder` — asserts IDs belong to (book, chapter) tuple, 422 on cross-tuple

**Unit Tests:**
- ✓ `CommentaryTextQueryBuilder::coveringVerse()` — single-verse block, multi-verse block, open-ended (verse_to NULL), miss, book/chapter scoping

## Schema Verification

**Commentaries table:**
- ✓ `slug VARCHAR(255) UNIQUE NOT NULL` — backfilled from Str::slug(abbreviation), collision suffix logic applied
- ✓ `is_published BOOLEAN DEFAULT false` — defaults to draft state
- ✓ `source_commentary_id BIGINT UNSIGNED NULLABLE` — FK to commentaries.id, ON DELETE SET NULL
- ✓ `language CHAR(2)` — widened from legacy VARCHAR(3)
- ✓ `name JSON` — converted from VARCHAR for multi-language resolution
- ✓ Composite index on `(language, is_published)` — optimizes public list queries

**Commentary_texts table:**
- ✓ `commentary_id BIGINT UNSIGNED` — renamed from Symfony `commentary`, FK re-asserted (was cascadeOnDelete)
- ✓ `book VARCHAR(8)` — widened from VARCHAR(3), backfilled with USFM-3 abbreviations
- ✓ `verse_from SMALLINT UNSIGNED NULLABLE` — backfilled from position
- ✓ `verse_to SMALLINT UNSIGNED NULLABLE` — backfilled from position (= verse_from for single-verse, open-ended = NULL)
- ✓ `verse_label VARCHAR(20) NULLABLE` — backfilled as position::TEXT
- ✓ `UNIQUE (commentary_id, book, chapter, position)` — replaces Symfony `commentary_text_unique`
- ✓ `INDEX (commentary_id, book, chapter, verse_from, verse_to)` — hot path for coveringVerse query

## Regression Testing

**Full suite:** 1118 tests pass, 4094 assertions.
- No failures in unrelated domains (Reading Plans, Sabbath School, Educational Resources, Olympiad, etc.)
- No migrations failed or rolled back.

## Authorization Verification

- ✓ Public endpoints require `api-key-or-sanctum` middleware + `resolve-language`
- ✓ Admin endpoints require `auth:sanctum` + `super-admin` middleware
- ✓ Non-super admins receive 403 on list, create, update, publish/unpublish
- ✓ Unauthenticated requests receive 401
- ✓ Route model binding applies `published()` filter only on `{commentary:slug}` routes (public); admin routes via `{commentary}` (id) bypass the filter

## API Contract

**CommentaryResource (public shape):**
```json
{
  "data": [
    {
      "slug": "sda",
      "name": "SDA Commentary",
      "abbreviation": "SDA",
      "source_commentary": {
        "slug": "sda-en",
        "name": "SDA Commentary (English)",
        "abbreviation": "SDA"
      }
    }
  ]
}
```

**AdminCommentaryResource (admin shape):**
Extends public with `id`, `language`, `is_published`.

**CommentaryTextResource (public):**
```json
{
  "data": [
    {
      "position": 1,
      "verse_from": 1,
      "verse_to": 3,
      "verse_label": "1-3",
      "content": "...",
      "book": "GEN",
      "chapter": 1
    }
  ]
}
```

**AdminCommentaryTextResource (admin):**
Extends public with `id`.

## Verdict

**QA PASSED**

All acceptance criteria met:
- ✓ Schema shape matches spec (columns, types, indices, backfills, FKs)
- ✓ Domain models (Commentary, CommentaryText) with proper relations and query builders
- ✓ Query builders with `published()`, `forLanguage()`, `forBookChapter()`, `coveringVerse()` logic verified
- ✓ All public and admin endpoints return correct status codes and JSON shapes
- ✓ Authorization enforced at middleware + Form Request level
- ✓ Feature tests cover happy path, 401/403/404/422 errors, edge cases
- ✓ Unit test for `coveringVerse()` covers single/multi-verse, open-ended, miss, and scoping
- ✓ No regressions in full test suite
- ✓ Caching headers applied (1 hour cache-control on public reads)
- ✓ Reorder action asserts tuple membership and uses transaction
- ✓ Slug auto-generation handles collisions deterministically

Ready for merge.
