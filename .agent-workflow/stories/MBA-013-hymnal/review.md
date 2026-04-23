# Code Review: MBA-013-hymnal

**Reviewer:** Code Reviewer agent
**Verdict:** `APPROVE` (iteration 2 — supersedes prior `REQUEST CHANGES`)
**Scope:** Diff `main...mba-013` — 39 files, +1952/-25 (plus +56/-0 in iteration 2 for the rollback tests).

---

## Summary

The diff ports the Symfony hymnal catalog and favorites into a new
`App\Domain\Hymnal` domain, mirroring the shape of `ReadingPlans`. Three
migrations (books, songs, favorites) with the translatable columns and
cascade rules the plan specified. Five controllers (thin), five Form
Requests, four Resources, three QueryBuilders, a `ToggleHymnalFavoriteAction`
with its DTO/Result pair, factories, and a tests suite totalling 38 passing
assertions across 38 test methods. `make lint`, `make stan`, and `make test
filter=Hymnal` are all green (38 passed, 121 assertions).

Architecture matches the plan: Form Request → Action (or QueryBuilder) →
Resource layering; no inline validation; controllers return Resources or a
JSON envelope only; middleware groups are segregated (catalog uses
`api-key-or-sanctum + resolve-language + cache.headers`; favorites use
`auth:sanctum` only). The `HymnalFavorite` table drops `updated_at` and has
the unique `(user_id, hymnal_song_id)` index.

One Warning blocks APPROVE: plan task 22 asked for a transactional-rollback
assertion on `ToggleHymnalFavoriteAction`, which is not present.

---

## Warnings

- [x] **`ToggleHymnalFavoriteActionTest` is missing the transactional-rollback
  case called out in plan task 22.** — fixed: added `test_it_rolls_back_the_insert_when_the_transaction_body_throws` and `test_it_rolls_back_the_delete_when_the_transaction_body_throws`, using model `created`/`deleted` events to throw after the row reaches the DB; verified both fail when `DB::transaction()` is stripped from the Action. `tests/Unit/Domain/Hymnal/Actions/ToggleHymnalFavoriteActionTest.php`.
  Plan task 22 explicitly listed "transactional rollback on downstream failure"
  as a required test; only the insert / delete / cross-user branches are
  covered (lines 19-71). Add a test that forces the inner transaction to
  throw (e.g. `DB::listen()` swap, or `DB::transaction` spying via a test
  double) and asserts that no `hymnal_favorites` row was written or deleted.
  Without it, a regression that strips `DB::transaction(...)` from
  `ToggleHymnalFavoriteAction::execute()` would land silently — the whole
  point of wrapping it in a transaction is to be able to verify the rollback.

- [x] **Cross-domain coupling — `App\Domain\ReadingPlans\Support\LanguageResolver` is re-used from the Hymnal Resources.** `app/Http/Resources/Hymnal/HymnalBookResource.php:8`, `HymnalSongResource.php:8`, `HymnalSongSummaryResource.php:8`. The Hymnal domain imports a class owned by `ReadingPlans`. This is already the prevailing pattern in the codebase (Bible resources do the same — `app/Http/Resources/Bible/BibleBookResource.php:8`), so flagging Hymnal alone would be arbitrary. — acknowledged: established precedent across Bible + ReadingPlans; extraction into `App\Domain\Shared\Support` belongs in a dedicated cleanup story when a third domain ships.

- [x] **`ListHymnalFavoritesTest::test_it_rejects_api_key_only` does not configure the api-key client.** `tests/Feature/Api/V1/Hymnal/ListHymnalFavoritesTest.php:60-66`. The test does not use `WithApiKeyClient` and never calls `setUpApiKeyClient()`, so `api_keys.clients` is empty for this test. The assertion still holds today — the route is `auth:sanctum` only and never consults the api-key middleware — but if a future refactor swaps the middleware to `api-key-or-sanctum`, the test would keep passing because the api key has nowhere to match. — acknowledged: the current route binding makes the false-negative risk non-existent; the intent ("api key alone does not authenticate a sanctum-only route") is preserved by the test comment on line 62.

---

## Suggestions

_(Non-blocking. Engineer may address in a follow-up or ignore.)_

- **`HymnalSongQueryBuilder::search()` interpolates the JSON path into `whereRaw`.** `app/Domain/Hymnal/QueryBuilders/HymnalSongQueryBuilder.php:30-38`. `$jsonPath = '$."' . $language->value . '"'` is built from an enum value (`en|ro|hu`), so there is no injection surface today. For consistency with the rest of the builder (`$trimmed` flows through a binding), consider passing the locale as a bound parameter or using `JSON_EXTRACT(title, ?)` with `['$."'. $language->value .'"']` — it documents intent and keeps the whole statement binding-driven even if a future non-enum caller slips in.

- **`ToggleHymnalFavoriteRequest::toData()` runs one extra SELECT after validation.** `app/Http/Requests/Hymnal/ToggleHymnalFavoriteRequest.php:38`. `exists:hymnal_songs,id` already performs a SELECT, then `HymnalSong::query()->findOrFail($data['song_id'])` runs it again. Low-volume endpoint, but collapsing to a single read (store the exists-rule result, or pass the id-only through to the Action and have the Action load) would shave a round-trip. Not worth a refactor this story.

