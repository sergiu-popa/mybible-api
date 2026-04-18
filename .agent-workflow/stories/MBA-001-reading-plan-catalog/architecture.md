# Architecture: MBA-001 — Reading Plan Catalog

## Overview

Introduce a single new bounded context, `ReadingPlans`, that owns the public
catalog: plans → days → fragments. All textual fields are multilingual via
JSON columns keyed by language with `en` fallback; Bible references are stored
as raw strings and parsed at read time. Two public read endpoints (list, show)
serve the data; no auth in this story (the API-key middleware from MBA-002
will be applied to both routes when it lands).

This is the catalog-only slice of the original combined story. Subscriptions
and lifecycle live in MBA-003 and MBA-004.

---

## Domain Changes

### Domain layout

```
app/Domain/
├── Shared/
│   └── Enums/
│       └── Language.php                  // En, Ro, Hu (string-backed)
└── ReadingPlans/
    ├── Models/
    │   ├── ReadingPlan.php
    │   ├── ReadingPlanDay.php
    │   └── ReadingPlanDayFragment.php
    ├── Enums/
    │   ├── ReadingPlanStatus.php         // Draft, Published
    │   └── FragmentType.php              // Html, References
    ├── QueryBuilders/
    │   └── ReadingPlanQueryBuilder.php
    └── Support/
        └── LanguageResolver.php
```

### Migrations

All migrations are SQLite-compatible (json + string types only).

#### `reading_plans`
| Column | Type | Notes |
|---|---|---|
| `id` | bigIncrements | |
| `slug` | string | unique |
| `name` | json | `{ "en": "...", "ro": "...", "hu": "..." }` |
| `description` | json | same shape |
| `image` | json | URL per language (DigitalOcean Spaces) |
| `thumbnail` | json | URL per language, smaller asset for mobile lists |
| `status` | string | enum cast to `ReadingPlanStatus`, default `draft` |
| `published_at` | timestamp | nullable |
| `timestamps` | | |
| `softDeletes` | | |

Index: `(status, published_at)` for the public listing query.

#### `reading_plan_days`
| Column | Type | Notes |
|---|---|---|
| `id` | bigIncrements | |
| `reading_plan_id` | foreignId | cascade on delete |
| `position` | unsignedSmallInteger | 1-indexed |
| `timestamps` | | |

Unique: `(reading_plan_id, position)`.
**No `name` column** (per product decision; may be added later).

#### `reading_plan_day_fragments`
| Column | Type | Notes |
|---|---|---|
| `id` | bigIncrements | |
| `reading_plan_day_id` | foreignId | cascade on delete |
| `position` | unsignedSmallInteger | 1-indexed within the day |
| `type` | string | enum cast to `FragmentType` |
| `content` | json | shape depends on `type` (see below) |
| `timestamps` | | |

Unique: `(reading_plan_day_id, position)`.

Content shape:
- `type = html` → `{ "en": "<p>…</p>", "ro": "…", "hu": "…" }`
- `type = references` → `["GEN.1-2", "MAT.5:27-48"]`

### Relations

- `ReadingPlan` → `hasMany(ReadingPlanDay)` ordered by `position`.
- `ReadingPlanDay` → `belongsTo(ReadingPlan)`, `hasMany(ReadingPlanDayFragment)` ordered by `position`.
- `ReadingPlanDayFragment` → `belongsTo(ReadingPlanDay)`.

### QueryBuilder

`ReadingPlanQueryBuilder` (wired via `newEloquentBuilder()` on the model):
- `published(): self` — `where('status', Published)->whereNotNull('published_at')`.
- `withDaysAndFragments(): self` — eager-loads the full tree for the show endpoint.

---

## Actions & DTOs

**None for this story.** Catalog reads do not mutate state. Actions arrive
in MBA-003 with the first write operations.

### Support classes (not Actions)

- `App\Domain\Shared\Enums\Language` — `final enum Language: string { case En = 'en'; case Ro = 'ro'; case Hu = 'hu'; }`. Methods: `static fromRequest(?string $value, self $fallback = self::En): self` (returns the enum case for a 2-letter code or the fallback when null/unknown).

- `LanguageResolver::resolve(array $map, Language $language, Language $fallback = Language::En): ?string` — returns `$map[$language->value]`, then `$map[$fallback->value]`, then `null`. Pure, no DB.

Bible reference parsing is **out of scope** for this story. References are
stored and returned as raw strings; resolution into actual verse text lives
in the future Bible-content domain.

---

## Events & Listeners

None.

---

## HTTP Endpoints

Both routes registered in `routes/api.php` inside the existing `Route::prefix('v1')`
group. Single-action invokable controllers under
`App\Http\Controllers\Api\V1\ReadingPlans\…`. **No auth middleware** in this
story — the routes are public until MBA-002 lands and adds `api-key`.

