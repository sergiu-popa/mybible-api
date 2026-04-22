# Plan: MBA-008-verses-and-daily-verse

## Approach

Two thin endpoints over the shared `verse` and `daily_verse` tables. Verse retrieval reuses the `ReferenceParser` from MBA-006 — the Form Request normalises the split-param form (`book`/`chapter`/`verses`/`version`) into a single canonical string, then hands one parsed `array<Reference>` to `ResolveVersesAction`. Daily verse is a single-row read by date with conditional language filtering pending MBA-007's schema confirmation (see Open questions). Both endpoints sit behind `api-key-or-sanctum` + `resolve-language`, matching the reading-plan stack.

## Dependencies & sequencing

- **Hard block on MBA-007.** `Verse` / `BibleVersion` / `Book` Eloquent models, their table names, column names, and `bible` table shape are all owned by MBA-007. This plan names the models and columns it needs, but does not migrate or model them — engineer must implement MBA-007 first, or this story ships atop agreed-upon model stubs.
- **Consumes MBA-006.** `ReferenceParser`, `Reference`, `InvalidReferenceException`.

## Open questions — resolutions

1. **Partial resolution status code.** `200` with `meta.missing` — confirmed per story's own recommendation. `207` reserved for the rare "some failed, some succeeded with different error shapes" case; we have one uniform shape.
2. **Daily verse table schema (CRITICAL).** The Symfony `daily_verse` table has **no `language` column** — it stores one row per `for_date` globally, with a `reference` string and `image_cdn_url`. No `version`, no `text` (the Symfony API returns reference-only and clients fetch the verse text themselves via the verse endpoint). Two resolutions available — **engineer must confirm with product before writing the migration/model**:
   - **Option A (match Symfony, recommended):** Daily verse is language-agnostic. Drop AC 8's `?language=` and AC 10's "no daily verse for this `{language, date}`". Response becomes `{ data: { date, reference, image_url } }` and the client resolves the verse text by calling `/api/v1/verses?reference=...` with its preferred version. This matches the existing table, avoids an admin migration, and honours the "shared DB during migration" lock-in.
   - **Option B (schema change):** Add `language` + `version` columns to `daily_verse`. Requires Admin-side backfill and an Admin UI change — Admin is out of scope per the memory, so this is blocked.
   - **Plan assumes Option A below.** If product insists on B, Risks section covers the cut.
3. **Language fallback for daily verse.** Moot under Option A. Under Option B: `404` (clients know what they asked for).
4. **Response field for verse text.** Symfony `Verse::jsonSerialize()` uses `content`. Rename to `text` in the Resource to match AC 2.
5. **Route-model binding for `daily-verse` date.** Not needed — `date` is a query param, not a URL segment. Today (`for_date = today`) is the default when `date` is omitted.

## Domain layout

```
app/Domain/Verses/
├── Actions/
│   ├── ResolveVersesAction.php
│   └── GetDailyVerseAction.php
├── DataTransferObjects/
│   ├── ResolveVersesData.php
│   └── VerseLookupResult.php
├── Models/
│   └── DailyVerse.php
├── QueryBuilders/
│   ├── VerseQueryBuilder.php
│   └── DailyVerseQueryBuilder.php
└── Exceptions/
    └── NoDailyVerseForDateException.php
```

`Verse` and `BibleVersion` models live under `App\Domain\Bible\*` (MBA-007). This story adds a `VerseQueryBuilder` for the reference-lookup scopes that MBA-007 won't own. If MBA-007 already extracted a `VerseQueryBuilder`, this story extends it rather than duplicating.

## Key types

