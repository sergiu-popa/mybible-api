# Plan: MBA-007-bible-catalog

## Approach

Introduce an `App\Domain\Bible` layer with four Eloquent models (`BibleVersion`, `BibleBook`, `BibleChapter`, `BibleVerse`) and expose four read-only endpoints under `/api/v1/bible-versions` and `/api/v1/books`, all behind `api-key-or-sanctum` + `resolve-language`. Symfony's `Book`/`Version` overloaded `bible` table is not reproduced: we model each concept as its own table and ship idempotent migrations so they no-op against environments where Symfony already populated the data. The full-version export streams JSON (`response()->stream`) to avoid loading 30k verses into memory, and responses carry `Cache-Control` + strong `ETag` headers emitted by a shared `BibleCacheHeaders` helper consumed by both controllers that need it.

## Open questions — resolutions

1. **Schema reality check.** The local MySQL DB has no Bible tables — the "shared DB" decision never materialized in this environment. We ship fresh Laravel migrations for `bible_versions` / `bible_books` / `bible_chapters` / `bible_verses`, each wrapped in `Schema::hasTable()` guards so prod (which will point at the Symfony DB) skips them while dev/test seeds work. Column names mirror Symfony's `bible` / `verse` layout so data carries over untouched. The overloaded Symfony `bible` table is NOT reproduced — versions and books live in separate tables in Laravel.
2. **ETag computation cost.** Use `max(updated_at)` across the target set (cheap with an index on `bible_versions.updated_at` / `bible_books.updated_at` / `bible_chapters.updated_at`). No ETag cache layer in MVP; revisit if the export's `max(updated_at)` over `bible_verses` exceeds 50ms in a measured environment.
3. **Does `export` need auth?** Keep it behind `api-key-or-sanctum` (story default). Opening it publicly is a follow-up request, not an Architect call.

## Domain layout

```
app/Domain/Bible/
├── Models/
│   ├── BibleVersion.php
│   ├── BibleBook.php
│   ├── BibleChapter.php
│   └── BibleVerse.php
├── QueryBuilders/
│   ├── BibleVersionQueryBuilder.php
│   └── BibleBookQueryBuilder.php
├── Support/
│   ├── BibleCacheHeaders.php        # ETag + Cache-Control builder
│   └── BibleVersionExporter.php     # streaming JSON writer for the full export
└── Exceptions/
    └── BibleVersionNotFoundException.php
```

No Actions/DTOs: all four endpoints are read-only list/show operations; pushing a no-op "Action" in front of `Model::query()` is bureaucracy, not architecture. The one piece of non-trivial logic (streaming export) lives in `BibleVersionExporter` and is covered by a feature test via the HTTP endpoint.

## Key types

| Type | Role |
|---|---|
| `BibleVersion` (Model) | Fields: `id`, `name`, `abbreviation`, `language`, `updated_at`. Route-key: `abbreviation`. Relations: `hasMany BibleVerse`. QueryBuilder: `forLanguage(Language)`. |
| `BibleBook` (Model) | Fields: `id`, `abbreviation` (e.g. `GEN`), `testament` (`old`/`new`), `position` (1–66), `chapter_count`, `names` (JSON: `{ro, en, hu}` long names), `short_names` (JSON: `{ro, en, hu}`). Route-key: `abbreviation`. Relations: `hasMany BibleChapter`. QueryBuilder: `inCanonicalOrder()`. |
| `BibleChapter` (Model) | Fields: `id`, `bible_book_id`, `number`, `verse_count`. Relations: `belongsTo BibleBook`. |
| `BibleVerse` (Model) | Fields: `id`, `bible_version_id`, `bible_book_id` (denormalized for export joins), `chapter`, `verse`, `text`. Relations: `belongsTo BibleVersion`, `belongsTo BibleBook`. Index on `(bible_version_id, bible_book_id, chapter, verse)`. No route-model binding — consumed by MBA-008, only the streaming export reads it here. |
| `BibleVersionQueryBuilder` | `forLanguage(Language)` — filters on `language` column. |
| `BibleBookQueryBuilder` | `inCanonicalOrder()` — orders by `position`. |
| `BibleCacheHeaders` | Static helpers `forVersionList(Collection): array`, `forVersionExport(BibleVersion): array`. Each returns `[ 'Cache-Control' => ..., 'ETag' => ... ]`. ETag is a strong quoted hash (`sha1(max_updated_at|id_count)`). |
| `BibleVersionExporter` | `stream(BibleVersion): StreamedResponse` — opens a `php://output` writer, emits `{ "version": {...}, "books": [ { ..., "chapters": [ { "number": N, "verses": [...] } ] } ] }` by cursoring `BibleVerse` ordered by `(book.position, chapter, verse)` and breaking on book/chapter transitions. |
| `BibleVersionNotFoundException` (extends `\RuntimeException`) | Rendered as 404 via `bootstrap/app.php` exception handler (or surfaces as `NotFoundHttpException` via route-model binding — see Risks). |

