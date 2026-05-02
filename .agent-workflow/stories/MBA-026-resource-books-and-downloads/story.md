# Story: MBA-026-resource-books-and-downloads

## Title

Port `resource_book` + `resource_book_chapter` from Symfony as a dedicated
domain; add `resource_downloads` tracking for both educational resources
and resource books.

## Status

`draft`

## Description

The Symfony app has two distinct content shapes for educational material:

- **`resource`** — flat articles, PDFs, audio, video items grouped under
  a `resource_category`. Already ported as `educational_resources` in the
  Laravel API.
- **`resource_book`** — multi-chapter long-form books with optional
  audio per chapter (`resource_book_chapter.audio_embed`,
  `audio_cdn_url`). Not present in the Laravel API; the admin app has a
  `BookBrowser` component (`/admin/resources/books/{id}`) that expects
  these endpoints.

Symfony also tracks `resource_download(resource_id, created_at,
ip_address)` rows for download metrics. The Laravel API has no equivalent.
The product manager has explicitly asked for download metrics.

This story adds the `resource_books` + `resource_book_chapters` domain as
a separate surface from `educational_resources` (the JSON-multilingual
flat shape doesn't fit the chapter hierarchy), and adds a unified
`resource_downloads` table that can record downloads for both
`educational_resources` and `resource_books` via a polymorphic relation.

## Acceptance Criteria

### Schema — Resource Books

1. `resource_books` table (renamed from Symfony `resource_book` by
   MBA-023) is integrated as a first-class Laravel domain. Final shape:
   - `id BIGINT UNSIGNED PRIMARY KEY`
   - `slug VARCHAR(255)` UNIQUE (added; backfilled from
     `LOWER(REPLACE(name, ' ', '-'))` with collision suffix)
   - `name VARCHAR(255) NOT NULL`
   - `language CHAR(2) NOT NULL`
   - `description LONGTEXT NULL`
   - `position UNSIGNED INT DEFAULT 0` — admin-driven ordering within a
     language
   - `is_published BOOLEAN DEFAULT false`
   - `published_at TIMESTAMP NULL`
   - `cover_image_url VARCHAR(255) NULL`
   - `author VARCHAR(255) NULL`
   - timestamps + `deleted_at` (soft delete)
2. Composite index `(language, is_published, position)` for the public
   list query.

### Schema — Resource Book Chapters

3. `resource_book_chapters` table (renamed from
   `resource_book_chapter` by MBA-023):
   - `id BIGINT UNSIGNED PRIMARY KEY`
   - `resource_book_id BIGINT UNSIGNED NOT NULL` FK `ON DELETE CASCADE`
   - `position SMALLINT UNSIGNED NOT NULL`
   - `title VARCHAR(255) NOT NULL`
   - `content LONGTEXT NOT NULL`
   - `audio_cdn_url TEXT NULL` (preserve from Symfony — points at MP3 in
     CDN bucket)
   - `audio_embed TEXT NULL` (preserve — embed code for hosted players)
   - `duration_seconds INT UNSIGNED NULL` — added; populated by admin or
     metadata extraction (not in this story)
   - timestamps
4. UNIQUE `(resource_book_id, position)`.
5. Composite index `(resource_book_id, position)` (covered by UNIQUE).

### Schema — Downloads

6. `resource_downloads` table (renamed from `resource_download` by
   MBA-023, generalised to polymorphic):
   - `id BIGINT UNSIGNED PRIMARY KEY`
   - `downloadable_type VARCHAR(64) NOT NULL` — `educational_resource` |
     `resource_book` | `resource_book_chapter`
   - `downloadable_id BIGINT UNSIGNED NOT NULL`
   - `user_id INT UNSIGNED NULL` FK `ON DELETE SET NULL` — anonymous
     downloads stay (NULL)
   - `device_id VARCHAR(64) NULL` — for anonymous attribution (matches
     analytics device_id from MBA-030)
   - `language CHAR(2) NULL` — request language at time of download (for
     per-language metrics)
   - `source VARCHAR(16) NULL` — `ios` | `android` | `web` (from the
     analytics convention)
   - `created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL`
7. Indexes:
   - `(downloadable_type, downloadable_id, created_at)` — primary lookup
     for "downloads for resource X"
   - `(user_id, created_at)` — user history
   - `(created_at)` — for daily aggregates by analytics rollup
8. ETL by MBA-031 maps existing Symfony `resource_download` rows
   (single-typed against `resource_id`) into the polymorphic shape with
   `downloadable_type = 'educational_resource'`. The Symfony
   `ip_address` column is **dropped** at migration (per stakeholder
   decision: no IP tracking). Existing rows lose IP; this is acceptable
   since the column is not used for metrics, only forensic.

### Domain

9. `App\Domain\EducationalResources\Models\ResourceBook` and
   `ResourceBookChapter` (extends the `EducationalResources` domain
   namespace — they live alongside `EducationalResource` even though the
   table is separate). Soft-delete trait on `ResourceBook`.
10. `App\Domain\Analytics\Models\ResourceDownload` —
    polymorphic `downloadable` morphTo. (The choice to put it in the
    Analytics domain rather than EducationalResources is intentional —
    it serves metrics, not content rendering.)
11. Query builders:
    - `ResourceBookQueryBuilder::published()`,
      `::forLanguage(string $code)`, `::orderedForList()`.
    - `ResourceBookChapterQueryBuilder::ordered()`.
    - `ResourceDownloadQueryBuilder::for(Model $downloadable)`,
      `::countsByDay(Carbon $from, Carbon $to)` for the rollup feed.

### Public read endpoints

12. `GET /api/v1/resource-books?language=&page=` — list published books
    for a language, ordered by `position ASC, published_at DESC`.
    Cached 1 hour.
13. `GET /api/v1/resource-books/{book:slug}` — book detail with nested
    `chapters[]` (lightweight: id, position, title, has_audio boolean —
    not the content). Cached 1 hour.
14. `GET /api/v1/resource-books/{book:slug}/chapters/{chapter}` — full
    chapter detail with `content`, `audio_cdn_url`, `audio_embed`,
    `duration_seconds`. Cached 10 minutes.

### Download tracking endpoints

15. `POST /api/v1/resources/{resource:uuid}/downloads` — record a
    download for an educational resource. Body: optional `device_id`,
    `source` (else inferred from User-Agent). Returns `204 No Content`.
    Rate limit: 60/min per IP+device_id pair (deduplicates accidental
    double-clicks while not punishing legitimate retries).
16. `POST /api/v1/resource-books/{book:slug}/downloads` — record book-
    level download (e.g. ePub). Same shape.
17. `POST /api/v1/resource-books/{book:slug}/chapters/{chapter}/downloads`
    — chapter-level download (audio MP3). Same shape.
18. All three endpoints accept anonymous or authenticated requests. The
    Sanctum `auth.api` middleware is **not** required. If the request
    has a Bearer token, `user_id` is captured; otherwise NULL.
19. Endpoints emit the corresponding analytics event
    (`resource.downloaded`, `resource_book.downloaded`,
    `resource_book.chapter.downloaded`) onto the analytics ingest
    pipeline (MBA-030). The dual-write (`resource_downloads` row +
    analytics event) is intentional: the table is the canonical source
    for download metrics; the event stream is for cross-feature roll-up.

### Admin endpoints

20. Resource Books CRUD:
    - `GET /api/v1/admin/resource-books`
    - `POST /api/v1/admin/resource-books`
    - `PATCH /api/v1/admin/resource-books/{book}`
    - `DELETE /api/v1/admin/resource-books/{book}` (soft delete)
    - `POST /api/v1/admin/resource-books/{book}/publish`
    - `POST /api/v1/admin/resource-books/{book}/unpublish`
    - `POST /api/v1/admin/resource-books/reorder` (within a language)
21. Resource Book Chapters CRUD:
    - `GET /api/v1/admin/resource-books/{book}/chapters`
    - `POST /api/v1/admin/resource-books/{book}/chapters`
    - `PATCH /api/v1/admin/resource-book-chapters/{chapter}`
    - `DELETE /api/v1/admin/resource-book-chapters/{chapter}`
    - `POST /api/v1/admin/resource-books/{book}/chapters/reorder`
22. Download metrics admin endpoint:
    - `GET /api/v1/admin/resource-downloads/summary?from=&to=&group_by=day|week|month&downloadable_type=&language=` —
      returns aggregated counts. Joins to `analytics_daily_rollups` from
      MBA-030 when the requested range exceeds 7 days; reads raw
      `resource_downloads` for shorter / current-day windows.

### Tests

23. Feature tests for every public read endpoint covering: 401-not-required
    (anonymous OK), 404 on unpublished, language scoping, correct
    pagination shape, cache headers.
24. Feature tests for download tracking endpoints covering anonymous,
    authenticated, rate-limit triggering at 61st request/min, polymorphic
    target persistence.
25. Feature tests for admin endpoints covering super-admin gating,
    validation errors, reorder idempotency.
26. Migration test asserting Symfony `resource_download` rows ETL into
    `resource_downloads` with `downloadable_type='educational_resource'`
    and `ip_address` dropped.

## Scope

### In Scope

- Schema: `resource_books`, `resource_book_chapters`,
  `resource_downloads` (polymorphic).
- Domain models, query builders, Resources, Form Requests, Actions.
- Public read endpoints + admin CRUD.
- Download tracking endpoints (anonymous + authenticated) for
  educational resources, resource books, and resource book chapters.
- Cross-emission of analytics events on download.

### Out of Scope

- Resource book ePub / PDF generation. The download endpoint records
  a click; the actual file delivery is handled by an external CDN URL
  (`media_path` for educational resources; `audio_cdn_url` /
  TBD-ebook-url for resource books). File generation is deferred.
- Admin UI for resource books — already partially exists in admin
  (`BookBrowser`); alignment to the new endpoints is in admin MB-015.
- Frontend reader UI (book browse + chapter reader + audio player) —
  in frontend MB-017.
- IP-based geographic analytics. Per stakeholder, no IP storage.

## API Contract Required

- `ResourceBookResource` (list shape): `slug`, `name`, `language`,
  `description`, `cover_image_url`, `author`, `published_at`,
  `chapter_count`.
- `ResourceBookResource` (detail shape): list shape + nested
  `chapters[]` array of `{id, position, title, has_audio,
  duration_seconds}`.
- `ResourceBookChapterResource`: full content fields per AC §14.
- Download endpoints: `204 No Content` on success; `429` with
  `Retry-After` header on rate-limit; `404` for unknown target.

## Technical Notes

- The polymorphic `resource_downloads` design replaces the Symfony
  single-target `resource_download` table. Rationale: resource books and
  educational resources both need download counts; a single table with
  `downloadable_type` keeps the metrics surface uniform and lets the
  admin dashboard query one source.
- `device_id` from request header `X-Device-Id` matches the analytics
  convention from MBA-030 (anonymous mobile attribution). When absent,
  `device_id` stays NULL; the row still counts toward gross download
  volume, just not toward unique devices.
- Rate-limit is per `(ip, device_id)` pair, not just IP. This is
  intentional: corporate NAT can hide hundreds of legitimate users
  behind one IP, and `device_id` disambiguates them. Without
  `device_id`, fall back to IP-only rate limiting.
- Sequencing with MBA-030: this story emits download events into the
  analytics pipeline (a `dispatch(new RecordEvent(...))` call). MBA-030
  builds the receiver. If MBA-030 ships first, the events flow
  immediately; if this story ships first, the dispatch is a no-op until
  MBA-030 lands the listener (queue jobs accumulate but cause no harm).

## References

- MBA-023 schema reconcile (table renames feed this story).
- MBA-030 analytics foundation (download events join the unified stream).
- MBA-031 ETL (migrates existing `resource_download` rows + drops IP).
- Admin MB-015 (alignment story for the new resource books endpoints).
- Frontend MB-017 (resource book reader UI).
- Symfony DDL: `resource_book`, `resource_book_chapter`,
  `resource_download` from production DDL 2026-05-02.
