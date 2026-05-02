# Plan: MBA-022-mobile-coldstart-and-ops-hardening

## Approach

Six surfaces ship in one story because each is small once the glue exists, and
together they harden the API for the same mobile cold-start workload: a cached
**bootstrap aggregator** that calls the existing per-domain Actions (no
duplicated query logic; reuses MBA-021 cache layer with tag-union
invalidation); a **sync delta** endpoint backed by `SoftDeletes` on seven
per-user tables and a per-type Sync Builder pattern that keeps each domain's
query local; **named throttle limiters** plus `TrustProxies` so the limiter
keys on the real client IP; **liveness/readiness split** at `/up` and `/ready`
with a VPC-gated `internal-ops` middleware; **harmonised pagination** behind a
single `PaginatesRead` request trait (default 30, max 100); and a small set of
**observability hooks** (CachedRead route_name tag, slow-query channel,
bootstrap `cold_start` tag).

Soft-delete strategy is uniform: `deleted_at TIMESTAMP NULL` + composite
indexes on the seven per-user tables. Toggle Actions
(`ToggleHymnalFavoriteAction`, `ToggleDevotionalFavoriteAction`,
`ToggleSabbathSchoolFavoriteAction`) switch from hard `delete()` to soft
`delete()`, and from `create()` to **restore-or-create** so the existing
`(user_id, target_id)` UNIQUE indexes need no schema change — a trashed row is
restored (and `updated_at` touched) on re-toggle. Per-passage rows
(SabbathSchoolHighlight) and per-question rows (SabbathSchoolAnswer, Note) get
plain SoftDeletes. Standard `delete()` on Favorite/FavoriteCategory becomes
soft.

Story is large but each surface is independently shippable and the surfaces
don't interlock: see "Risks & notes" §1 for an optional 4-PR split. **No
mandatory split** — the migration + restore-or-create + sync builders form one
coherent change, and the ops surfaces (rate limits, health, observability) are
trivial individually.

## Open questions — resolutions

1. **Soft-delete vs tombstones.** SoftDeletes everywhere, restore-or-create on
   the three toggle Actions. Reason: schema-light (no unique-index rebuild),
   idiomatic Laravel, and trashed rows survive long enough for the sync
   `deleted` array to surface them in any reasonable client polling cadence
   (clients using sync stay within ~30 days; we GC trashed rows older than 90
   days in a follow-up story, not this one). Cascade-on-user-delete still
   applies — user FKs are hard-cascade so a true user delete still purges all
   user data.
2. **Bootstrap TTL.** Keep 300 s as the AC default but expose
   `BOOTSTRAP_CACHE_TTL` env so ops can lower it without redeploying code.
   News-only fast-publish is achieved by the `news` tag flush busting bootstrap
   too (bootstrap inherits `news` in its tag set).
3. **TrustProxies for real client IP.** Project has no `TrustProxies` config
   today. Add it via Laravel 11 middleware DSL (`$middleware->trustProxies(at:
   env('TRUSTED_PROXIES', '*'), …)`) in `bootstrap/app.php`. Default `'*'`
   matches the Docker dev stack; production overrides via env to the LB CIDR.
4. **`/up` Caddy access logs.** Keep — one line per 10 s × 2 droplets is low
   volume and the count is useful for LB sanity. Filter at Vector source if it
   becomes noisy — that is an ops-side change, not API.
5. **`qr_codes` in bootstrap payload.** Yes, per story technical notes. Add a
   new `ListQrCodesAction` returning the full table (cached, tagged `qr`) and
   embed its payload in bootstrap. Adds ~62 KB gzipped; bootstrap stays under
   100 KB total.
6. **Bootstrap `sabbath_school_current_lesson` resolution.** Query lessons
   where `week_start <= today <= week_end` for the language; fall back to
   latest `published_at`. The query lives inside `ShowAppBootstrapAction`'s
   build closure (one DB call per cache miss); not a separate Action because
   only bootstrap consumes it.
7. **Sync per-type 5 000 cap + cursor.** Each per-type Sync Builder enforces
   the cap and returns `max_seen_at` of the last row. The Action emits a
   top-level `next_since` = `min(max_seen_at across truncated types)` if any
   builder hit the cap, else null. Clients with `next_since != null` re-call
   with `since = next_since`; non-truncated types replay a few rows but the
   client dedupes by id (idempotent upsert).
8. **Pagination defaults clash with MBA-021's `ListNewsRequest`** (default 20,
   max 50). Story AC 22 names `ListNewsController` so we raise to 30/100; this
   is non-breaking (callers asking for ≤50 still get what they ask for).
9. **`/up` behaviour change.** Today `/up` pings DB+Redis (effectively
   readiness). LB-side this means readiness was wired to `/up`. The split
   needs the deploy/LB pipeline to re-point its readiness probe from `/up` →
   `/ready` and keep liveness on `/up` — runbook updated; no code action
   beyond the docs.

## Domain layout