## HTTP endpoints

| Method | Path | Controller | Form Request | Resource | Middleware |
|---|---|---|---|---|---|
| GET | `/api/v1/bible-versions` | `ListBibleVersionsController` | `ListBibleVersionsRequest` | `BibleVersionResource` | `api-key-or-sanctum`, `resolve-language` |
| GET | `/api/v1/bible-versions/{version:abbreviation}/export` | `ExportBibleVersionController` | `ExportBibleVersionRequest` | streamed JSON (not a Resource) | `api-key-or-sanctum` |
| GET | `/api/v1/books` | `ListBibleBooksController` | `ListBibleBooksRequest` | `BibleBookResource` | `api-key-or-sanctum`, `resolve-language` |
| GET | `/api/v1/books/{book:abbreviation}/chapters` | `ListBibleBookChaptersController` | `ListBibleBookChaptersRequest` | `BibleChapterResource` | `api-key-or-sanctum` |

Route-model binding on `{version:abbreviation}` and `{book:abbreviation}` uses the default global resolver — both models are unscoped (no `published()`, no soft deletes), so per-guideline §5b the default binding strategy is correct. Controllers all invokable (single-action).

## Data & migrations

| Migration | Tables | Notes |
|---|---|---|
| `create_bible_versions_table` | `bible_versions` | Guard with `Schema::hasTable('bible_versions')`. Columns: `id`, `name`, `abbreviation` (unique), `language` (indexed), `timestamps`. |
| `create_bible_books_table` | `bible_books` | Guard. Columns: `id`, `abbreviation` (unique), `testament`, `position` (unique), `chapter_count`, `names` (JSON), `short_names` (JSON), `timestamps`. |
| `create_bible_chapters_table` | `bible_chapters` | Guard. Columns: `id`, `bible_book_id` (FK), `number`, `verse_count`, `timestamps`. Unique on `(bible_book_id, number)`. |
| `create_bible_verses_table` | `bible_verses` | Guard. Columns: `id`, `bible_version_id` (FK), `bible_book_id` (FK), `chapter`, `verse`, `text`. Index `(bible_version_id, bible_book_id, chapter, verse)`. |
| Seeder: `BibleCanonSeeder` | `bible_books`, `bible_chapters` | Seeds the 66-book canon + chapter counts from `BibleBookCatalog::BOOKS` (MBA-006) so tests + dev environments have queryable data. Does NOT seed versions/verses — tests build those via factories. |

All four tables get `updated_at` indexes to keep the ETag `MAX(updated_at)` probe index-only.

Factories: `BibleVersionFactory`, `BibleBookFactory`, `BibleChapterFactory`, `BibleVerseFactory` — each ships a default state plus one named state per common test shape (e.g. `BibleVersionFactory::romanian()`, `BibleBookFactory::genesis()`). Each factory state listed here is consumed by tasks 8/10/11/13.

## Tasks