| Method | Path | Controller | Form Request | Resource |
|---|---|---|---|---|
| GET | `/reading-plans` | `ListReadingPlansController` | `ListReadingPlansRequest` | `ReadingPlanResource` (collection, paginated) |
| GET | `/reading-plans/{plan:slug}` | `ShowReadingPlanController` | `ShowReadingPlanRequest` | `ReadingPlanResource` (with days + fragments) |

### Form Requests

- `ListReadingPlansRequest`
  - `language`: `nullable|string|in:en,ro,hu`
  - `per_page`: `nullable|integer|min:1|max:100` (default `15`)
- `ShowReadingPlanRequest`
  - `language`: `nullable|string|in:en,ro,hu`

Both Form Requests resolve `language` into a `Language` enum via a small
`language(): Language` helper for the controller to read directly.

### API Resources

- `ReadingPlanResource` — `{ id, slug, name, description, image, thumbnail, status, published_at, days? }`. `name`/`description`/`image`/`thumbnail` are language-resolved strings via `LanguageResolver`. `days` is included only when the relation is loaded (i.e. on the show endpoint).
- `ReadingPlanDayResource` — `{ id, position, fragments }`.
- `ReadingPlanDayFragmentResource` — `{ id, position, type, content }`. For `html`, `content` is the language-resolved string. For `references`, `content` is the raw array of strings as stored (e.g. `["GEN.1-2", "MAT.5:27-48"]`).

The `language` is read from the request via the Form Request helper and
threaded into resources through `$request->input('language')` or a small
container-bound helper. Recommended path: a tiny middleware
`ResolveRequestLanguage` registered on the v1 group that resolves the
`Language` enum once and binds it into the container as
`reading-plans.language`. Resources read from the container, keeping nested
resource children free of `$request` plumbing.

---

## Seeders & Factories

- `ReadingPlanFactory` (states: `published()`, `draft()`).
- `ReadingPlanDayFactory`.
- `ReadingPlanDayFragmentFactory` (states: `html()`, `references()`).
- `ReadingPlanSeeder` produces one 7-day published plan in `en` + `ro`
  (Hungarian content optional in v1 — used to verify fallback-to-en path).
  Wire into `DatabaseSeeder`.

---

## Testing Strategy

| Layer | Tests |
|---|---|
| Support unit | `LanguageResolverTest` (matched language, fallback to en, neither available); `LanguageEnumTest` (`fromRequest` valid/invalid/null). |
| QueryBuilder | `ReadingPlanQueryBuilderTest::published_excludes_drafts_and_unpublished`. |
| API Resources | `ReadingPlanResourceTest` (language resolution, fallback path, days only when loaded); `ReadingPlanDayFragmentResourceTest` (html resolution, references returned as raw array). |
| Form Requests | `ListReadingPlansRequestTest`, `ShowReadingPlanRequestTest` (validation rules, language helper). |
| HTTP feature | `ListReadingPlansTest` (returns published only, paginates, language filter, default per_page=15, max enforced); `ShowReadingPlanTest` (full tree, language fallback per fragment, references returned as raw strings, 404 on unknown slug). |

---

## Risks & Open Questions

### Risks

1. **Hungarian content gap (resolved-as-acceptable).** Seeders provide
   `en` + `ro` only; the `hu` fallback path is exercised via the resource
   tests but the seeded data has no Hungarian copy. Confirmed acceptable
   for v1 — content team will populate via the future admin CRUD story.

2. **References are unvalidated raw strings.** The API stores and returns
   whatever string is provided. A typo at content-entry time will silently
   round-trip. Mitigation: validation lands when the Bible-content domain
   ships and provides a canonical book registry. Not in this story.

3. **JSON column ergonomics in tests.** Asserting against multilingual JSON
   columns via `assertJsonPath` requires careful key paths; tests should
   use the parsed JSON helpers consistently to avoid brittleness.

### Resolved Decisions

- Hungarian content is **not** seeded for v1; fallback is sufficient.
- `slug` is **mutable**; clients must use `id` for stable internal refs.
- `references` fragments return **raw strings only**; reference resolution
  (fetching actual verse text) is a later story.

---

## Files Touched / Created

- `database/migrations/` — 3 new files
- `database/factories/` — 3 new factories
- `database/seeders/ReadingPlanSeeder.php` + wire into `DatabaseSeeder.php`
- `app/Domain/Shared/Enums/Language.php`
- `app/Domain/ReadingPlans/…` — full new domain (no Actions/DTOs and no parser in this story)
- `app/Http/Controllers/Api/V1/ReadingPlans/{ListReadingPlans,ShowReadingPlan}Controller.php`
- `app/Http/Requests/ReadingPlans/{ListReadingPlans,ShowReadingPlan}Request.php`
- `app/Http/Resources/ReadingPlans/{ReadingPlan,ReadingPlanDay,ReadingPlanDayFragment}Resource.php`
- `app/Http/Middleware/ResolveRequestLanguage.php` (small, registered on the v1 group)
- `routes/api.php` — register the 2 routes inside the existing `v1` group
- `tests/…` — unit + feature suite per the testing table above
