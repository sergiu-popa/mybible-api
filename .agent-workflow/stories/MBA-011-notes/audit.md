# Audit: MBA-011-notes

**Auditor:** Auditor agent
**Verdict:** `PASS`
**Branch:** `mba-011`
**Suite:** `make check` (lint + stan + test) → lint PASS (287 files), stan PASS (267 files, 0 errors), tests `408 passed / 1257 assertions / 7.18s`.
**Focused:** `make test filter=Notes` → 31 passed (95 assertions) post-fix.

## Scope

Full holistic pass over the MBA-011 story (notes CRUD domain). Read: `story.md`, `plan.md`, `review.md` (iterations 1 + 2), `qa.md`, and the 36-file diff `main...mba-011`. Cross-checked against:
- `.agent-workflow/CLAUDE.md` (project overrides, API layer conventions, Deferred Extractions register).
- `CLAUDE.md` (root; Laravel Boost guidelines, Docker commands).
- Existing MBA-004 `AuthorizedReadingPlanSubscriptionRequest` pattern (reference implementation parity).

## Dimensions surveyed

1. **Architecture compliance** — Domain/Http/Policies layout per project structure; single-action invokable controllers under `App\Http\Controllers\Api\V1\Notes`; Form Requests + API Resources; routes under `/api/v1`; Sanctum gate; Policy via `Gate::policy`. All OK.
2. **Code quality** — `declare(strict_types=1)`, `final` on concretes, `readonly` DTOs, constructor-promoted dependencies, typed returns. All OK.
3. **API design** — 201 on create, 200 on update, 204 on delete, 401 unauth, 403 cross-user, 404 missing, 422 validation. Canonical reference round-trips. JSON envelopes match project convention. All OK.
4. **Security** — Ownership enforced via `AuthorizedNoteRequest` → `NotePolicy::manage`; `reference` immutable on PATCH (not in rules → absent from `validated()`); `StripHtml` defence-in-depth; multi-reference truncation closed in iteration 2 of review. All OK.
5. **Performance** — Compound indexes `(user_id, created_at)` and `(user_id, book)` match the only two access patterns (pagination, book filter); `reference` also indexed for future reverse-lookup. No N+1 risks — index controller hits `Note::query()` directly, no eager relations needed for the Resource shape. All OK.
6. **Test coverage** — Feature tests cover all CRUD happy paths + 403 cross-user + 401 unauth + 404 missing + validation matrix + pagination + filter; Unit tests cover every Action and both new Rules. No gaps observed.

## Issue table

| # | Issue | Location | Severity | Status | Resolution |
|---|---|---|---|---|---|
| 1 | `Note::$guarded = []` exposes future mass-assignment footgun if someone later refactors an Action to call `fill($array)` / `create($array)`. Review iteration-1 suggestion. | `app/Domain/Notes/Models/Note.php:33` | Suggestion | Fixed | Swapped to explicit `$fillable = ['user_id', 'reference', 'book', 'content']`. Actions assign individually so behaviour is unchanged; list matches the four writable columns on `notes`. |
| 2 | `NoteQueryBuilder::forBook()` signature is `?string` where plan specified `string`. Review iteration-1 suggestion. | `app/Domain/Notes/QueryBuilders/NoteQueryBuilder.php:21` | Suggestion | Skipped-with-reason | Nullable signature is the more ergonomic call-site (`->forBook($request->book())` passes through a nullable without an extra branch in the controller). Deviation is documented in review and plan-deviations list; does not affect correctness or behaviour. |
| 3 | `Note::newEloquentBuilder()` return type widened to base `Builder` (PHPDoc narrows). Review iteration-1 suggestion. | `app/Domain/Notes/Models/Note.php:47` | Suggestion | Skipped-with-reason | Cannot narrow below the parent signature without a Larastan extension. PHPDoc `@return NoteQueryBuilder` already carries the static-analysis narrowing; `stan` is green. |
| 4 | `StoreNoteController` injects `ReferenceFormatter` as a method parameter and forwards it to `toData()`. Review iteration-1 suggestion. | `app/Http/Controllers/Api/V1/Notes/StoreNoteController.php:28-31` | Suggestion | Skipped-with-reason | Current form resolves cleanly via container injection at route dispatch and keeps the Form Request pure-PHP (easier to unit-test). The alternative (having `toData()` resolve from the container) is a stylistic swap, not an improvement. Non-blocking per reviewer. |
| 5 | `StripHtmlTest::test_length_is_measured_after_stripping` doc-comment mis-described the input (`<b></b>` vs actual `<b>hi</b>`). Review iteration-1 suggestion. | `tests/Unit/Http/Rules/StripHtmlTest.php:37-39` | Suggestion | Fixed | Rewrote the comment to accurately describe the 9→2 char reduction and why the `max:5` assertion meaningfully proves post-strip measurement. |
| 6 | Tripwire register still lists 4 copies of owner-`authorize()` under the old description; plan committed to re-framing around `AuthorizedXyzRequest` base + Policy with copies reset to 2 on story close. | `.agent-workflow/CLAUDE.md` §7 | Observation | Deferred-with-pointer | Tripwire register maintenance belongs to Improver per `.agent-workflow/CLAUDE.md` §4 (`Improver` section). Audit flags it for the next Improver pass. No code change in this audit. |

## Fresh-pass findings (new issues not flagged in Review or QA)

None. The multi-reference truncation (iteration 1 Critical) and the domain-coupled attribute-bag key (iteration 1 Warning) were both closed in commit `6766fa8` and verified by the iteration-2 re-review. A fresh read across the 36 changed files did not surface any additional architecture, security, or performance issues. The six Beyond-CRUD dimensions (minus Livewire per `.agent-workflow/CLAUDE.md` §1, plus the API Design substitution per §4) are all clean.

## Spot-checks

- ✅ Controllers are thin (Request in, Action call, Resource out).
- ✅ No inline `$request->validate()`; no Eloquent models returned directly.
- ✅ No Blade / Livewire / Tailwind / Vite introduced.
- ✅ No new composer dependencies.
- ✅ Routes under `/api/v1`, Sanctum applied to all four endpoints.
- ✅ `unsignedInteger` on `notes.user_id` matches the Symfony-era `users.id` width (consistent with `reading_plan_subscriptions`).
- ✅ JSON error envelope honoured on every failure path (validation 422, auth 401, policy 403, binding 404).
- ✅ `ValidReference` correctly rejects multi-reference input (`GEN.1-3.VDC`, `GEN.1:1;2.VDC`) with 422 + no DB write.
- ✅ `StripHtml` runs before `max:N` via `ValidatorAwareRule::setValue()`.
- ✅ `reference` field silently ignored on PATCH (asserted via DB fresh fetch).
- ✅ `Gate::policy(Note::class, NotePolicy::class)` wired in `AppServiceProvider::boot()`.

## Commands run

```
make lint-fix   # PINT PASS, 287 files
make stan       # PHPStan PASS, 267 files, 0 errors
make test       # PHPUnit 408 passed, 1257 assertions
```

## Verdict rationale

Zero Critical, zero unresolved Warnings. Two suggestions fixed (`$fillable`, test comment), three skipped with documented reasons, one deferred to Improver (register update). Full `make check` green. Story moves from `qa-passed` to `done`.