- [x] 1. Create `App\Domain\Bible\Models\BibleVersion` + `BibleVersionQueryBuilder` (with `forLanguage`) + `BibleVersionFactory` (default + `romanian()` state). Unit test: `forLanguage` filters; route-key is `abbreviation`.
- [x] 2. Create `App\Domain\Bible\Models\BibleBook` + `BibleBookQueryBuilder` (with `inCanonicalOrder`) + `BibleBookFactory` (default + `genesis()` state). Unit test: `inCanonicalOrder` returns rows by `position`; route-key is `abbreviation`.
- [x] 3. Create `App\Domain\Bible\Models\BibleChapter` + `BibleChapterFactory`. Unit test: `(bible_book_id, number)` is unique; `verse_count` casts to int.
- [x] 4. Create `App\Domain\Bible\Models\BibleVerse` + `BibleVerseFactory`. Unit test: compound index query returns rows ordered by `(chapter, verse)`.
- [x] 5. Create migrations for `bible_versions`, `bible_books`, `bible_chapters`, `bible_verses` — each wrapped in `Schema::hasTable()` guards per Open Question 1. Include all listed indexes.
- [x] 6. Create `Database\Seeders\BibleCanonSeeder` that seeds the 66 books + chapter counts from `App\Domain\Reference\Data\BibleBookCatalog::BOOKS`. Localized names in `names` / `short_names` come from the RO/EN/HU `LanguageFormatter` maps (MBA-006). Wire into `DatabaseSeeder`.
- [x] 7. Create `App\Domain\Bible\Support\BibleCacheHeaders` with `forVersionList(Collection)` (`max-age=3600`) and `forVersionExport(BibleVersion)` (`max-age=86400`); both emit a strong ETag. Unit test: same input → same ETag; `updated_at` bump changes ETag.
- [x] 8. Create `ListBibleVersionsRequest` (`language?`, `per_page?` with `MAX_PER_PAGE = 100`, `DEFAULT_PER_PAGE = 50`) + `BibleVersionResource` (`id`, `name`, `abbreviation`, `language`). Unit test the request rules and resource shape.
- [x] 9. Create `ListBibleVersionsController` (invokable): applies `forLanguage` when `?language=` present, paginates, attaches `BibleCacheHeaders::forVersionList` via `->additional()` or `withResponse()`, handles `If-None-Match` → `304`. Route in `routes/api.php` under `v1` group with `api-key-or-sanctum` + `resolve-language`. Feature test: list, language filter, pagination shape, ETag round-trip returns 304, auth gate.
- [x] 10. Create `ListBibleBooksRequest` (`language?` — falls back to `ResolveRequestLanguage::ATTRIBUTE_KEY`) + `BibleBookResource` (`id`, `abbreviation`, `name`, `testament`, `position`, `chapter_count`). Unit test both. `name` uses `LanguageResolver::resolve($this->names, $language)`.
- [x] 11. Create `ListBibleBooksController` (invokable): returns all 66 books in canonical order (no pagination — AC 12). Route under `v1`, `api-key-or-sanctum` + `resolve-language`. Feature test: default-language fallback, explicit `?language=ro`, 66-item response, auth gate.
- [x] 12. Create `ListBibleBookChaptersRequest` (no query params — accept only the path binding) + `BibleChapterResource` (`number`, `verse_count`). Unit test resource shape.
- [x] 13. Create `ListBibleBookChaptersController` (invokable): resolves `{book:abbreviation}`, returns chapter list ordered by `number`, not paginated. Route under `v1`, `api-key-or-sanctum`. Feature test: resolve by abbreviation (`GEN`), 404 for unknown, shape assertion.
- [x] 14. Create `App\Domain\Bible\Support\BibleVersionExporter::stream(BibleVersion)` — cursors `BibleVerse` joined with `BibleBook` ordered by `(books.position, chapter, verse)`, writes JSON incrementally to `php://output` with book/chapter break markers. Unit test against a small fixture version (2 books × 1 chapter × 3 verses): asserts final JSON shape and that no model collection holds > N rows at once (use `Model::preventsLazyLoading` + cursor).
- [x] 15. Create `ExportBibleVersionRequest` (no query params) + `ExportBibleVersionController` (invokable): resolves `{version:abbreviation}`, delegates to `BibleVersionExporter::stream`, attaches `BibleCacheHeaders::forVersionExport` headers, short-circuits `If-None-Match` → `304`. Route under `v1`, `api-key-or-sanctum`. Feature test: shape for a fixture version, 404 for unknown version, ETag round-trip returns 304.
- [x] 16. Run `make lint-fix`, `make stan`, `make test --filter=Bible`; then `make test` before marking ready for review.

## Risks & notes

- **Shared-DB assumption is currently a fiction.** The locked decision in `MEMORY.md` says Laravel+Symfony share MySQL during migration. The current `mybible-api-app` container points at a Laravel-owned `laravel` schema with no Bible tables. Guarded migrations + canon seeder keep dev/test green; prod cutover will need a separate confirmation that production DB wiring aligns with Symfony's Bible tables (raise in MBA-020 if not already tracked).
- **Symfony `bible` table overload NOT reproduced.** We intentionally split versions and books into separate tables. Anywhere the Symfony data layer still uses the union, a one-off ETL migration is needed before production cutover — out of scope here, flag during QA.
- **Streaming JSON + ETag ordering.** `BibleVersionExporter::stream` has already begun writing headers by the time a 304 would be ideal. The controller must compute the ETag and short-circuit BEFORE handing off to the streamer — order the task 15 implementation accordingly.
- **`max(updated_at)` over `bible_verses`.** If the verses table's `updated_at` is never touched after the version is seeded (Symfony pattern), the export ETag reduces to `version.updated_at` and is O(1). If Symfony touches per-verse rows, add a version-level ETag cache later.
- **Route-model binding with `abbreviation`.** Default global binding is fine because neither model is scoped (no `published()` / soft deletes). If Symfony data introduces hidden/draft versions later, switch to an explicit `resolveRouteBinding` on `BibleVersion` then.
- **No Actions for list/show.** Deliberate — per Architect rules, ship helpers only if a named task consumes them. Future write stories (admin, seed-from-Symfony) will introduce Actions; this story doesn't fabricate them.
- **`BibleCanonSeeder` cross-domain coupling.** Reading `BibleBookCatalog::BOOKS` from the Reference domain is fine (catalog data is canonical and domain-agnostic), but localized names require pulling static arrays from `RomanianFormatter` / `EnglishFormatter` / `HungarianFormatter`. If those class internals aren't public, expose them via a new `LanguageFormatter::bookNames(): array` method in MBA-006 before task 6 — raise a follow-up if that's not already reachable.
- **Rate limiting (500 rpm) not wired.** Story mentions it in prose but gives no middleware; deferred to a dedicated rate-limit story. Flag in review if QA expects enforcement here.