```
app/Domain/Mobile/
├── Support/MobileCacheKeys.php             # bootstrap($lang) → "app:bootstrap:{lang}"; tagsForBootstrap()
└── Actions/ShowAppBootstrapAction.php      # composes per-domain Actions; sets `cold_start=true` Sentry tag

app/Domain/Sync/
├── DataTransferObjects/SyncTypeDelta.php   # readonly { upserted: array, deleted: array<int>, maxSeenAt: ?DateTimeImmutable }
├── Sync/SyncBuilder.php                    # interface — fetch(int $userId, DateTimeImmutable $since, int $cap): SyncTypeDelta
├── Sync/Builders/FavoriteSyncBuilder.php
├── Sync/Builders/NoteSyncBuilder.php
├── Sync/Builders/SabbathSchoolAnswerSyncBuilder.php
├── Sync/Builders/SabbathSchoolHighlightSyncBuilder.php
├── Sync/Builders/SabbathSchoolFavoriteSyncBuilder.php
├── Sync/Builders/DevotionalFavoriteSyncBuilder.php
├── Sync/Builders/HymnalFavoriteSyncBuilder.php
└── Actions/ShowUserSyncAction.php          # iterates builders, returns aggregated payload + next_since

app/Domain/QrCode/Actions/ListQrCodesAction.php   # NEW — full-set cached read, used by bootstrap

app/Domain/Favorites/Models/Favorite.php          # MOD — use SoftDeletes
app/Domain/Favorites/Models/FavoriteCategory.php  # MOD — use SoftDeletes
app/Domain/Notes/Models/Note.php                  # MOD — use SoftDeletes
app/Domain/Devotional/Models/DevotionalFavorite.php  # MOD — use SoftDeletes
app/Domain/Hymnal/Models/HymnalFavorite.php          # MOD — use SoftDeletes
app/Domain/SabbathSchool/Models/SabbathSchoolAnswer.php     # MOD — SoftDeletes
app/Domain/SabbathSchool/Models/SabbathSchoolHighlight.php  # MOD — SoftDeletes
app/Domain/SabbathSchool/Models/SabbathSchoolFavorite.php   # MOD — SoftDeletes

app/Domain/Hymnal/Actions/ToggleHymnalFavoriteAction.php           # MOD — restore-or-create
app/Domain/Devotional/Actions/ToggleDevotionalFavoriteAction.php   # MOD — restore-or-create
app/Domain/SabbathSchool/Actions/ToggleSabbathSchoolFavoriteAction.php  # MOD — restore-or-create

app/Http/Controllers/Api/V1/Mobile/ShowAppBootstrapController.php
app/Http/Controllers/Api/V1/Sync/ShowUserSyncController.php
app/Http/Controllers/Health/ShowLivenessController.php       # NEW — pure /up
app/Http/Controllers/Health/ShowReadinessController.php      # NEW — DB + Redis ping for /ready (replaces existing HealthCheckController)
app/Http/Controllers/HealthCheckController.php               # DELETE — replaced by Show{Liveness,Readiness}Controller

app/Http/Requests/Mobile/ShowAppBootstrapRequest.php         # validates ?language=
app/Http/Requests/Sync/ShowUserSyncRequest.php               # validates ?since= ISO-8601 timestamp

app/Http/Resources/Mobile/AppBootstrapResource.php           # shapes the bootstrap payload (no model — wraps an array DTO)
app/Http/Resources/Sync/SyncTypeDeltaResource.php            # shapes one per-type delta
app/Http/Resources/QrCode/QrCodeListItemResource.php         # NEW — slim QR row for bootstrap+list

app/Http/Requests/Concerns/PaginatesRead.php                 # NEW trait — DEFAULT_PER_PAGE=30, MAX_PER_PAGE=100, perPage()

app/Http/Middleware/EnsureInternalOps.php                    # NEW — VPC CIDR allowlist (env INTERNAL_OPS_CIDR), 403 otherwise

app/Support/Observability/SlowQueryListener.php              # NEW — DB::listen hook, threshold 500ms, log channel `slow_query` + Sentry breadcrumb
app/Support/Caching/CachedRead.php                           # MOD — also tag `route_name` from current Request

app/Providers/AppServiceProvider.php                         # MOD — boot(): RateLimiter::for('public-anon'), for('per-user'); SlowQueryListener::register()

bootstrap/app.php                                            # MOD — alias `internal-ops`; trustProxies(); /up + /ready route binds; throttle middleware on rate-limited groups

config/logging.php                                           # MOD — add `slow_query` channel (daily, 14 days)
config/mobile.php                                            # MOD — add `bootstrap.cache_ttl` reading BOOTSTRAP_CACHE_TTL (default 300)
config/cache.php                                             # already wired via MBA-021 — no change

routes/api.php                                               # add bootstrap route, sync route, throttle middleware on existing groups, drop old /up override

database/migrations/2026_05_xx_add_soft_deletes_to_user_tables.php   # NEW — adds deleted_at + (user_id, deleted_at) + (user_id, updated_at) on 7 tables
```

