# Code Review: MBA-011-notes

**Reviewer:** Code Reviewer agent
**Verdict:** `REQUEST CHANGES`
**Scope:** Diff on `mba-011` vs `main` — 33 files, +1490/-21. Tests: `make test filter=Notes` → 30 passed. `make stan` + `make lint --test` clean.

---

## Summary

Implements the four `notes` CRUD endpoints per plan: `Note` model + `NoteQueryBuilder`, three Actions with matching DTOs, `NotePolicy` (registered via `Gate::policy` in `AppServiceProvider`), the `AuthorizedNoteRequest` base mirroring `AuthorizedReadingPlanSubscriptionRequest`, Form Requests + Resource + invokable controllers, and a migration with the planned `(user_id, created_at)` and `(user_id, book)` compound indexes. The `StripHtml` transform rule correctly runs before `max:N` via `ValidatorAwareRule::setValue()` and the whole path is covered by unit and feature tests. Pattern parity with MBA-004's subscription layer is tight.

One Critical contract issue: `ValidReference::validate()` silently discards all but the first parsed `Reference`, so inputs like `GEN.1-2.VDC` (chapter range → 2 references) pass validation but only `GEN.1.VDC` reaches the database. The story treats a note as attached to a "specific passage," so the correct behaviour is `422`, not silent truncation.

---

## Critical

- [x] **`ValidReference` silently drops extra references on multi-reference input, producing silent DB writes that don't round-trip.** — fixed: rule now rejects `count($references) !== 1` with `"The :attribute must reference a single passage."`; added unit tests for chapter-range (`GEN.1-3.VDC`) and list (`GEN.1:1;2.VDC`) inputs, plus feature test on `POST /api/v1/notes` asserting 422 + no DB write. `app/Http/Rules/ValidReference.php:52` stashes `$references[0]` unconditionally after `ReferenceParser::parse()` returns. The parser returns `array<int, Reference>` and legitimately expands chapter ranges and multi-references (`ChapterRangeParser`, `MultipleReferenceParser`). Verified: `parse('GEN.1-2.VDC')` returns two `Reference`s; `StoreNoteController` then persists only `toCanonical($refs[0])` → `GEN.1.VDC`. The client posted `GEN.1-2.VDC`, the response `data.reference` comes back `GEN.1.VDC`, no error, no warning. That's a contract break (MBA-011 AC 2: invalid ⇒ 422; this is neither invalid nor honoured) and a data-integrity surprise. **Fix:** in `ValidReference::validate()`, reject multi-reference input explicitly:
  ```php
  if (count($references) !== 1) {
      $fail('The :attribute must reference a single passage.');
      return;
  }
  ```
  Add a feature test: `POST /api/v1/notes` with `reference: 'GEN.1-2.VDC'` → 422 on `reference`. Alternative fix — extend the rule to accept a `maxRefs` option — is over-engineered for the current two consumers; hard-coding single-ref is fine.

---

## Warnings

