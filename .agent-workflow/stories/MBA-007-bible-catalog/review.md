# Code Review: MBA-007-bible-catalog

## Verdict

**APPROVE** — no Critical findings, no outstanding Warnings. Suggestions below are non-blocking.

## Scope reviewed

- Four read-only endpoints under `/api/v1/bible-versions` and `/api/v1/books`.
- New `App\Domain\Bible` layer (4 models, 2 QueryBuilders, 2 support classes).
- Migrations for `bible_versions` / `bible_books` / `bible_chapters` / `bible_verses` with `Schema::hasTable()` guards.
- `BibleCanonSeeder` seeding the 66-book canon + chapter rows.
- Factories for all four models.
- Feature tests for every endpoint + unit tests for models, query builders, resources, requests, cache headers, exporter, seeder.

Against `plan.md`: all 16 tasks are checked and the implementation matches the architecture (no rogue domain additions, no speculative Actions/DTOs).

## Findings

### Suggestion

- [ ] **`app/Domain/Bible/Support/BibleVersionExporter.php:46-62`** — The streaming query already joins `bible_books` to order by `books.position`, but then `->select('bible_verses.*')` drops all joined columns. When a book transition is detected, the code fires an extra `BibleBook::query()->findOrFail($currentBookId)` per book (66 queries for a full canon export). Either alias the needed book columns onto the select (`bible_books.abbreviation as book_abbreviation, bible_books.position as book_position, bible_books.id as book_id`) and read them off the verse row, or pre-load `BibleBook::whereIn('id', ...)` once into a keyed map. Bounded at 66 so not a perf blocker — worth cleaning up the next time the exporter is touched.

- [ ] **`app/Domain/Bible/Support/BibleCacheHeaders.php:24-37`** — `forVersionList` clones the query twice and runs two aggregate queries (`max('updated_at')` + `count()`). A single `selectRaw('MAX(updated_at) as max_updated_at, COUNT(*) as total')->first()` would halve the DB work for every list request, including the cheap 304 path. Versions table is small so it's negligible today; flag for the next pass.

- [ ] **`routes/api.php:37-51`** — The `resolve-language` middleware is applied to the whole `bible-versions` and `books` groups, so it runs on `/bible-versions/{v}/export` and `/books/{b}/chapters` too. Plan §HTTP endpoints scopes `resolve-language` only to the list endpoints; the extra middleware on the other two does nothing harmful (no resource reads the attribute there) but diverges from the planned contract. Either move those two routes out of the `resolve-language` group, or update the plan to reflect that it's applied uniformly.

- [ ] **`database/seeders/BibleCanonSeeder.php:42-46`** — `short_names` is seeded with the *long* names (`$names` reused for both). Plan task 6 specified short names come from the `LanguageFormatter` short-name map. Nothing in this story reads `short_names`, so it's currently dead data, but the column will mislead a future consumer. Either drop `short_names` for now, or add a `bookShortName()` method on `LanguageFormatter` and seed it properly (out of scope here — raise as follow-up).

- [ ] **`app/Domain/Bible/Support/BibleVersionExporter.php:53-54`** — The `/** @var BibleVerse $verse */` annotation is redundant; `BibleVerse::query()->lazy()` is already typed as `LazyCollection<int, BibleVerse>`. Remove.

## What works well

- Strict types, `final`, return types, PHPDoc generics on all relations and builders — consistent with existing domains.
- Route-model binding on `abbreviation` is unscoped (per guideline §5b) — correct call given neither model has soft deletes or a published scope.
- Feature tests cover the auth gate, happy path, 404, ETag round-trip (including the 304 short-circuit on the streamed export — the tricky case flagged in the plan).
- ETag computation deliberately includes `count(*)` so pagination can't shift the value (verified by `test_for_version_list_etag_does_not_change_with_pagination`).
- The streaming exporter writes JSON incrementally with correct book/chapter transition bookkeeping; the empty-version case (no verses) still emits a well-formed `{"version":...,"books":[]}`.
- The 304 short-circuit for the export runs *before* `BibleVersionExporter::stream` is invoked, per the plan's risk note.
- No Eloquent models leak through controllers — every endpoint wraps in a Resource.
- No inline `$request->validate()`; every controller takes a Form Request.

## Public API contract check

- `BibleVersionResource.language` is filterable via `?language=`, so it varies across responses in the default (unfiltered) case — not a constant-under-scope. Keeping it is fine.
- `BibleBookResource.testament` is constant for any single book (static taxonomy), but the list endpoint returns both testaments in one call — not a scoped constant. Fine.
- No fields are echoed purely because of a where-clause scope. Clean.

## Deferred extractions tripwire

No changes to the two registers in `.agent-workflow/CLAUDE.md §7` — this story doesn't touch owner-`authorize()` Form Requests or reading-plan lifecycle Actions.
