# Audit: MBA-012-devotionals

**Auditor:** Auditor agent
**Verdict:** `PASS`
**Scope:** Full review of `git diff main...mba-012` (41 files, +2218/-33) plus
every story artifact (story / plan / review / qa). No Critical or Warning
issues introduced; the six Suggestions raised in review are all accounted for
below.

## Dimensions scanned

- **Architecture compliance** — Domain layout matches plan; `App\Domain\Devotional\*` owns enum, models, query builders, DTOs, actions. Controllers stay thin and delegate to Actions or scoped query builders. No Blade/Livewire/frontend code.
- **API design** — `GET`/`POST` verbs correct, `201` on toggle create, `200 { deleted: true }` on delete (matches MBA-009 envelope lock), `404` via `ModelNotFoundException`, `422` on validation. Routes live under `/api/v1`. Archive registered before show (route-ordering tripwire in plan). `Cache-Control: public, max-age=3600` only on the cacheable show endpoint.
- **Code quality** — Strict types, `final`, `readonly` DTOs, explicit return types, no `else`, `whenNotNull` / `whenLoaded` on resources. No models returned directly from controllers.
- **Security** — `api-key-or-sanctum` on public devotional reads; `auth:sanctum` on favorites group. Favorites are query-scoped via `forUser($user)` so cross-user rows can't leak. Toggle uses `lockForUpdate()` inside a `DB::transaction` to serialise concurrent clicks.
- **Performance** — Composite `(language, type, date)` index on `devotionals` covers the show path and the archive `ORDER BY date DESC` prefix. Toggle eager-loads `devotional` on the insert branch so the resource does not re-query. Pagination caps at 30.
- **Test coverage** — 52 devotional tests (145 assertions) cover every AC row, the no-language-fallback contract, cross-user isolation, enum rejection, date-format rejection, per_page cap, Cache-Control header, embedded devotional shape. Full suite 419 passing / 1284 assertions / 0 regressions.

## Issues

| # | Issue | Location | Severity | Status | Resolution |
|---|---|---|---|---|---|
| 1 | `ShowDevotionalRequest::forDate()` has an unreachable `instanceof` fallback after `date_format:Y-m-d` has already validated the string. Readability. | `app/Http/Requests/Devotionals/ShowDevotionalRequest.php:50` | Suggestion | Fixed | Collapsed the `instanceof` guard to a PHPDoc-narrowed direct return — the outer `is_string && !=''` guard still distinguishes the "omitted" path from the "provided" path. |
| 2 | `ListDevotionalFavoritesRequest::MAX_PER_PAGE = 50` diverges from the archive's `30` cap on a sibling paginated endpoint. | `app/Http/Requests/Devotionals/ListDevotionalFavoritesRequest.php:13` | Suggestion | Fixed | Standardised on `30` to match the archive; sibling endpoints now share the same page-size cap. Unit test constant reference (`MAX_PER_PAGE + 1`) still works correctly. |
| 3 | `ToggleDevotionalFavoriteResult::created` method name visually collides with the `bool $created` property. | `app/Domain/Devotional/DataTransferObjects/ToggleDevotionalFavoriteResult.php:16` | Suggestion | Fixed | Added a docblock on the factory method calling out the name overlap (and that PHP resolves static-method and instance-property namespaces independently). |
| 4 | `DevotionalFavoriteQueryBuilder::matching()` duplicates `forUser()` + a trivial `where('devotional_id', …)`. Only one caller. | `app/Domain/Devotional/QueryBuilders/DevotionalFavoriteQueryBuilder.php:31` | Suggestion | Fixed | Removed `matching()`; `ToggleDevotionalFavoriteAction` now composes `forUser($user)->where('devotional_id', $id)` at the call site. |
| 5 | `ListDevotionalArchiveController` reaches into the query builder directly rather than going through an Action. Flagged as "sanctioned by plan" in review. | `app/Http/Controllers/Api/V1/Devotionals/ListDevotionalArchiveController.php:27` | Suggestion | Skipped — plan task 19 explicitly allows query-builder-driven pagination and this mirrors `ListDevotionalFavoritesController`. Extraction tripwire not tripped. | — |
| 6 | `DevotionalFactory::definition()` randomises `passage` / `author` via `fake()->boolean()`. Harmless today but invites future flakiness. | `database/factories/DevotionalFactory.php:31-32` | Suggestion | Fixed | Defaulted both fields to non-null values and added explicit `withoutPassage()` / `anonymous()` states for the null cases. Existing tests that assert the omission path already pass `passage: null` / `author: null` explicitly via `create()`. |
| 7 | Deferred Extractions register — owner-`authorize()` trait counter. | `.agent-workflow/CLAUDE.md` §7 | Note | Logged — no-op this story | The toggle Form Request authorizes via `$this->user() !== null` (not an owner check), and ownership is enforced in the action via `forUser()`. Counter stays at 4. No extraction due yet. |

## Test results

- `make lint-fix` — 294 files, all clean (no diff).
- `make stan` — 274 files, no errors.
- `make test filter=Devotional` — **52 passed, 145 assertions** (0.84s).
- `make test` (full suite) — **419 passed, 1284 assertions, 0 failures** (7.33s).

## Verdict

All Suggestions resolved or consciously skipped with rationale. No Critical /
Warning items outstanding. Full suite green. Story advances to `done`.