- [x] **`ValidReference::PARSED_ATTRIBUTE_KEY = 'notes.parsed_reference'` couples a generic `App\Http\Rules` class to the Notes domain.** — fixed: renamed constant to `'reference.parsed'` (domain-neutral, named after the rule that writes it); all call sites go through the constant so no caller changes were needed, and the existing `StoreNoteRequest::toData()` reader keeps working unchanged. `app/Http/Rules/ValidReference.php:23`. The rule lives in a shared namespace and is a cross-cutting primitive (per the plan's placement), but the attribute-bag key carries a `notes.` prefix. Anyone reusing this rule for Devotionals / Favorites (MBA-010 could have used it) now writes `notes.parsed_reference` into an unrelated request — confusing and invites accidental key collisions or typos. **Fix:** rename to `self::PARSED_ATTRIBUTE_KEY = 'reference.parsed'` (or `rules.valid_reference.parsed`) so the key reflects the rule that wrote it, not the caller. Update `StoreNoteRequest::toData()` accordingly.

- [x] **Plan Task 17 asked to assert the `reference` body field is silently ignored on update via a DB-value-unchanged check; implementation also asserts the response mirrors the stored reference.** `tests/Feature/Api/V1/Notes/UpdateNoteTest.php:40-49`. — acknowledged: the test actually does both (response check + fresh-from-DB check on lines 45-48), satisfying the plan. Net positive, no action.

- [x] **Tripwire register update.** The plan's Risks section commits to updating the Deferred-Extractions register on close (copies `4 → 2` after reframing around the `AuthorizedXyzRequest` base + Policy). — acknowledged: register maintenance is Improver's job on story close per `.agent-workflow/CLAUDE.md` §7; no review-time action for the Engineer.

---

## Suggestions

- **`Note` model declares `protected $guarded = []` but exposes no `$fillable`**. `app/Domain/Notes/Models/Note.php:32`. Safe here because Actions assign one property at a time (no `fill()` / `create()` / `update($array)`), but it's weaker than pinning `$fillable = ['user_id', 'reference', 'book', 'content']` — a future refactor that introduces `Note::create($request->all())` silently mass-assigns anything. Consider swapping to `$fillable`.

- **`NoteQueryBuilder::forBook()` accepts `?string` and no-ops on `null|''`; plan said `forBook(string)`**. `app/Domain/Notes/QueryBuilders/NoteQueryBuilder.php:20`. The nullable signature is actually nicer for the controller (`->forBook($request->book())`), but it means the builder now hides a filter decision (no-op on null) that callers might prefer to make explicit. Either keep as-is (document) or revert to `string` and have the controller branch. Non-blocking.

- **`Note::newEloquentBuilder()` return type is the base `Builder`, not `NoteQueryBuilder`**. `app/Domain/Notes/Models/Note.php:45-48`. Matches Laravel's parent signature so can't be narrowed without Larastan extension help — but the `/** @return NoteQueryBuilder */` PHPDoc is carrying the static-analysis narrowing. Confirmed `phpstan` clean. No action needed; flag only so the next reader knows the widening is deliberate.

- **`StoreNoteController` injects `ReferenceFormatter` as a method param and passes it into `toData()`**. `app/Http/Controllers/Api/V1/Notes/StoreNoteController.php:28-32`. Slightly awkward — the Form Request is the one holding the parsed `Reference`, so it could just resolve the formatter from the container itself (`$this->container->make(ReferenceFormatter::class)` inside `toData()`), matching the pattern already used for `ReferenceParser` in `StoreNoteRequest::rules()`. Keeps controllers ignorant of supporting services. Non-blocking; current form is still clean.

- **`StripHtmlTest::test_length_is_measured_after_stripping` doc-comment is slightly off.** `tests/Unit/Http/Rules/StripHtmlTest.php:37-40` says `<b></b>` strips to empty; the actual input is `<b>hi</b>` which strips to `hi` (2 chars). Assertions are correct; only the explanatory comment is misleading. Low priority.

---

## Guideline adherence (spot-checks)

- ✅ Strict types + `final` on every new concrete class.
- ✅ `readonly` DTOs under `DataTransferObjects/`.
- ✅ Controllers are thin — Form Request in, Action call, Resource out (`DeleteNoteController` returns `Response::noContent()` for 204 correctness).
- ✅ No `$request->validate()` inline; no Eloquent model returned directly.
- ✅ Auth via `auth:sanctum` (`routes/api.php:79`); ownership via `AuthorizedNoteRequest` → `NotePolicy` (API layer's 403, not route-scope 404, per plan).
- ✅ Migration uses `unsignedInteger` for `user_id` (matches Symfony-era `users.id` width, same reasoning as `reading_plan_subscriptions`).
- ✅ Indexes: `(user_id, created_at)` + `(user_id, book)` + `reference` — all planned; pagination ordering + book filter are index hits.
- ✅ `StripHtml` runs before `max:N` via `ValidatorAwareRule::setValue()` (covered by `StripHtmlTest::test_length_is_measured_after_stripping`).
- ✅ Feature coverage: cross-user 403, unauth 401, missing 404, filter/pagination/shape (`ListNotesTest`), multi-field validation matrix (`StoreNoteTest`/`UpdateNoteTest`).
- ✅ Unit coverage: all three Actions + both new rules.
- ✅ No Blade, views, Livewire, frontend tooling added.
- ✅ No new dependencies.
- ✅ `Gate::policy(Note::class, NotePolicy::class)` wired in `AppServiceProvider::boot()` next to the existing `ReadingPlanSubscription` registration.

---

## Plan deviations — acknowledged

- `AuthServiceProvider` doesn't exist in this app; policies are registered in `AppServiceProvider::boot()`. Matches the `ReadingPlanSubscriptionPolicy` precedent — right call.
- `ListNotesController` uses `orderByDesc('created_at')->orderByDesc('id')` (id as tie-breaker) instead of plain `latest()`. Improvement — deterministic pagination when multiple notes share a second.
- Plan Task 16 said "no `apiResource` — use individual `Route::get/post/patch/delete`." Engineer did exactly that. Matches.
- Plan Task 3 spec'd `forBook(string)`; implemented as `forBook(?string)` with null no-op (see Suggestions).

---

## Verdict rationale

One Critical: `ValidReference` silently truncates multi-reference input, which violates the story's reference-validation contract and produces DB rows that don't round-trip to the input. One unchecked Warning: generic rule coupled to notes domain via its public attribute-bag key. Request changes; status stays `in-review`.