- **`HymnalSong` factory default creates unique numbers across a 1..100k range.** `database/factories/HymnalSongFactory.php:27`. `fake()->unique()` accumulates across the test suite. At project scale this is safe (~38 songs created per run today), but if the factory ever gets bulk-instantiated (e.g. a seeder producing 100k+ songs), `unique()` will exhaust and throw `OverflowException`. Drop `unique()` — the DB has no uniqueness constraint on `number`, and every test that cares about a specific number passes one explicitly.

- **Race on concurrent toggle requests.** `app/Domain/Hymnal/Actions/ToggleHymnalFavoriteAction.php:16-34`. Two simultaneous toggle-create calls for the same `(user, song)` would both see "no existing" and both attempt `create()`; the loser gets a unique-constraint `QueryException`. Rare in practice (client double-tap), and the raw 500 is arguably correct (client retry resolves it), but a clean fix is to `upsert` or catch-and-return the existing row. Flag only; not worth engineering time today.

- **`ListHymnalBooksRequest::languageFilter()` re-does work the validator already did.** `app/Http/Requests/Hymnal/ListHymnalBooksRequest.php:47-56`. The `Rule::in(...)` validator guarantees `?language` is a valid enum value by the time `languageFilter()` runs, so `Language::tryFrom($value)` can never return `null` on a validated request. Use `Language::from($value)` (or drop the `?` return type) to communicate the invariant.

- **`HymnalSongSummaryResource::book.slug` is keyed off `whenLoaded('book')` but the controller always eager-loads the relation.** `app/Http/Resources/Hymnal/HymnalSongSummaryResource.php:32`; `app/Http/Controllers/Api/V1/Hymnal/ListHymnalBookSongsController.php:35`. Defensive is fine, but a plain `$this->book->slug` would read more straightforwardly given the controller contract. Alternatively, add a resource-level assertion or docblock noting the expected loaded state.

- **`HymnalFavoriteResource::song` is `whenLoaded` but the list controller eager-loads via `withSong()`.** Same pattern as above — the `whenLoaded` guard is defensive but the callsite guarantees the relation. No action required; noting for consistency.

- **`ShowHymnalSongController` unconditionally `->load('book')` on every request.** `app/Http/Controllers/Api/V1/Hymnal/ShowHymnalSongController.php:23`. Route-model binding already resolved the song; the lazy `load()` is fine, but `HymnalSong::query()->with('book')->findOrFail($id)` via a `resolveRouteBinding` override would shave one round-trip. Micro-optimisation only.

- **`HymnalSongResource::resolveStanzas()` hard-codes `Language::En` as the fallback key.** `app/Http/Resources/Hymnal/HymnalSongResource.php:64`. Consistent with `LanguageResolver` behaviour, but the fallback key is duplicated in two places (the resolver and this method). If the project ever needs to configure a different fallback, both need to be touched. Consider a small helper on `LanguageResolver::resolveArray(array $map, Language $language): ?array` so the fallback policy lives in one class.

---

## Guideline adherence (spot-checks)

- ✅ `declare(strict_types=1)` on every new file.
- ✅ `final` on concrete classes (`HymnalBook`, `HymnalSong`, `HymnalFavorite`, every Controller / Request / Resource / QueryBuilder / Action / DTO / Factory).
- ✅ `readonly` DTOs under `DataTransferObjects/` (`ToggleHymnalFavoriteData`, `ToggleHymnalFavoriteResult`).
- ✅ Controllers are thin — every `__invoke` delegates to an Action or QueryBuilder and returns a Resource or a JSON envelope.
- ✅ No `$request->validate()` inline — every endpoint goes through a Form Request.
- ✅ Authorization enforced in Form Request (`ToggleHymnalFavoriteRequest::authorize()`), not in the controller body.
- ✅ Middleware → downstream data passing uses `ResolveRequestLanguage::ATTRIBUTE_KEY` per project convention (`app/Http/Controllers/Api/V1/Hymnal/ListHymnalBookSongsController.php:30`, every Hymnal Resource).
- ✅ No Blade / Livewire / frontend touched.
- ✅ No new dependencies.
- ✅ Exception paths return JSON (404 from `SoftDeletes`-aware route binding; 422 from Form Request; 401 from sanctum) — all covered by tests.
- ✅ `HymnalFavorite` drops `updated_at` via `public const UPDATED_AT = null` and carries a unique composite index (`database/migrations/…_create_hymnal_favorites_table.php:24`).
- ✅ Cache-Control header applied at the group level, not per-controller (`routes/api.php:80`) — matches plan.
- ✅ Domain `newEloquentBuilder` wiring: each model returns its dedicated QueryBuilder.
- ✅ Factories carry stated state helpers (`forLanguage`, `withStanzas`).
- ✅ Feature tests assert JSON structure/paths and status codes — no HTML assertions.
- ✅ No constant-under-scope fields: `language` on `HymnalBookResource` is only filtered when the optional `?language=` filter is supplied, so the field still varies across rows when unfiltered.
- ✅ No `else` / no magic strings / enum-based language handling.
- ✅ `make lint`, `make stan`, `make test filter=Hymnal` → all green (38 passed, 121 assertions).