## Key types

| Type | Role |
|---|---|
| `App\Domain\Mobile\Support\MobileCacheKeys` | `bootstrap(Language $lang): string` → `"app:bootstrap:{lang}"`; `tagsForBootstrap(): array` → `['app:bootstrap', 'news', 'daily-verse', 'dev', 'ss', 'ss:lessons', 'bible', 'bible:versions', 'qr']` so any constituent flush busts bootstrap. |
| `App\Domain\Mobile\Actions\ShowAppBootstrapAction` | Constructor-injects every per-domain Action it composes (`ListNewsAction`, `GetDailyVerseAction`, `FetchDevotionalAction`, `ListSabbathSchoolLessonsAction`, `ListBibleVersionsAction`, `ListQrCodesAction`, plus a `MobileVersionResolver` for per-platform versions read from `config/mobile.php`). `execute(Language $lang): array` wraps the assembly in `CachedRead::read()` at TTL `config('mobile.bootstrap.cache_ttl', 300)`. Sets Sentry tag `cold_start: true` once per call (best-effort, same pattern as `CachedRead`). The current-lesson sub-query (week-bracketing) lives inline in the build closure. |
| `App\Domain\Sync\DataTransferObjects\SyncTypeDelta` | `readonly` — `{ array $upserted, array<int> $deleted, ?DateTimeImmutable $maxSeenAt }`. `maxSeenAt` is non-null only if the builder hit its cap. |
| `App\Domain\Sync\Sync\SyncBuilder` | Interface — `public function fetch(int $userId, DateTimeImmutable $since, int $cap): SyncTypeDelta;` plus `public function key(): string` returning the response key (`favorites`, `notes`, `sabbath_school_answers`, …). |
| `App\Domain\Sync\Sync\Builders\<Domain>SyncBuilder` (×7) | Each implements `SyncBuilder`. Queries `Model::withTrashed()->where('user_id', $userId)->where(fn($q) => $q->where('updated_at', '>', $since)->orWhere('deleted_at', '>', $since))->orderBy('updated_at')->limit($cap + 1)->get()`. Splits results into upserted (no `deleted_at`) vs deleted ids. Returns `SyncTypeDelta` with `maxSeenAt` set if more than `$cap` rows were returned. Each builder uses the existing per-domain Resource to serialise upserted rows (consistency with the per-resource read endpoints). |
| `App\Domain\Sync\Actions\ShowUserSyncAction` | Constructor takes `iterable<SyncBuilder>` (all seven, registered via the service container `tag('sync.builder')`). `execute(int $userId, DateTimeImmutable $since): array` calls each builder, assembles `{ synced_at, next_since, <key> => {upserted, deleted} }`. `synced_at` = `now()` (server clock, ISO-8601 UTC). `next_since` = `min(maxSeenAt across truncated builders)` if any, else null. Cap from `config('sync.per_type_cap', 5000)`. |
| `App\Http\Controllers\Api\V1\Mobile\ShowAppBootstrapController` | Invokable — takes `ShowAppBootstrapRequest`, `ShowAppBootstrapAction`. Returns `response()->json(['data' => $action->execute($request->resolvedLanguage())])->header('Cache-Control', 'public, max-age=300')` per AC 3. |
| `App\Http\Controllers\Api\V1\Sync\ShowUserSyncController` | Invokable — takes `ShowUserSyncRequest`, `ShowUserSyncAction`. Reads `auth()->id()` (never the request body) per AC 10. Returns JSON envelope `{ data: $action->execute(...) }`. Auth via Sanctum middleware on the route. |
| `App\Http\Requests\Mobile\ShowAppBootstrapRequest` | Validates `language` against `Language::cases()`. `resolvedLanguage()`: same pattern as `ListNewsRequest`. |
| `App\Http\Requests\Sync\ShowUserSyncRequest` | Validates `since` as nullable ISO-8601 (`date_format` rule with multiple accepted formats; falls back to `Carbon::parse` for ISO-8601). `since(): DateTimeImmutable` returns parsed value or epoch. |
| `App\Http\Resources\Mobile\AppBootstrapResource` | Wraps the array returned by the Action; no `@mixin` — the resource just structures the response shape and delegates to per-domain resources where applicable. |
| `App\Http\Resources\QrCode\QrCodeListItemResource` | Slim shape `{ reference, url, image_url? }` for the full-list view. Distinct from `QrCodeResource` (which is the single-item shape). |
| `App\Http\Resources\Sync\SyncTypeDeltaResource` | `{ upserted: <ResourceCollection>, deleted: <int[]> }`. Each builder pairs with the appropriate per-domain Resource via constructor injection (e.g. `FavoriteResource`, `NoteResource`, `SabbathSchoolAnswerResource`, …). |
| `App\Http\Requests\Concerns\PaginatesRead` | Trait — protected consts `DEFAULT_PER_PAGE = 30`, `MAX_PER_PAGE = 100` (overridable). `perPage(): int` reads `?per_page` query param, clamps to `[1, MAX_PER_PAGE]`, defaults to `DEFAULT_PER_PAGE`. `pageRules(): array` returns the `per_page` + `page` validation tuple to merge into `rules()`. |
| `App\Http\Middleware\EnsureInternalOps` | `handle($request, Closure $next): Response`. Reads `INTERNAL_OPS_CIDR` env (default `10.114.0.0/20`); if `$request->ip()` not within → 403 JSON `{message: 'Internal endpoint.'}`. CIDR matching via Symfony `IpUtils::checkIp` (stdlib in Laravel). |
| `App\Http\Controllers\Health\ShowLivenessController` | Invokable — returns `{status: 'alive', ts: now()->toIso8601String()}`. No Redis, no DB. Always 200. |
| `App\Http\Controllers\Health\ShowReadinessController` | Invokable — pings Redis (round-trip `set/get` on a 5 s TTL key) + DB (`SELECT 1`). Returns 200 + `{status: 'ready', services: {…}}` if all under 1 s; 503 + `{status: 'unready', dependency: 'redis' OR 'db', services: {…}}` otherwise. Replaces the existing `HealthCheckController`. |
| `App\Support\Observability\SlowQueryListener` | `register(): void` — boot-time hook; calls `DB::listen()` and logs to channel `slow_query` plus `Sentry\addBreadcrumb()` when `$query->time > 500`. Disabled when `app()->environment('local', 'testing')` to keep dev quiet. |
| `App\Support\Caching\CachedRead` (MOD) | `tagSentryScope($status)` is extended to also set `route_name` from `request()->route()?->getName()` so Sentry can group cache hit-rate per route. No public API change. |

