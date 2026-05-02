# Story: MBA-024-commentary-domain

## Title

Port the Bible Commentary domain from Symfony into the Laravel API; expose
public read endpoints and admin write endpoints.

## Status

`in-review`

## Description

The Symfony app exposes a Bible Commentary feature backed by two tables:
`commentary` (sources — abbreviation, name, language) and `commentary_text`
(content per `commentary, book, chapter, position`). The Laravel API has
no commentary domain yet, but the admin app **already has full UI** for
commentary management (`/admin/commentary`, Browser, Check) wired to a
`CommentaryClient` that expects API endpoints. Those endpoints don't
exist — admin pages currently 404 against the new API. This story closes
that gap.

The story ports the Symfony feature with one structural improvement: a
commentary that is a translation of another commentary records its origin
via `source_commentary_id`. This is the hook that the AI translation
pipeline (MBA-029) uses to find which commentary to translate from. The
AI-specific columns on `commentary_texts` (`original`, `plain`,
`with_references`, `errors_reported`, AI timestamps) are added in MBA-029
when they are first used; this story leaves the schema clean and keeps the
content stored in a single `content` column for now.

The Symfony rows are reconciled by MBA-023 (table renames `commentary` →
`commentaries`, `commentary_text` → `commentary_texts`). This story builds
on those renames and adds the new `source_commentary_id` column and
indices.

## Acceptance Criteria

### Schema

1. `commentaries` table (renamed from Symfony `commentary` by MBA-023)
   gains:
   - `source_commentary_id BIGINT UNSIGNED NULLABLE` (FK to
     `commentaries.id`, `ON DELETE SET NULL`) — non-NULL on
     auto-translated commentaries pointing at the source-language
     commentary they were derived from.
   - `slug VARCHAR(255)` UNIQUE (replaces public-route exposure of the
     `id`). Backfilled from `abbreviation` lower-cased and dasherised.
   - `is_published BOOLEAN DEFAULT false` — gates public visibility.
   - `language` widened/normalised to `CHAR(2)` if not already done by
     MBA-023.
2. `commentary_texts` table (renamed by MBA-023) is left structurally
   untouched in this story beyond:
   - `book` widened to `VARCHAR(8)` (USFM-3) per MBA-023 standard.
   - Composite index `(commentary_id, book, chapter, position)` confirmed
     present (Symfony had it as `chapter_idx` on slightly different
     columns; this story replaces with the FK-based composite).
   - UNIQUE `(commentary_id, book, chapter, position)` re-asserted (was
     `commentary_text_unique` in Symfony on `(commentary, book, chapter,
     position)` — same shape, FK-keyed equivalent).
3. New optional columns on `commentary_texts` to support per-verse
   ranging (used by the SQLite export and the per-verse modal):
   - `verse_from SMALLINT UNSIGNED NULLABLE`
   - `verse_to SMALLINT UNSIGNED NULLABLE`
   - `verse_label VARCHAR(20) NULLABLE` — display label
     (e.g. `"1"`, `"1-3"`, `"1, 5-7"`)
4. Backfill `verse_from`/`verse_to` from `position` for existing rows
   where Symfony `position` was used as a verse number directly:
   `verse_from = position`, `verse_to = position`,
   `verse_label = position::TEXT`. Operator may refine multi-verse
   blocks later via admin.

### Domain

5. `App\Domain\Commentary\Models\Commentary` — Eloquent model with
   `language`, `slug`, `name`, `abbreviation`, `is_published`,
   `source_commentary_id`. Relations: `texts` (hasMany
   `CommentaryText`), `sourceCommentary` (belongsTo `Commentary`).
6. `App\Domain\Commentary\Models\CommentaryText` — fields per AC §2-3.
   Relation: `commentary` (belongsTo).
7. Query builders:
   - `CommentaryQueryBuilder::published()`,
     `CommentaryQueryBuilder::forLanguage(string $code)`.
   - `CommentaryTextQueryBuilder::forBookChapter(string $book, int $chapter)`,
     `CommentaryTextQueryBuilder::coveringVerse(string $book, int $chapter, int $verse)`
     (using `verse_from <= $v AND (verse_to IS NULL OR verse_to >= $v)`).

### Public read endpoints

8. `GET /api/v1/commentaries` — list published commentaries, scoped by
   request language (existing `ResolveRequestLanguage` middleware). Fields:
   `slug`, `name`, `abbreviation`, `language`. Cached 1 hour with
   `Cache-Control` headers consistent with MBA-021 patterns.
9. `GET /api/v1/commentaries/{commentary:slug}/{book}/{chapter}` — list
   commentary text blocks for a book+chapter. Returns `data[]` with
   `position`, `verse_from`, `verse_to`, `verse_label`, `content`.
   Ordered by `position ASC`. 404 if commentary unpublished or unknown.
   Cached 1 hour.
10. `GET /api/v1/commentaries/{commentary:slug}/{book}/{chapter}/{verse}` —
    list commentary blocks covering a specific verse (using `coveringVerse`
    query). Convenience endpoint for the per-verse mobile/web modal.
    Cached 1 hour.

### Admin write endpoints

11. `GET /api/v1/admin/commentaries` — list all commentaries (published +
    drafts), super-admin gated.
