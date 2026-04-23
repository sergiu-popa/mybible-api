# Code Review: MBA-012-devotionals

**Reviewer:** Code Reviewer agent
**Verdict:** `APPROVE`
**Scope:** `git diff main...mba-012` — 41 files, +2018/-33 (migrations, domain, HTTP layer, tests). Plan + story updates included.

---

## Summary

The implementation follows the plan cleanly. `App\Domain\Devotional\*` owns the
enum, both models, both query builders, the two DTOs (plus a `ToggleResult`
DTO), and the two Actions. Four invokable controllers expose the four
endpoints in the plan's table; Form Requests validate input, build DTOs, and
delegate to Actions. Resources match the documented shape, with `whenNotNull`
omitting optional fields and `whenLoaded` embedding the devotional on the
favorite resource. Route ordering puts `/devotionals/archive` before the root
show route per plan note.

Migrations reconcile via `Schema::hasTable` (matches MBA-005 posture);
`devotional_favorites.user_id` correctly uses `unsignedInteger` to track
Symfony's `users.id` width with an explanatory comment. Composite `(language,
type, date)` index on `devotionals` covers both the single-row lookup and the
`ORDER BY date DESC` archive path.

Toggle uses `DB::transaction` + `lockForUpdate` to serialize concurrent
clicks, mirrors MBA-009's `201 created` / `200 { deleted: true }` envelope,
and eager-loads `devotional` on the insert branch so the resource does not
re-query. `Cache-Control: public, max-age=3600` header on the show endpoint
is documented in a docblock explaining why a Sanctum-shared response is safe
to cache publicly.

`make lint` (294 files), `make stan` (274 files) and `make test filter=Devotional`
(52 tests, 145 assertions) all pass locally. Tests cover every AC + plan
tripwire (language no-fallback, future-date exclusion, cross-user scoping,
unknown devotional id, per_page cap, `to < from`, auth-required paths,
Cache-Control header presence).

---

## Suggestions

_(Non-blocking; engineer may address in a follow-up or ignore.)_

- **`ShowDevotionalRequest::forDate()` has an unreachable fallback.**
  `app/Http/Requests/Devotionals/ShowDevotionalRequest.php:50-63`. After the
  `date_format:Y-m-d` rule passes, `CarbonImmutable::createFromFormat('!Y-m-d', $raw)`
  cannot fail; the `return CarbonImmutable::today();` at the bottom is only
  reachable when `date` was omitted. Collapse to an early `is_string($raw) && $raw !== ''`
  guard that returns `CarbonImmutable::createFromFormat('!Y-m-d', $raw)`
  directly, or drop the defensive `instanceof` check — it does not earn its
  complexity. Readability win only.

- **`ListDevotionalFavoritesRequest::MAX_PER_PAGE = 50` diverges from the
  archive's 30.** `app/Http/Requests/Devotionals/ListDevotionalFavoritesRequest.php:13`
  vs `app/Http/Requests/Devotionals/ListDevotionalArchiveRequest.php:19`.
  Story does not spec a cap for the favorites list, so 50 is defensible, but
  having two different caps on sibling paginated endpoints invites churn.
  Consider standardising on the archive's 30, or extracting a project-wide
  paginated-request trait / base class once a third paginated endpoint adopts
  the same shape (would also deduplicate the `perPage()` helper currently
  copy-pasted into both Form Requests).

- **`ToggleDevotionalFavoriteResult::created` — method name collides visually
  with the `bool $created` property.**
  `app/Domain/Devotional/DataTransferObjects/ToggleDevotionalFavoriteResult.php:12-19`.
  PHP resolves `::created()` as a static method independently of `$created`,
  so there is no bug, but reading `Result::created($fav)` and then
  `$result->created` two lines later takes a double-take. Rename the factory
  to `forCreation()` / `created(...): self` stays but add a docblock calling
  out the name overlap; or rename the property to `isCreated`. Pure cosmetic.

- **`DevotionalFavoriteQueryBuilder::matching()` leaks user/devotional-id
  coupling into the builder shape.**
  `app/Domain/Devotional/QueryBuilders/DevotionalFavoriteQueryBuilder.php:31-34`.
  The only caller is `ToggleDevotionalFavoriteAction`, and the method
  duplicates the two single-column scopes (`forUser`, `where devotional_id`).
  Composing them at the call site (`->forUser($user)->where('devotional_id', $id)`)
  would remove a method without losing clarity. Minor.

- **`ListDevotionalArchiveController` reaches into the query builder directly
  rather than through an Action.**
  `app/Http/Controllers/Api/V1/Devotionals/ListDevotionalArchiveController.php:27-33`.
  Plan task 19 explicitly allows "query-builder-driven pagination", so this
  is sanctioned and consistent with `ListDevotionalFavoritesController`. If
  Symfony parity grows (e.g. adding language fallback, caching, observability
  on the archive), extracting a `ListDevotionalArchiveAction` would keep the
  controller thin — flag for follow-up only if that growth materialises.

- **`DevotionalFactory::definition()` randomises `passage` / `author` via
  `fake()->boolean()`.** `database/factories/DevotionalFactory.php:31-32`.
  Harmless today because resource tests assert the omission path explicitly
  via `create(['passage' => null, 'author' => null])`, but a non-deterministic
  default factory can create flaky tests if future assertions ever rely on
  both fields being present. Consider defaulting both to non-null and using
  explicit states (`withoutPassage()`, `anonymous()`) for the null cases.

---

## Guideline adherence (spot-checks)

- `declare(strict_types=1);` on every new file.
- `final` on every concrete class (models, controllers, requests, resources, DTOs, actions, query builders, factories).
- `readonly` DTOs under `App\Domain\Devotional\DataTransferObjects`.
- Controllers stay thin — delegate to Actions or scoped query builders, return Resources / JSON.
- Form Request → Action → Resource layering; no `$request->validate()` inline.
- `api-key-or-sanctum` + `resolve-language` wrapping the public routes; `auth:sanctum` on the user-scoped favorites group (`routes/api.php:79-95`).
- Middleware-attached language is read via `$request->attributes->get(ResolveRequestLanguage::ATTRIBUTE_KEY, Language::En)` with a sane default — matches CLAUDE.md §2 middleware-to-request-data contract.
- No Blade, no Livewire, no frontend assets.
- No new composer dependencies.
- Migrations are idempotent (`Schema::hasTable` guard), matching MBA-005 shared-DB posture.
- Feature tests assert JSON structure + status codes; unit tests isolate Actions, query builders, Form Requests, and Resources.
- `assertStringContainsString('public', ...)` / `assertStringContainsString('max-age=3600', ...)` avoid the Symfony directive-ordering trap flagged in the MBA-008 review.

---

## Plan deviations — acknowledged

- Plan task 16 listed a `ListDevotionalFavoritesRequest` as "authenticated-only";
  the engineer added a `per_page` rule plus a separate `MAX_PER_PAGE = 50` (vs
  the archive's 30). Explicit decision, flagged as a Suggestion above.
- Plan said `DevotionalFavoritePolicy::view` would be owner-only — engineer
  achieved the same guarantee by query-scoping (`forUser($user)`), which the
  plan itself acknowledged was sufficient ("the query can't leak"). No
  policy class was added, matching the plan's own carve-out.
- `ToggleDevotionalFavoriteAction` upgrades the plan's `transaction-wrapped`
  requirement with `lockForUpdate()` on the existence check — race-safer
  than the plan required; net positive.

---

## Verdict rationale

No Critical findings. No Warnings. All six Suggestions are non-blocking cosmetic
/ style calls the engineer may take or leave. Lint, stan, and the 52-test
Devotional suite are green. Story advances to `qa-ready`.