## HTTP endpoints

| Method + Path | Controller | Action / Request | Resource | Auth |
|---|---|---|---|---|
| `GET /api/v1/app/bootstrap` | `ShowAppBootstrapController` | `ShowAppBootstrapAction` / `ShowAppBootstrapRequest` | `AppBootstrapResource` | api-key-or-sanctum (public; per AC 3) |
| `GET /api/v1/sync` | `ShowUserSyncController` | `ShowUserSyncAction` / `ShowUserSyncRequest` | `SyncTypeDeltaResource` (per type) | `auth:sanctum` |
| `GET /up` | `ShowLivenessController` | — | — | none (no rate limit) |
| `GET /ready` | `ShowReadinessController` | — | — | `internal-ops` |

Existing per-domain endpoints listed in story AC 5 remain untouched (backwards
compat for old app builds). The bootstrap endpoint is a new opt-in surface.

## Rate-limiter wiring

Defined in `AppServiceProvider::boot()`:

| Limiter name | Key | Limit | Applied to |
|---|---|---|---|
| `public-anon` | `$request->ip()` | 180/min | All `Route` groups currently using `api-key-or-sanctum` for **public reads** (bible, books, verses, daily-verse, collections, reading-plans index/show, olympiad index/show, hymnal-books/songs, devotionals show+archive, news, qr-codes, mobile/version, sabbath-school lessons index/show, resource-categories, app/bootstrap). |
| `per-user` | `auth()->id() ?: $request->ip()` | 300/min | All `Route` groups requiring `auth:sanctum` (auth/me, auth/logout, profile/*, favorites/*, favorite-categories/*, notes/*, hymnal-favorites/*, devotional-favorites/*, sabbath-school answers/highlights/favorites, reading-plan-subscriptions/*, sync). |

Excluded: `/up`, `/ready`, admin routes (admin already gated by
`auth:sanctum` + role middleware; rate limiting admins has no value at this
stage). 429 response includes `Retry-After` and `X-RateLimit-*` headers
(Laravel default behaviour for the throttle middleware).

`bootstrap/app.php` adds `$middleware->trustProxies(at: env('TRUSTED_PROXIES',
'*'), headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO)`
so the limiter sees the real client IP behind the LB.

## Migration

One migration, single transaction (Laravel handles per-DDL implicit commits;
the migration does not need explicit `DB::transaction`):

| Table | Add | Add indexes |
|---|---|---|
| `favorites` | `deleted_at TIMESTAMP NULL` | `(user_id, deleted_at)`, `(user_id, updated_at)` |
| `favorite_categories` | `deleted_at` | `(user_id, deleted_at)`, `(user_id, updated_at)` |
| `notes` | `deleted_at` | `(user_id, deleted_at)`, `(user_id, updated_at)` |
| `devotional_favorites` | `deleted_at`, `updated_at` (table is created-only today) | `(user_id, deleted_at)`, `(user_id, updated_at)` |
| `hymnal_favorites` | `deleted_at`, `updated_at` (table has nullable created_at only) | `(user_id, deleted_at)`, `(user_id, updated_at)` |
| `sabbath_school_answers` | `deleted_at` | `(user_id, deleted_at)`, `(user_id, updated_at)` |
| `sabbath_school_highlights` | `deleted_at` | `(user_id, deleted_at)`, `(user_id, updated_at)` |
| `sabbath_school_favorites` | `deleted_at` | `(user_id, deleted_at)`, `(user_id, updated_at)` |

Down: drop the indexes, drop the columns. Existing UNIQUE constraints stay
in place — restore-or-create in toggle Actions handles re-toggle without
schema change.

## Sync response shape

Action returns:

```
{
  synced_at: <ISO-8601 UTC, server clock at request start>,
  next_since: <ISO-8601 UTC or null>,
  favorites:                   { upserted: [<FavoriteResource>], deleted: [<int>] },
  notes:                       { upserted: [<NoteResource>], deleted: [<int>] },
  sabbath_school_answers:      { upserted: [<SabbathSchoolAnswerResource>], deleted: [<int>] },
  sabbath_school_highlights:   { upserted: [<SabbathSchoolHighlightResource>], deleted: [<int>] },
  sabbath_school_favorites:    { upserted: [<SabbathSchoolFavoriteResource>], deleted: [<int>] },
  devotional_favorites:        { upserted: [<DevotionalFavoriteResource>], deleted: [<int>] },
  hymnal_favorites:            { upserted: [<HymnalFavoriteResource>], deleted: [<int>] },
}
```

Controller wraps under `{ data: … }` per project convention.

## Bootstrap response shape

Built by `ShowAppBootstrapAction`, wrapped under `{ data: … }`:

```
{
  version:                       { ios, android, olympiad?, hymnal? }   from config/mobile.php (current ShowMobileVersionController already serves this)
  languages_available:           Language::cases() values
  daily_verse:                   GetDailyVerseAction(today)        — caught NoDailyVerseForDateException → null
  news:                          ListNewsAction(lang, page=1, perPage=7)
  bible_versions:                ListBibleVersionsAction(lang, page=1, perPage=100)['data']
  devotionals_today:             { adults: FetchDevotionalAction(adults, today, lang), youth: FetchDevotionalAction(youth, today, lang) }   each null on miss
  sabbath_school_current_lesson: SabbathSchoolLesson::query()->published()->forLanguage($lang)->where('week_start','<=',today)->where('week_end','>=',today)->latest('published_at')->first()  — fallback to most-recent published
  qr_codes:                      ListQrCodesAction()['data']
}
```

Each constituent Action already caches its own slice; bootstrap caches the
**stitched array** so a hit serves in one Redis read. The bootstrap `tags`
union ensures any constituent invalidation (news publish, daily-verse upsert,
SS lesson update) propagates to bootstrap.

## Configuration changes

- `config/mobile.php` — append `'bootstrap' => ['cache_ttl' => (int) env('BOOTSTRAP_CACHE_TTL', 300)]`.
- `config/logging.php` — add `slow_query` channel (`daily`, path
  `storage_path('logs/slow_query.log')`, days 14, level `info`).
- `bootstrap/app.php` — alias `internal-ops`, `throttle:public-anon`,
  `throttle:per-user` (the throttle aliases come from Laravel by default;
  explicit registration not needed). Add `trustProxies(at: env('TRUSTED_PROXIES','*'), headers: …)`.
  Replace existing `Route::get('up', HealthCheckController::class)` with two
  routes: `Route::get('up', ShowLivenessController::class)` and
  `Route::get('ready', ShowReadinessController::class)->middleware('internal-ops')`.
- `routes/api.php` — wrap public read groups with `throttle:public-anon`,
  authenticated groups with `throttle:per-user`; add the new
  `/app/bootstrap` and `/sync` routes.
- `config/sync.php` (NEW) — `'per_type_cap' => (int) env('SYNC_PER_TYPE_CAP', 5000)`.
- `.env.example` — append `BOOTSTRAP_CACHE_TTL`, `SYNC_PER_TYPE_CAP`,
  `INTERNAL_OPS_CIDR`, `TRUSTED_PROXIES`.

## Tasks

- [x] 1. Create migration `add_soft_deletes_to_user_tables` adding `deleted_at TIMESTAMP NULL` plus `(user_id, deleted_at)` and `(user_id, updated_at)` indexes on the eight tables listed in **Migration**. For `devotional_favorites` and `hymnal_favorites` also add `updated_at TIMESTAMP NULL` (currently absent). Down reverses every change.
- [x] 2. Add `Illuminate\Database\Eloquent\SoftDeletes` to all eight models listed in **Domain layout**. Update each model's `@property` PHPDoc with `?Carbon $deleted_at`. No factory changes needed.
- [x] 3. Modify `ToggleHymnalFavoriteAction`, `ToggleDevotionalFavoriteAction`, `ToggleSabbathSchoolFavoriteAction` to use restore-or-create on toggle-on (`Model::withTrashed()->firstOrNew([...])`; if trashed, `restore()` + `touch()`; if new, `save()`) and soft `delete()` on toggle-off. Existing unit tests for each Action stay green; add a test that re-toggling restores the same primary key id.
- [x] 4. Create `App\Domain\Mobile\Support\MobileCacheKeys` with `bootstrap(Language): string` and `tagsForBootstrap(): array` returning `['app:bootstrap', 'news', 'daily-verse', 'dev', 'ss', 'ss:lessons', 'bible', 'bible:versions', 'qr']`. Unit test the returned strings/arrays for sample inputs.
- [x] 5. Create `App\Domain\QrCode\Actions\ListQrCodesAction::execute(): array` — cached at TTL 86400, tag `qr`, returns the full QR set wrapped via a new `App\Http\Resources\QrCode\QrCodeListItemResource`. Unit test cache hit/miss.
- [x] 6. Create `App\Domain\Mobile\Actions\ShowAppBootstrapAction::execute(Language): array`. Constructor injects `CachedRead`, `ListNewsAction`, `GetDailyVerseAction`, `FetchDevotionalAction`, `ListSabbathSchoolLessonsAction`, `ListBibleVersionsAction`, `ListQrCodesAction`. Build closure stitches the payload per **Bootstrap response shape**. Catch `NoDailyVerseForDateException` and return null for that key. Set Sentry tag `cold_start: true` (best-effort, same `function_exists` guard as `CachedRead`). Unit test: composes from mocked Actions; cache miss/hit; tag flush propagates from any constituent (e.g. flushing `news` busts bootstrap).
- [x] 7. Create `App\Http\Requests\Mobile\ShowAppBootstrapRequest` (validates `?language=` against `Language::cases()`, `resolvedLanguage(): Language`); `App\Http\Resources\Mobile\AppBootstrapResource` (structures the payload); `App\Http\Controllers\Api\V1\Mobile\ShowAppBootstrapController` (invokable, returns `response()->json(['data' => $action->execute(...)])->header('Cache-Control', 'public, max-age=' . config('mobile.bootstrap.cache_ttl'))`). Register route under `/api/v1/app/bootstrap` in `routes/api.php` with `api-key-or-sanctum` middleware (no `auth:sanctum`). Feature test: response shape (every top-level key present); zero DB queries on second hit; `news` tag flush busts the bootstrap; cold_start Sentry tag set (assert via Sentry test transport).
- [x] 8. Create `App\Domain\Sync\DataTransferObjects\SyncTypeDelta` (readonly `{ array $upserted, array<int> $deleted, ?DateTimeImmutable $maxSeenAt }`). Create `App\Domain\Sync\Sync\SyncBuilder` interface with `fetch(int $userId, DateTimeImmutable $since, int $cap): SyncTypeDelta` and `key(): string`. Unit test the DTO is immutable.
- [x] 9. Implement seven per-domain `SyncBuilder` classes under `App\Domain\Sync\Sync\Builders\`. Each queries `Model::withTrashed()->where('user_id', $userId)->where(fn($q) => $q->where('updated_at', '>', $since)->orWhere('deleted_at', '>', $since))->orderBy('updated_at')->limit($cap + 1)->get()`, splits trashed→`deleted`, present→`upserted` (serialised via the per-domain Resource), sets `maxSeenAt` if more than `$cap` rows fetched. Each builder gets a unit test asserting: full sync includes everything; delta sync excludes pre-`since`; trashed rows surface in `deleted`; cap+1 trips `maxSeenAt`; cross-user rows excluded.
- [x] 10. Create `App\Domain\Sync\Actions\ShowUserSyncAction::execute(int $userId, DateTimeImmutable $since): array`. Constructor takes `iterable<SyncBuilder>` (tag `sync.builder` in `AppServiceProvider::register()`). Aggregates per builder, computes `next_since = min(maxSeenAt across truncated)` or null, returns `{ synced_at, next_since, <key> => {upserted, deleted}, ... }`. Cap from `config('sync.per_type_cap')`. Unit test the aggregation logic with stub builders.
- [x] 11. Create `config/sync.php` (`'per_type_cap' => (int) env('SYNC_PER_TYPE_CAP', 5000)`). Tag-bind every `SyncBuilder` impl in `AppServiceProvider::register()` so the iterable injection works.
- [x] 12. Create `App\Http\Requests\Sync\ShowUserSyncRequest` (validates nullable `since` ISO-8601, `since(): DateTimeImmutable` returns parsed value or epoch); `App\Http\Resources\Sync\SyncTypeDeltaResource`; `App\Http\Controllers\Api\V1\Sync\ShowUserSyncController` (invokable; `auth()->id()`; never reads user from request). Register `GET /api/v1/sync` under `auth:sanctum` middleware in `routes/api.php`. Feature test: full sync returns all data; delta with `since` returns only newer; soft-deleted rows surface in `deleted`; cross-user 0-row isolation; cap+1 emits `next_since`; missing `since` defaults to epoch.
- [x] 13. Create `App\Http\Requests\Concerns\PaginatesRead` trait with `DEFAULT_PER_PAGE=30`, `MAX_PER_PAGE=100`, `perPage(): int`, `pageRules(): array`. Apply to `ListNewsRequest`, `ListResourceCategoriesRequest`, `ListBibleVersionsRequest`, `ListSabbathSchoolLessonsRequest`, `ListOlympiadThemesRequest`, `ListCollectionTopicsRequest` (create the Form Request file if any of these controllers don't have one yet — refactor controllers to inject it). Override consts on a Form Request only when its existing limits diverge with reason; otherwise use trait defaults. Unit-test the trait once (clamping, default fallback) and add a feature test per controller asserting `per_page=200` → 422 and `per_page=50` → 50 results + `meta.per_page=50`.
- [x] 14. Create `App\Http\Middleware\EnsureInternalOps` (uses `Symfony\Component\HttpFoundation\IpUtils::checkIp($request->ip(), explode(',', env('INTERNAL_OPS_CIDR', '10.114.0.0/20')))`; 403 JSON `{message: 'Internal endpoint.'}` otherwise). Alias `internal-ops` in `bootstrap/app.php`. Unit test: VPC IP passes; public IP rejected with 403; multiple comma-separated CIDRs supported.
- [x] 15. Create `App\Http\Controllers\Health\ShowLivenessController` (returns `{status:'alive', ts:now()->toIso8601String()}`, always 200, no upstream pings). Create `App\Http\Controllers\Health\ShowReadinessController` (pings Redis via `Redis::connection('cache')->set('ready:probe', '1', 'EX', 5)` + `get`; pings DB via `DB::select('SELECT 1')`; total budget 1 s; 200 + `{status:'ready', services:{redis,db}}` or 503 + `{status:'unready', dependency:'<first-failing>', services:{...}}`). Delete the old `App\Http\Controllers\HealthCheckController`. In `bootstrap/app.php` replace the `Route::get('up', HealthCheckController::class)` with the two new routes (`/up` no middleware; `/ready` with `internal-ops`). Feature tests: `/up` returns 200 even when Redis is unreachable; `/ready` returns 503 with `dependency:'redis'` when Redis is unreachable; `/ready` returns 403 from a non-VPC IP.
- [x] 16. Define rate limiters in `AppServiceProvider::boot()`: `RateLimiter::for('public-anon', fn(Request $r) => Limit::perMinute(180)->by($r->ip()))` and `RateLimiter::for('per-user', fn(Request $r) => Limit::perMinute(300)->by((string) ($r->user()?->getAuthIdentifier() ?? $r->ip())))`. In `bootstrap/app.php` add `$middleware->trustProxies(at: env('TRUSTED_PROXIES', '*'), headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO)`. Wrap public read groups in `routes/api.php` with `throttle:public-anon` and authenticated groups with `throttle:per-user` — surgical changes to existing `Route::middleware([...])` arrays in the relevant groups (do not touch admin or `/up`/`/ready`). Feature tests: 200 hits to `/api/v1/news` from one IP returns 200 for first 180 then 429 with `Retry-After`; 350 hits to `/api/v1/notes` from one user returns 200 for first 300 then 429; `/up` responds 200 after 1000 hits; `429` carries `X-RateLimit-Remaining` headers.
- [x] 17. Add `slow_query` channel to `config/logging.php` (`daily`, path `storage_path('logs/slow_query.log')`, `days => 14`, `level => 'info'`). Create `App\Support\Observability\SlowQueryListener::register(): void` that calls `DB::listen()` and, for each `$query->time > 500`, writes `Log::channel('slow_query')->warning('slow_query', ['sql' => $query->sql, 'time_ms' => $query->time, 'bindings' => $query->bindings])` and `\Sentry\addBreadcrumb()` (best-effort). Skip in `local` and `testing` envs. Call from `AppServiceProvider::boot()`. Unit test: 600 ms simulated query writes to the channel and adds a breadcrumb; 100 ms query writes nothing.
- [x] 18. Modify `App\Support\Caching\CachedRead::tagSentryScope()` to additionally set `route_name` from `request()->route()?->getName()` when present. Update the existing CachedRead unit tests to assert the new tag is applied.
- [x] 19. Update `.env.example` with the new envs (`BOOTSTRAP_CACHE_TTL`, `SYNC_PER_TYPE_CAP`, `INTERNAL_OPS_CIDR`, `TRUSTED_PROXIES`). Update `docs/runbook/cache.md` with: bootstrap key+tag map, `mybible:cache-clear-tag app:bootstrap` example, the `/up` vs `/ready` split (note that the deploy/LB readiness probe must move from `/up` to `/ready`), rate-limit headers, slow-query log location.
- [x] 20. Run `make lint-fix`, `make stan`, then `make test-api filter='Mobile|Sync|Health|RateLimit|Pagination|Bootstrap|Slow'` to exercise the changed surfaces; finally `make check` from the monorepo root before marking the story ready for review.

## Risks & notes

1. **Optional 4-PR split.** Each surface is independently deployable; the bootstrap+sync surfaces sit behind the mobile-cutover (story dependency MBA-020), so they don't add user-visible behaviour until clients update — making them low-risk to merge separately. **(a)** Migration + SoftDeletes wiring + restore-or-create on toggles + sync builders+action+endpoint (tasks 1–3, 8–12); **(b)** Bootstrap composition (tasks 4–7); **(c)** Rate limits + TrustProxies + pagination (tasks 13, 16); **(d)** Health split + observability (tasks 14, 15, 17–19). (a) is the only one that ships behavior-bearing code; the others are additive.
2. **Restore-or-create vs unique-index rebuild.** Chosen restore-or-create per Open question 1. The trade-off: a soft-deleted row surfaces in `sync.deleted` only on the first sync after the toggle-off, then on toggle-on it disappears from `deleted` and reappears in `upserted` with the **same id** but a newer `updated_at`. Clients dedupe by id, so the upsert overwrites the local record cleanly. Document this in the runbook so a mobile dev investigating "why does the same favorite id come back after I deleted it" finds the answer.
3. **Unique constraint surprise on hard delete by some other path.** If admin tooling ever introduces a `forceDelete` that bypasses SoftDeletes, the row vanishes from `sync.deleted` once trashed-then-purged. With per-day clients polling, a same-day forceDelete is fine (it surfaces in `deleted` first); a stale 30-day client would never see the deletion. Acceptable until we add a tombstones table in a future story (deferred — track in `MBA-032-cleanup-post-cutover`).
4. **TrustProxies default `'*'`.** Trusts every upstream — fine in Docker dev, **must** be tightened in production via `TRUSTED_PROXIES` env to the LB CIDR. The runbook explicitly calls this out; ops PR for the production env file is a separate change.
5. **`/up` behaviour change for the LB.** Today the LB's `/up` poll happens to ping DB+Redis (because `HealthCheckController` does both). After the split, `/up` only verifies PHP-FPM. The deploy pipeline / LB readiness probe must move to `/ready`. This is an ops change paired with this PR — the runbook lists it but does not configure the LB. If the LB stays on `/up` only, the cluster becomes more tolerant of transient deps blips (acceptable, that's the whole point of the split).
6. **Bootstrap composition costs on cache miss.** A cold miss runs every constituent Action's build closure (each is itself cached, so most run as Redis hits). Worst case (all sub-caches cold simultaneously, e.g. after a `mybible:cache-clear-tag app:bootstrap` followed by full deploy flush): ~9 query bursts in one HTTP request. Acceptable because (a) `Cache::flexible` lock prevents stampede on the bootstrap key itself, and (b) the lock applies to each constituent independently. If it becomes a thundering-herd hot-spot at deploy time, the follow-up is `cache:warm` (already deferred from MBA-021).
7. **Sync row cap at 5 000.** A user with >5 000 changes per type since `since` will paginate via `next_since`. Realistically only happens on a true full sync of a power user, where 5 000 favorites is plausible. Cursor convergence is monotonic: each call advances `next_since` past at least one row, so finite calls reach end-of-stream.
8. **Rate-limit per-user keying when auth missing.** `per-user` limiter falls back to IP when no user is authenticated (one route group has both kinds — e.g. `news` is public-only, but if ever moved to `per-user` we want IP fallback). This avoids `null` keys and keeps the limiter functional for unauthenticated probes against an authenticated route (which would 401 first anyway).
9. **`internal-ops` middleware blocks local dev unless env-overridden.** `INTERNAL_OPS_CIDR` defaults to `10.114.0.0/20`; local Docker IPs (172.x) won't match. Local dev `.env` should set `INTERNAL_OPS_CIDR=127.0.0.1/32,172.16.0.0/12` so devs can hit `/ready` from the host. Documented in `.env.example`.
10. **Slow-query listener overhead.** `DB::listen` fires per query; the listener body is a single comparison + (rarely) one log write. Overhead is sub-millisecond per query. Disabled in `local`/`testing` to keep the test suite quiet.
11. **Deferred Extractions register.** This story extracts `PaginatesRead` (×6 copies of `perPage()`-style code now collapse into one trait). Update the register: the **owner-`authorize()` block** entry stays at 4/5 (no new owner-gated endpoints added); the `PaginatesRead` extraction is now done so it does not need a tripwire entry going forward. No new patterns to track from this story.
12. **Testing soft-delete + sync end-to-end requires a `Carbon::setTestNow()` discipline.** Each sync test sets a fixed `now()` so `updated_at`/`deleted_at` comparisons are deterministic. Helper trait `Tests\Concerns\SyncsAt` (one method, `freezeAt(string $iso): void`) — only extract if more than three test files repeat it; otherwise keep inline.
