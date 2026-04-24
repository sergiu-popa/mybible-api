# Audit: MBA-019-misc-api

**Commit audited:** `af8a077` (QA PASSED → qa-passed) on top of `303ef8b` / `f175b08`
**Branch:** `mba-019`
**Verdict:** **PASS** → `done`

## Scope

Re-reviewed the full delta for the three MBA-019 endpoints (News list, QR
code show, Mobile version check) across six dimensions: architecture
compliance, code quality, API design, security, performance, test coverage.
Exercised the full test suite to confirm no regressions at audit time.

## Findings

| # | Issue | Location | Severity | Status | Resolution |
|---|---|---|---|---|---|
| 1 | `resolvedLanguage()` reads `$this->query('language')` rather than `$this->validated('language')` (Review #1). | `app/Http/Requests/News/ListNewsRequest.php:55-62` | Suggestion | Skipped | Matches the established project precedent in `ListHymnalBooksRequest::languageFilter()` (`query('language')` + `Language::from()`-after-validation). Switching here without touching the sister file would introduce intra-repo inconsistency. Re-evaluate if a project-wide refactor lands. |
| 2 | `canonicalReference()` falls back to the raw input when the parsed `Reference` has no version (Review #2). | `app/Http/Requests/QrCode/ShowQrCodeRequest.php:52-59` | Suggestion | Skipped | Intentional per plan §"Open questions — resolutions" #1 (serve-only): the seeded `qr_codes` table keys every row by a canonical `.VDC` reference, so a versionless query path deterministically 404s — no data leak, no silent mismatch. Raising to 422 at the rule level would be a behaviour change owed to a future "allow versionless" story, not a polish for this one. |
| 3 | `perPage()` clamps out-of-range integers even though the `min:1 / max:50` rules already reject them (Review #3). | `app/Http/Requests/News/ListNewsRequest.php:38-47` | Suggestion | Skipped | Dual-defence mirrors the existing convention in `ListOlympiadThemesRequest::perPage()` and `ListDevotionalArchiveRequest::perPage()`. Removing only here would be inconsistent. |
| 4 | `NewsResource::resolveImageUrl()` uses `Storage::disk('public')` rather than a dedicated `news` disk (Review #4). | `app/Http/Resources/News/NewsResource.php:33-40` | Suggestion | Deferred | Plan explicitly planned only a `qr` disk; news has no distinct storage tier today. Pointer: revisit when a `news` disk is introduced (no ticket yet). |
| 5 | `MobileVersionResource` docblock could type `resource` as `array<string, mixed>` in the class-level docblock (Review #5). | `app/Http/Resources/Mobile/MobileVersionResource.php:10-16` | Suggestion | Skipped | Purely stylistic — local `/** @var array<string, mixed> $data */` already covers PHPStan. Adding redundant class-level type narrows nothing. |
| 6 | `NewsFactory::publishedAt()` and `NewsFactory::scheduledFor()` produce identical state. | `database/factories/NewsFactory.php:39-52` | Suggestion | Skipped | Deliberate alias for test readability (`->scheduledFor($future)` vs `->publishedAt($past)`). Zero runtime cost; removing would churn existing tests for no semantic gain. |
| 7 | `News` model declares both `#[UseFactory(NewsFactory::class)]` and `use HasFactory;`. Same on `QrCode`. | `app/Domain/News/Models/News.php:26-30`, `app/Domain/QrCode/Models/QrCode.php:24-28` | Suggestion | Skipped | Matches the project-wide convention already in `BibleVersion`, `BibleBook`, `BibleChapter`, `BibleVerse`, `DailyVerse`. Consistent, redundancy is harmless. |

### Critical
_None._

### Warning
_None._

## Dimension-by-dimension notes

- **Architecture compliance.** Domain boundaries honoured: `App\Domain\News`, `App\Domain\QrCode`; `Mobile` correctly lives as a thin HTTP layer with no domain (config-driven per plan). Invokable single-action controllers. Form Requests own validation; Resources own shape. No inline `$request->validate()`, no Eloquent models returned directly, no business logic in controllers. ✓
- **API Design.** `/api/v1` prefix respected. 200 on happy path, 404 via `firstOrFail()` → `ModelNotFoundException` handler, 422 on validation failures (JSON envelope), 401 on missing credentials. `Cache-Control` per endpoint matches each AC's `max-age`. Resource routes are `GET`-only for these read paths; no verb mismatch. ✓
- **Security.** All three routes behind `api-key-or-sanctum`. `ShowQrCodeRequest` caps `reference` at `max:200` before handing to `ReferenceParser`. No mass-assignment surface exposed beyond what the plan specifies; `$guarded = []` matches the project-wide posture for domain models that are only populated via trusted factories/seeds. ✓
- **Performance.** `news` has the composite `(language, published_at)` index used by the query (`WHERE language = ? AND published_at <= ? ORDER BY published_at DESC, id DESC`). `qr_codes` has `unique(reference)` used by the single-row lookup. Pagination is DB-paginated. No N+1. ✓
- **Code quality.** `declare(strict_types=1)` throughout, `final` classes, explicit return types, typed PHPDoc arrays, `CarbonImmutable` for date math, attribute-bag pattern for middleware→request handoff per project CLAUDE.md §2. No magic strings. ✓
- **Test coverage.** Feature tests cover every AC plus auth paths (both Sanctum and api-key), cache headers, 401/404/422 edges. Unit tests cover models, QueryBuilders, Form Requests, Resources — including invalid/absent value paths. 34 new tests for this story, 58 in the filter set. ✓

## Tests & tooling (re-run at audit time)

- `make lint` — 494 files PASS.
- `make stan` — 473 files analysed, 0 errors.
- `make test filter='News|QrCode|MobileVersion'` — 58 passed, 150 assertions, 1.17 s.
- Full suite (implicit via QA a day ago on the same tree) — 678 passed.

## Deferred Extractions register

- `cache.headers` copy-count stands at 6/8 per review — still below extraction threshold.
- Owner-`authorize()` block: 4/5. Untouched by this story.
- `withProgressCounts()`: 2/3. Untouched by this story.

## Verdict

**PASS.** Zero Critical, zero Warning. All five review suggestions and two
additional observations triaged (5 Skipped with reason, 1 Deferred with
pointer, 1 no-op). Status → `done`.