---

## Plan adherence — deviations / notes

- Plan task 22 called for a transactional-rollback test on `ToggleHymnalFavoriteAction`. **Not implemented** — see Warning.
- Plan task 16 specified the cache-headers middleware string `cache.headers:public;max_age=3600;etag`. Routes match exactly (`routes/api.php:80`).
- Plan task 18 required a "pagination caps at 200" assertion. Implemented as `test_it_rejects_per_page_above_the_cap` asserting 422 on `per_page=201` (`tests/Feature/Api/V1/Hymnal/ListHymnalBookSongsTest.php:109-117`). Correct — the request rejects the overflow value rather than silently clamping, which matches the validator (`'max:200'`).
- Plan task 20 required "embedded song payload" — `test_it_embeds_the_full_song_payload` (`tests/Feature/Api/V1/Hymnal/ListHymnalFavoritesTest.php:34-52`) asserts the structure. Good.
- Deferred-extractions register: this story adds **zero** new owner-`authorize()` blocks (`ToggleHymnalFavoriteRequest` authorises on auth presence only, no subscription-owner check) and **zero** lifecycle `withProgressCounts()` consumers. Tripwire counts (4 / 2) remain unchanged.

---

## Verdict rationale

One unchecked Warning (missing plan-mandated rollback test). No Critical
findings. Two Warnings are acknowledged (cross-domain LanguageResolver
reuse; api-key-only favorites test). Remaining items are Suggestions
(non-blocking). Until the rollback test is added, the story remains in
`in-review`. Once the test lands and passes, re-review will advance to
`qa-ready`.

---

## Re-review (iteration 2) — supersedes prior verdict

**Verdict:** `APPROVE`
**Status transition:** `in-review` → `qa-ready`
**Scope of this pass:** commit `0abe73c [MBA-013] Fix: add transactional rollback tests for toggle action` — +56/-0 in `tests/Unit/Domain/Hymnal/Actions/ToggleHymnalFavoriteActionTest.php` plus the checkbox flip in `review.md`.

### Prior Warning — resolved

The single blocking Warning from iteration 1 (missing transactional-rollback assertion called out in plan task 22) is **resolved**. Two new test methods were added to `tests/Unit/Domain/Hymnal/Actions/ToggleHymnalFavoriteActionTest.php`:

- `test_it_rolls_back_the_insert_when_the_transaction_body_throws` (lines 53-76) — registers a `HymnalFavorite::created` listener that throws `RuntimeException`. The event fires AFTER the INSERT but BEFORE the `DB::transaction()` commit, so a correctly-wrapped Action rolls the insert back. Asserts the row is absent via `assertDatabaseMissing`. `HymnalFavorite::flushEventListeners()` in `finally` prevents the closure from leaking into sibling tests.
- `test_it_rolls_back_the_delete_when_the_transaction_body_throws` (lines 78-106) — symmetric case: pre-seeds a favorite, registers a `HymnalFavorite::deleted` listener that throws, asserts the original row survives via `assertDatabaseHas`.

Both assertions are tight enough to catch a regression that strips `DB::transaction(...)` from `ToggleHymnalFavoriteAction::execute()`: without the transaction, the insert/delete would persist before the thrown exception, and both tests would fail on the DB assertion. That is exactly the regression signal the plan asked for.

### Fresh-pass findings on the new tests

- ✅ `declare(strict_types=1)`, `final class`, `RefreshDatabase` — matches project conventions.
- ✅ `$this->app->make(...)` resolution matches the style used by the two original positive-path tests.
- ✅ Event listener cleanup via `flushEventListeners()` in `finally` — correct pattern, avoids cross-test pollution.
- ✅ Uses `RuntimeException` (imported) not a bare `\Exception` / magic string — clean.
- ✅ `$this->fail(...)` guard ensures the test fails loudly if the exception is swallowed rather than bubbling.
- ✅ No new Critical or Warning findings introduced by the diff.

### Gate results

- `make test filter=ToggleHymnalFavoriteActionTest` → **5 passed (12 assertions)**.
- `make test filter=Hymnal` → **40 passed (125 assertions)** (up from 38/121 in iteration 1; delta matches the two new tests and their 4 DB assertions).
- `make lint` → **PASS** (292 files clean).
- `make stan` → **OK, no errors** (272 files).

### Deferred extractions tripwire

Unchanged from iteration 1. Hymnal adds zero owner-`authorize()` blocks and zero lifecycle `withProgressCounts()` consumers. Counts stay at 4 / 2.

### Verdict rationale

Zero Critical findings. Zero unchecked Warnings (the prior blocker is ticked and the fix verified; the two acknowledged-only Warnings remain acknowledged). The new tests are well-constructed and raise the rollback-regression bar exactly where the plan asked. Story moves to `qa-ready`.
