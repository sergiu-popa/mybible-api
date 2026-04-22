# Plan: MBA-019-misc-api

## Approach

Three independent read-only endpoints that share nothing but a middleware stack and a cache-friendly posture. Group them at the routing and test-file level but keep each endpoint's Action/Resource/Request isolated under its own Domain: `App\Domain\News`, `App\Domain\QrCode`. Mobile version check has no Domain — it's a thin controller over `config('mobile')` (no model, no Action: reading config is not business logic worth wrapping). Apply the Laravel `cache.headers` middleware alias at the route group level for the three different `max-age` values rather than hand-rolling `Cache-Control` per controller (matches the precedent adopted in MBA-013's plan).

Scope is small enough to ship as one story; no split recommended. The three endpoints have no shared model/Action surface, so cross-endpoint regression is unlikely and the test file can stay grouped.

## Open questions — resolutions

1. **QR generation vs. serve-only.** Serve-only. The story's dependency on MBA-006 is purely for `ReferenceParser::parseOne()` validation of `?reference=`. `qr_codes` table stores pre-rendered blobs keyed by canonical reference; a missing row is a `404`. Dynamic generation is out of scope — if Symfony generates on demand today, port that as a follow-up story behind `bacon/bacon-qr-code`. Flagged as a risk.
2. **News soft-delete / unpublish.** Mirror Symfony: filter by `published_at <= now()` and (if the column exists) `published_at IS NOT NULL`. No `deleted_at` column — unpublishing means clearing `published_at`. Confirm column names when the shared-DB wiring lands (MBA-020).
3. **Force-update contract.** Preserve field names verbatim (`minimum_supported_version`, `latest_version`, `update_url`, `force_update_below`). Mobile clients already ship against this shape; renaming is a breaking change with no upside. Document in Resource docblock so Scramble emits the stable contract.

## Domain layout

```
app/Domain/News/
├── Models/News.php                  # published_at filter + language scope
└── QueryBuilders/NewsQueryBuilder.php

app/Domain/QrCode/
├── Models/QrCode.php                # reference (canonical) + image path
└── QueryBuilders/QrCodeQueryBuilder.php

app/Http/Controllers/Api/V1/
├── NewsController.php               # invokable, __invoke(ListNewsRequest)
├── QrCodeController.php             # invokable, __invoke(ShowQrCodeRequest)
└── MobileVersionController.php      # invokable, reads config('mobile')

app/Http/Requests/
├── ListNewsRequest.php
├── ShowQrCodeRequest.php
└── ShowMobileVersionRequest.php

app/Http/Resources/
├── NewsResource.php                 # +NewsCollection via ResourceCollection
├── QrCodeResource.php
└── MobileVersionResource.php

config/mobile.php                    # ios + android keys, env-driven
```

No `Actions/` directory for QrCode or Mobile — the controllers are thin reads with no state change, and wrapping a single Eloquent query in an Action for ceremony's sake creates dead-weight indirection flagged by Review. `News` gets no Action either; listing is a pure QueryBuilder call.

## Key types

| Type | Role |
|---|---|
| `News` | Eloquent model. Casts `published_at` to `datetime`. `$fillable` matches the table columns from the shared DB. Uses `NewsQueryBuilder` via `newEloquentBuilder()`. |
| `NewsQueryBuilder` | `published()` (published_at ≤ now), `forLanguage(Language)` (iso2 match), `newestFirst()`. Consumed by `NewsController`. |
| `QrCode` | Eloquent model. `reference` (canonical string), `url`, `image_path` (relative to `qr` disk). `imageUrl()` accessor resolves via `Storage::disk('qr')->url(...)`. Uses `QrCodeQueryBuilder`. |
| `QrCodeQueryBuilder` | `forReference(string $canonical)` — exact match on the canonical reference. Consumed by `QrCodeController`. |
| `ListNewsRequest` | Validates `language` (nullable, iso2, falls back to `$request->attributes->get(ResolveRequestLanguage::ATTRIBUTE_KEY)`), `page`, `per_page` (max 50, default 20). |
| `ShowQrCodeRequest` | Validates `reference` (required, string). `prepareForValidation()` runs `ReferenceParser::parseOne()` on the input; on `InvalidReferenceException` adds a `422` error via `failedValidation()`. The parsed canonical string is attached to `$request->attributes` under a `CANONICAL_REFERENCE` const so the controller does not re-parse. |
| `ShowMobileVersionRequest` | Validates `platform` in `['ios','android']`. Required. |
| `NewsResource` / `NewsCollection` | Paginated shape. `content` optional (only on detail — this story is list-only, so omit `content` entirely or expose truncated `summary`; AC 2 marks `content?` as nullable so emit `null` unless the column is present and requested). `image_url` resolved via `Storage`. |
| `QrCodeResource` | `{ reference, url, image_url }`. |
| `MobileVersionResource` | Flat mapping of `config('mobile.{platform}')`; preserves AC 9 field names verbatim. |

## HTTP endpoints

| Method + Path | Controller | Request | Resource | Middleware | Cache |
|---|---|---|---|---|---|
| `GET /api/v1/news` | `NewsController` | `ListNewsRequest` | `NewsCollection` | `api-key-or-sanctum`, `resolve-language`, `cache.headers:public;max_age=300;etag` | 300s public |
| `GET /api/v1/qr-codes` | `QrCodeController` | `ShowQrCodeRequest` | `QrCodeResource` | `api-key-or-sanctum`, `cache.headers:public;max_age=86400;etag` | 86 400s public |
| `GET /api/v1/mobile/version` | `MobileVersionController` | `ShowMobileVersionRequest` | `MobileVersionResource` | `api-key-or-sanctum`, `cache.headers:public;max_age=300` | 300s public |

Route-model binding: none. `QrCode` is looked up via the parsed canonical reference (string, not id); the controller calls `QrCode::query()->forReference(...)->firstOrFail()` to keep the 404 path going through the standard JSON exception handler.

## Data & migrations

Two new tables (both follow Laravel plural convention; guarded with `Schema::hasTable()` per the MBA-007 precedent so the seeded Laravel migration does not collide with a shared-DB ETL):

| Table | Columns | Index |
|---|---|---|
| `news` | `id`, `language` (char 2), `title`, `summary`, `content` (text nullable), `image_path` (nullable), `published_at` (nullable, indexed), `created_at`, `updated_at` | `(language, published_at desc)` |
| `qr_codes` | `id`, `reference` (string), `url`, `image_path`, `created_at`, `updated_at` | `unique(reference)` |

No migration for mobile version — `config/mobile.php` is the source of truth, env-driven per the story.

`config/mobile.php`: one array per platform; keys match AC 9 exactly. Add the file; do not rename existing env vars if they already ship from Symfony (the shape is locked by mobile clients).

## Tasks

- [ ] 1. Create `config/mobile.php` with `ios` and `android` keys whose inner shape matches AC 9 verbatim, all values env-driven. Append the new env vars to `.env.example` (not `.env`).
- [ ] 2. Create `news` and `qr_codes` migrations with `Schema::hasTable()` guards. Add the composite index `(language, published_at desc)` on `news` and `unique(reference)` on `qr_codes`. Create factories for both models.
- [ ] 3. Create `App\Domain\News\Models\News` with casts, `$fillable`, and `newEloquentBuilder()` wiring to `NewsQueryBuilder`. Unit test: cast of `published_at`, mass-assignment surface.
- [ ] 4. Create `App\Domain\News\QueryBuilders\NewsQueryBuilder` with `published()`, `forLanguage(Language)`, `newestFirst()`. Unit test (`RefreshDatabase`): published filter excludes future-dated and null rows; language filter narrows; ordering is `published_at DESC, id DESC` for deterministic pagination.
- [ ] 5. Create `App\Domain\QrCode\Models\QrCode` with `imageUrl()` accessor (resolved via `Storage::disk('qr')`) and `newEloquentBuilder()` wiring to `QrCodeQueryBuilder`. Unit test: `imageUrl()` returns a URL string, and returns `null` when `image_path` is null.
- [ ] 6. Create `App\Domain\QrCode\QueryBuilders\QrCodeQueryBuilder::forReference(string)` — exact canonical-string match. Unit test: returns the single matching row; returns empty on miss.
- [ ] 7. Create `ListNewsRequest` extending `FormRequest`: rules for `language` (nullable iso2), `page`, `per_page` (default 20, max 50). `validated()` falls back to `$request->attributes->get(ResolveRequestLanguage::ATTRIBUTE_KEY)` when `language` is absent. Unit test: validation rules + language fallback.
- [ ] 8. Create `ShowQrCodeRequest`: rule `reference` required. Override `prepareForValidation()` to run `ReferenceParser::parseOne()` and attach the canonical string to `$request->attributes` under a new `public const CANONICAL_REFERENCE` on the Request class. Catch `InvalidReferenceException` and rethrow as a 422 via `failedValidation()` (or add a custom validation rule wrapping the parser). Unit test: happy path exposes the canonical on the attributes bag; invalid input produces a 422 JSON envelope.
- [ ] 9. Create `ShowMobileVersionRequest`: rule `platform` required, `in:ios,android`. Unit test: accepts both platforms; rejects missing / unknown.
- [ ] 10. Create `NewsResource` (+ `NewsCollection` for the paginated shape) matching AC 2. Unit test: structure match via `assertJsonStructure`.
- [ ] 11. Create `QrCodeResource` matching AC 6. Unit test: structure match.
- [ ] 12. Create `MobileVersionResource`. Its `toArray()` reads `config('mobile.'.$platform)` directly — no model. Preserve field names from AC 9 verbatim. Unit test: structure match; field names are NOT renamed.
- [ ] 13. Create `NewsController`, `QrCodeController`, `MobileVersionController` as invokable single-action controllers.
- [ ] 14. Register routes in `routes/api.php` under the `/api/v1` group with the `api-key-or-sanctum` stack. Wrap News in `resolve-language` middleware. Apply `cache.headers` middleware per endpoint with the max-ages in the table above; include `etag` for news and qr-codes.
- [ ] 15. Feature test `NewsControllerTest`: list with explicit `?language=ro`; list with language fallback from `ResolveRequestLanguage` attribute; pagination (`per_page`, default + max); `Cache-Control` header; auth required (401 without api-key/sanctum).
- [ ] 16. Feature test `QrCodeControllerTest`: happy path for a reference with a stored QR; `404` for a reference that has no stored QR; `422` for an unparseable reference; `Cache-Control: public, max-age=86400` header; auth required.
- [ ] 17. Feature test `MobileVersionControllerTest`: `ios` and `android` return their config shape; `422` for missing/unknown platform; `Cache-Control: public, max-age=300` header; auth required (`X-Api-Key` alone is sufficient).
- [ ] 18. Run `make lint-fix`, `make stan`, then `make test --filter='News|QrCode|MobileVersion'`; finally run `make check` before marking the story ready for review.

## Risks & notes

- **QR generation deferred.** If Symfony actually generates QRs on demand and mobile clients rely on always-fresh QRs for arbitrary references, serve-only means gaps. Confirm during engineering; if false, open a follow-up story to port the generation path (likely via `bacon/bacon-qr-code` into a queued job that writes to the `qr` disk).
- **Shared-DB news column names.** The story assumes `published_at`; Symfony may use `publication_date` or similar. The migration/model should match whatever the shared DB ships. Verify before finalising migration.
- **`cache.headers` middleware alias.** Laravel ships it out of the box. No new package. Confirm it's not stripped from `bootstrap/app.php` middleware aliases during MBA-005's bootstrap rewrite; add it back if so.
- **News resource `content` field.** AC 2 marks `content` as optional. Emit `null` on list responses; a future detail endpoint (out of scope) would populate it. Do not conditionally hide the key — Scramble-friendly contracts prefer explicit `null`.
- **`ShowQrCodeRequest` double-parse avoidance.** Parsing a reference twice (once in the Request, once in the controller) would both duplicate work and risk divergence if the parser evolves. The `ATTRIBUTE_KEY` pattern (per project CLAUDE.md §2 "Middleware → Downstream Data Passing") is reused here at the Form-Request layer — same precedent, same reason.
- **`image_url` from `Storage::disk('qr')`.** The disk must be configured in `config/filesystems.php` with a public URL; MBA-020 env wiring. If the disk does not exist at request time, `Storage::disk('qr')->url(...)` throws — add a disk entry to `config/filesystems.php` in task 2 with a `public` default pointing to `storage/app/public/qr`, and note the s3 production override for MBA-020.
- **Deferred Extractions register.** Zero owner-gated Form Requests, zero lifecycle Actions, no `withProgressCounts`. Tripwire untouched. The `Cache-Control` / `cache.headers` middleware pattern now appears in MBA-007 (1 copy), MBA-012 (1), MBA-013 (3), MBA-014 (0, deferred), MBA-016 (1), MBA-019 (3). If the total crosses 8 copies across the portfolio, consider a per-route macro or a dedicated middleware. Not tripped yet; Improver to tally on story close.
- **No split recommended.** Three endpoints, no shared surface, small test matrix. The grouping is cohesive enough (all public-ish read-only, all cacheable) that splitting would multiply branch/review overhead with no design upside.