12. `POST /api/v1/admin/commentaries` — create (super-admin only).
    Validation: `slug` unique, `language` ISO-2, `name` required.
13. `PATCH /api/v1/admin/commentaries/{commentary}` — update metadata.
14. `POST /api/v1/admin/commentaries/{commentary}/publish` /
    `POST /api/v1/admin/commentaries/{commentary}/unpublish`.
15. `GET /api/v1/admin/commentaries/{commentary}/texts?book=&chapter=` —
    paginated list for the admin Browser.
16. `POST /api/v1/admin/commentaries/{commentary}/texts` —
    create a text block. Body: `book`, `chapter`, `position`,
    `verse_from?`, `verse_to?`, `verse_label?`, `content`.
17. `PATCH /api/v1/admin/commentaries/{commentary}/texts/{text}` — update.
18. `DELETE /api/v1/admin/commentaries/{commentary}/texts/{text}` — delete.
19. `POST /api/v1/admin/commentaries/{commentary}/texts/reorder` — full-
    array idempotent reorder within a book+chapter (matches the reorder
    contract from MBA-022 §12).

### Authorization

20. All `/api/v1/admin/commentaries/*` routes require `super-admin`
    middleware (per `MBA-022` E-09 / P-03 precedent). Non-super admins
    can author commentary texts only via per-language gating in MBA-016
    (admin) — but at the API level, super-admin is the gate for now.
    `User::canManageLanguage()` is consulted before mutations once
    multi-author flows land; not in this story.

### Tests

21. Feature tests for every new endpoint covering: 401 (no token), 403
    (non-super admin on admin endpoints), 404 (unpublished commentary on
    public endpoints), validation errors (missing required fields,
    invalid slugs), happy-path response shape.
22. Unit tests for `CommentaryTextQueryBuilder::coveringVerse()` with at
    least these cases: single-verse block, multi-verse block, NULL
    `verse_to` (open-ended block), block that doesn't cover the verse.
23. Integration test asserting public list endpoints honour
    `is_published` (drafts hidden) and `language` scoping.

## Scope

### In Scope

- Schema additions on top of MBA-023 renames: `slug`, `is_published`,
  `source_commentary_id` on `commentaries`; `verse_from/verse_to/verse_label`
  on `commentary_texts`.
- Public read endpoints for listing sources, fetching by chapter, and
  fetching by verse.
- Admin CRUD endpoints for sources and text blocks, plus reorder.
- API Resources for both shapes; Form Requests for all writes.

### Out of Scope

- AI columns on `commentary_texts` (`original`, `plain`, `with_references`,
  error counters, AI timestamps). All in MBA-029.
- Admin UI for the AI workflow (correction button, translate, error
  reports queue, SQLite export). All in admin MB-017.
- Frontend public reader (`/commentary/{slug}/{language}/book/chapter`)
  and AI-corrected notice. In frontend MB-019.
- Multiple commentary sources for the same language. The schema supports
  it; there is currently only SDA. Selector UI is a frontend concern.
- Per-language editor permissions via `User::canManageLanguage()` —
  super-admin-only for now.

## API Contract Required

Endpoints listed in AC §8–19. Response shapes follow the existing
multi-language Resource conventions (e.g. `EducationalResourceResource`,
`SabbathSchoolLessonResource`):

- `CommentaryResource` exposes `slug`, `name`, `abbreviation`, `language`,
  `is_published` (admin only), plus `source_commentary` (nested resource
  when present, indicating a translation).
- `CommentaryTextResource` exposes `id`, `book`, `chapter`, `position`,
  `verse_from`, `verse_to`, `verse_label`, `content`. Admin-only fields
  added in MBA-029 (AI columns) are gated by request user role.

## Technical Notes

- The decision to add `slug` is a small departure from Symfony (which
  exposed `abbreviation` directly). Rationale: routing on `abbreviation`
  forces uppercase-only ASCII; `slug` lets us evolve naming without
  breaking URLs, matching the convention used by `reading_plans.slug` and
  `hymnal_books.slug`.
- `source_commentary_id` is nullable on purpose. The first row per language
  is always source (NULL); translations point at the source commentary.
  Self-referential FK is fine because the source row is created first and
  the translation references it after.
- `verse_from`/`verse_to`/`verse_label` are introduced on this story
  (rather than waiting for the AI workflow) because they are read-side
  features useful to public consumers, not just AI. Symfony uses
  `position` as the implicit verse number; preserving that as
  `verse_from = position` is a strict superset of the legacy semantics.
- The `coveringVerse` query is the hot path for the per-verse modal that
  fires when a `.reference` link is clicked in another piece of content
  (devotional, SS lesson). Indexed via `(commentary_id, book, chapter,
  verse_from, verse_to)` to keep the lookup O(log n).

## References

- MBA-023 reconcile foundation (table renames, language/book
  standardisation).
- MBA-029 commentary AI workflow (the consumer of `source_commentary_id`).
- Admin MB-017 — UI side of the commentary AI workflow.
- Frontend MB-019 — public commentary reader + cross-chapter modal.
- Symfony precedent: `commentary` and `commentary_text` tables in the
  production DDL provided 2026-05-02.
- Existing admin client: `apps/admin/app/Support/Api/CommentaryClient.php`.