| Type | Role |
|---|---|
| `ResolveVersesData` (readonly DTO) | `array<Reference> $references`, `string $version`. Produced by `ResolveVersesRequest::toData()`. Parser is invoked in the Form Request so `InvalidReferenceException` surfaces through the exception handler mapped to `422`. |
| `VerseLookupResult` (readonly DTO) | `array<Verse> $verses`, `array<array{version:string, book:string, chapter:int, verse:int}> $missing`. Returned by `ResolveVersesAction`. Controller wraps into the Resource + `meta.missing`. |
| `ResolveVersesAction` | `handle(ResolveVersesData): VerseLookupResult`. Groups the input `Reference[]` by `(version, book, chapter)` and issues one `WHERE (version, book, chapter) = … AND position IN (…)` per group. Expands whole-chapter refs via `VerseQueryBuilder::wholeChapter()`. Diffs resolved positions against the requested expansion to build `missing`. |
| `GetDailyVerseAction` | `handle(\DateTimeImmutable $date): DailyVerse`. Delegates to `DailyVerseQueryBuilder::forDate()`. Throws `NoDailyVerseForDateException` on miss. |
| `DailyVerse` model | Maps to existing `daily_verse` table: `id`, `for_date` (date, unique), `reference` (string), `image_cdn_url` (nullable text). Read-only from this API's POV (no writes). No timestamps unless MBA-007 audit shows the Symfony table has them — confirm with `database-schema` during engineering. |
| `VerseQueryBuilder` | `forVersion(string)`, `forBook(string)`, `forChapter(int)`, `withPositions(array<int>)`, `wholeChapter()`. Extended with `lookupReferences(array<Reference>): Collection<Verse>` — the batched multi-group query consumed by `ResolveVersesAction`. |
| `DailyVerseQueryBuilder` | `forDate(\DateTimeImmutable): ?DailyVerse`. Single consumer: `GetDailyVerseAction`. |
| `NoDailyVerseForDateException` (extends `\RuntimeException`) | Carries the requested date. Rendered as `404` with the `{ message: "No daily verse for this date." }` body in `bootstrap/app.php`. |
| `ResolveVersesRequest` | Validates split vs. canonical form. Normalises split form to a canonical reference string. Invokes `ReferenceParser` in `passedValidation()` or an accessor `toData()`. Resolves default version (query > user profile > `config('bible.default_version_by_language')` > `422`). |
| `DailyVerseRequest` | `date` (nullable, `Y-m-d`, past-or-today). No `language` under Option A. |
| `VerseResource` | Shape: `{ version, book, chapter, verse, text }`. `verse` is the Eloquent `position` column aliased; `text` is `content` aliased. |
| `VerseCollection` | `AnonymousResourceCollection` override. Adds `meta.missing` from the controller's `VerseLookupResult`. |
| `DailyVerseResource` | Shape (Option A): `{ date, reference, image_url }`. Option B adds `version`, `text`, `source`. |

## HTTP endpoints

| Method | Path | Controller | Request | Resource | Middleware |
|---|---|---|---|---|---|
| `GET` | `/api/v1/verses` | `ResolveVersesController` | `ResolveVersesRequest` | `VerseCollection` (of `VerseResource`) | `api-key-or-sanctum`, `resolve-language` |
| `GET` | `/api/v1/daily-verse` | `GetDailyVerseController` | `DailyVerseRequest` | `DailyVerseResource` | `api-key-or-sanctum`, `resolve-language` |

Both invokable single-action controllers. Sibling name parity: `ResolveVerses{Controller,Request,Action,Data}` and `GetDailyVerse{Controller,Action}` + `DailyVerse{Request,Resource}` (controller prefix diverges from the model name intentionally — the controller is a query verb, not a CRUD resource).

`resolve-language` is included because default-version resolution falls back to `config('bible.default_version_by_language')` keyed by the resolved `Language` attribute.

## Cache headers

- `GET /api/v1/daily-verse` — `Cache-Control: public, max-age=3600` (AC 12). Set in controller response.
- `GET /api/v1/verses` — no cache headers; per-user default-version selection breaks shared caching.

## Data & migrations

- **`verses` table** — owned by MBA-007 (columns: `id`, `version`, `book`, `chapter`, `position`, `content` + composite index on `(version, book, chapter, position)`). This story does not touch the migration.
- **`daily_verse` table** — `daily_verse` in shared DB. Migration policy: follow whatever MBA-007 establishes (Option A from this story's open question #1). Expected columns: `id`, `for_date` (date, unique), `reference` (string 25), `image_cdn_url` (text nullable). If MBA-007 ships a `Schema::hasTable()` guard pattern, reuse it here.
- **Config addition.** `config/bible.php` with `default_version_by_language => ['ro' => 'VDC', 'en' => 'KJV', 'hu' => 'KAR']`. If MBA-007 already added this, reuse; otherwise add it here.

## Exception rendering

Add two handlers in `bootstrap/app.php`:

| Exception | Status | Body |
|---|---|---|
| `InvalidReferenceException` | `422` | `{ message: "<exception message>", errors: { reference: [<reason>] } }` |
| `NoDailyVerseForDateException` | `404` | `{ message: "No daily verse for this date." }` |

The `InvalidReferenceException` render is a shared concern — MBA-014 (collections) will hit it too. Ship the handler here; audit for duplication at MBA-014 time.

## Default-version resolution

Three-tier cascade inside `ResolveVersesRequest::resolveVersion()`:

1. `?version=` query param (validated against `BibleVersion::whereAbbreviation()` existence — `422` if unknown).
2. `request->user()?->preferred_version` (column added by MBA-018; guard with `null` coalescing so this story does not depend on MBA-018).
3. `config('bible.default_version_by_language')[Language]`.
4. Reject `422` with `{ errors: { version: ['Version is required.'] } }`.

## Tasks

- [x] 1. Inspect `daily_verse` and `verse` tables via `mcp__laravel-boost__database-schema` in the shared DB; record actual column names in a comment at the top of `DailyVerse.php`. Confirm Option A vs. Option B with product before proceeding.
- [x] 2. Create `config/bible.php` with `default_version_by_language` map. Skip if MBA-007 already created it.
- [x] 3. Create `App\Domain\Verses\Models\DailyVerse` mapped to the `daily_verse` table (`$table`, `$fillable`, casts `for_date => immutable_date`, no timestamps unless table has them). Skip writes.
- [x] 4. Create `App\Domain\Verses\QueryBuilders\DailyVerseQueryBuilder` with `forDate(\DateTimeImmutable): ?DailyVerse`. Wire via `newEloquentBuilder()` on the model.
- [x] 5. Create `App\Domain\Verses\Exceptions\NoDailyVerseForDateException` carrying the requested date.
- [x] 6. Extend `App\Domain\Bible\QueryBuilders\VerseQueryBuilder` (or create if MBA-007 did not) with `lookupReferences(array<Reference>): Collection`. Groups by `(version, book, chapter)`, issues one query per group using `IN` for positions; whole-chapter refs query without position constraint.
- [x] 7. Create `App\Domain\Verses\DataTransferObjects\ResolveVersesData` (readonly, `array<Reference>`, `string $version`).
- [x] 8. Create `App\Domain\Verses\DataTransferObjects\VerseLookupResult` (readonly, resolved verses + missing tuples).
- [x] 9. Create `App\Domain\Verses\Actions\ResolveVersesAction::handle(ResolveVersesData): VerseLookupResult`. Computes expected verse-tuple set from the input `Reference[]`, calls `VerseQueryBuilder::lookupReferences()`, diffs to produce `missing`.
- [x] 10. Create `App\Domain\Verses\Actions\GetDailyVerseAction::handle(\DateTimeImmutable): DailyVerse`. Delegates to the query builder; throws `NoDailyVerseForDateException` on null.
- [x] 11. Create `App\Http\Requests\Verses\ResolveVersesRequest`. Rules: mutual-exclusion of `reference` and split-form, `reference` string pattern allowlist, `book` 3-letter, `chapter` int ≥1, `verses` string (comma/dash/semicolon pattern), optional `version`. `passedValidation()` assembles canonical form if split, then invokes `ReferenceParser` and exposes `toData(): ResolveVersesData` including default-version cascade (query → `$this->user()?->preferred_version` → `config('bible.default_version_by_language')` → fail with `ValidationException::withMessages`).
- [x] 12. Create `App\Http\Requests\Verses\DailyVerseRequest`. Rules: `date` nullable `date_format:Y-m-d` + `before_or_equal:today`. Expose `forDate(): \DateTimeImmutable` defaulting to today.
- [x] 13. Create `App\Http\Resources\Verses\VerseResource` with shape `{ version, book, chapter, verse, text }` (aliasing `position` → `verse`, `content` → `text`).
- [x] 14. Create `App\Http\Resources\Verses\VerseCollection` extending `ResourceCollection`, adds `meta.missing` from the collection's additional-data channel.
- [x] 15. Create `App\Http\Resources\Verses\DailyVerseResource` with Option A shape `{ date, reference, image_url }`.
- [x] 16. Create `App\Http\Controllers\Api\V1\Verses\ResolveVersesController` invokable. Receives `ResolveVersesRequest`, calls `ResolveVersesAction`, wraps into `VerseCollection::make()->additional(['meta' => ['missing' => ...]])`.
- [x] 17. Create `App\Http\Controllers\Api\V1\Verses\GetDailyVerseController` invokable. Returns `DailyVerseResource` with `Cache-Control: public, max-age=3600` header.
- [x] 18. Register the two routes under the `v1` prefix in `routes/api.php` with `api-key-or-sanctum` + `resolve-language` middleware. Name routes `verses.index` and `daily-verse.show`.
- [x] 19. Register `InvalidReferenceException` and `NoDailyVerseForDateException` handlers in `bootstrap/app.php` per the table above.
- [x] 20. Feature test `ResolveVersesController`: single verse, multi-verse range, mixed-verse (`1-3,5`), multi-chapter (`GEN.1:1;2:3`), split-param form, unknown reference → `422`, partial resolution → `200 + meta.missing`, no version supplied + no user + no config fallback → `422`, authenticated-user default-version fallback (stub `preferred_version`), anonymous API-key client with language-based config fallback, missing both auth → `401`/`403`.
- [x] 21. Feature test `GetDailyVerseController`: today (no date), past date, missing date → `404`, `Cache-Control` header asserted, `date` in the future → `422`, unauthenticated → `401`.
- [x] 22. Unit test `ResolveVersesAction`: group-by batching (assert query count ≤ number of distinct `(version, book, chapter)` groups — use `DB::listen`), whole-chapter expansion, missing computation edge cases (all-missing, none-missing, partial).
- [x] 23. Unit test `GetDailyVerseAction`: hit, miss throws `NoDailyVerseForDateException`.
- [x] 24. Unit test `ResolveVersesRequest::toData()` default-version cascade: explicit > user > config > fail. Use the Form Request's `validateResolved()` in a unit harness (see `tests/Feature/ReadingPlans` precedents if present).
- [x] 25. Feature test for `InvalidReferenceException` handler: request with `reference=NOPE.99:1.VDC` asserts the `422` + `errors.reference` envelope.
- [x] 26. Run `make lint-fix`, `make stan`, `make test filter=Verses`; then full `make check` before marking ready for review.

## Risks & notes

- **Symfony `VerseController::show` and `byReference()` don't exist.** The story's "Symfony source" list is aspirational — the Symfony code has `BibleController`, `DailyVerseController`, `RandomVerseController`, and nothing else for verse reads. The `/api/v1/verses` endpoint is therefore **a new API surface**, not a port. No Symfony golden-response to diff against. Document this in the plan artefact set.
- **Symfony `DailyVerseController::lastWeek` returns the last 30 days, not a single date.** The Laravel story adopts a single-date lookup which is a breaking change from Symfony but matches the story's AC 8/9. Acceptable — clients will migrate via the cutover (MBA-020 locks this in).
- **Daily verse has no `language` column** — Open question #2. If Option B is chosen, tasks 3, 4, 10, 12, 15, 17, 21 change shape and a schema migration is added. Plan does not include Option B tasks; a re-plan is required if product chooses B.
- **Daily verse has no `text` column.** AC 11 lists `text` and `source` in the response. Under Option A, `text` and `source` would need to be resolved at read time by joining the daily verse's `reference` string through `ReferenceParser` → `VerseQueryBuilder`. Expensive for a "cheap hit" endpoint. Response shape in this plan drops `text`/`source` and exposes only `{ date, reference, image_url }`. **Confirm with product.** If `text` is required, the plan adds an in-process call to `ResolveVersesAction` inside `GetDailyVerseController` and accepts the cost.
- **`VerseQueryBuilder` ownership.** Plan assumes MBA-007 hasn't already added `lookupReferences()`. Engineer checks at task 6; if already present, this story consumes it (no task 6 change) and extends only if the signature differs.
- **`preferred_version` column.** MBA-018 is the owner; this story's default-version cascade step #2 is null-safe so it's a no-op until MBA-018 lands. Do not add a migration or column accessor here.
- **Default-version config key.** If MBA-007 used a different key name, adopt MBA-007's key in task 2 — do not fork a second config.
- **Request-attribute for `Language`.** Use `$request->attributes->get(ResolveRequestLanguage::ATTRIBUTE_KEY, Language::En)` per the project's middleware convention. Do not re-resolve from `?language=` in the Form Request.
- **No `perPage` on verses.** Bounded by the input reference set. AC 2 is explicit: not paginated. Use `VerseCollection` (not `AnonymousResourceCollection` with paginator).
- **Exception handler placement.** `InvalidReferenceException` handler shipped here is a cross-story concern. Track in the deferred-extractions register after MBA-014 lands its second consumer.
