# Code Review: MBA-017 — Sabbath School

**Commit reviewed:** `f1d39bd`
**Branch:** `mba-017`
**Verdict:** `APPROVE`

## Summary

The implementation ports Sabbath School end-to-end under the planned
`app/Domain/SabbathSchool` layout: six models + QueryBuilders, four
Actions with DTO/Result pairs, a sentinel support class, a
domain-specific exception wired into the JSON handler, nine
controllers, nine Form Requests, seven resources, six migrations, six
factories, and both unit and feature test suites.

- `make test filter=SabbathSchool` — 55 passed, 166 assertions (1.25s).
- `make stan` — no errors (511 files).
- All 39 plan tasks ticked in `plan.md`.

AC coverage is complete: language-scoped paginated catalog
(`ListSabbathSchoolLessonsController`), lesson detail with segments +
questions and N+1 guard test, answer upsert/show/delete with
cross-user 404 semantics, highlight toggle with reference-parser
validation on write, favorite toggle with the sentinel-0 pattern, and
`Cache-Control: public, max-age=3600` on both public catalog
endpoints.

No Critical findings. No unchecked Warnings.

## Critical

_None._

## Warnings

_None._

## Suggestions

- [ ] `app/Http/Controllers/Api/V1/SabbathSchool/ShowSabbathSchoolLessonController.php:28`
  — uses `$lesson->load(['segments.questions'])` after route-model
  binding instead of the `withLessonDetail()` builder scope listed in
  the plan. Functionally equivalent (both issue the same eager-load
  pair) and the test proves N+1 is avoided. A small follow-up: expose
  the eager-load path through the builder so a future caller of the
  builder gets the same guarantee without duplicating the relation
  list in the controller. Non-blocking.

- [ ] `app/Domain/SabbathSchool/QueryBuilders/SabbathSchoolHighlightQueryBuilder.php:26-29`
  — `forPassage(string $passage)` is consumed only by the toggle
  Action on a single call site. Fine as written; if no other callers
  appear in the next story that touches highlights, consider folding
  it back to an inline `->where('passage', …)` to keep the public
  builder surface tight. Non-blocking.

- [ ] `app/Http/Requests/SabbathSchool/ListSabbathSchoolLessonsRequest.php:26-34`
  — `rules()` validates `language` even though `ResolveRequestLanguage`
  already falls back silently on unknown values. The current posture
  (strict-reject via Form Request) is intentional per the failing test
  `test_it_validates_the_language_filter`, but it duplicates the
  knowledge of accepted codes between the middleware fallback and the
  validator. Not a bug; a candidate for a shared rule if a third
  language-aware endpoint lands. Non-blocking.

- [ ] `app/Http/Resources/SabbathSchool/SabbathSchoolFavoriteResource.php:22-33`
  — surfaces both `segment_id: null` (sentinel mapped away) and
  `whole_lesson: bool`. The boolean is derivable from the nullable
  id; either field alone is sufficient. Keeping both is reader-
  friendly but adds a public-API field that cannot vary independently
  of `segment_id`. Non-blocking; if a client already depends on
  `whole_lesson`, leave it.

- [ ] `app/Domain/SabbathSchool/Exceptions/InvalidSabbathSchoolPassageException.php:17`
  — PSR-compliant but the plan had it "extends `\RuntimeException`";
  matches. Minor point: the public readonly `$reason` is re-exposed
  from the wrapped `InvalidReferenceException`, which is fine.

## Checks Performed

- [x] Architecture matches plan (six models, QueryBuilders, four
  Actions, DTOs, sentinel, exception, nine controllers, nine FRs,
  seven resources).
- [x] Layer separation: controllers call Actions or delegate to the
  Form Request `toData()` builder; no business logic leaks into
  controllers.
- [x] All responses use API Resources; no raw Eloquent models
  returned.
- [x] Validation via Form Requests exclusively; no
  `$request->validate()` inline.
- [x] Authorization: Form Request `authorize()` guards
  owner-gated endpoints; cross-user access returns 404 (Show, Delete
  Answer), not 403.
- [x] Exception path: `InvalidSabbathSchoolPassageException` is wired
  in `bootstrap/app.php` to the standard `{ message, errors }`
  envelope at 422.
- [x] Route-model binding on `{lesson}` is scoped to `published()` via
  `resolveRouteBinding` — drafts 404.
- [x] N+1 guard: `ShowSabbathSchoolLessonTest::test_it_avoids_n_plus_one_on_a_large_fixture`
  asserts ≤ 8 queries on a 7×5 fixture.
- [x] Schema: unique indexes on `(user_id, question_id)` for answers
  and `(user_id, lesson_id, segment_id)` for favorites. Sentinel
  column documented; FK intentionally omitted on favorite
  `segment_id` (comment present in the migration).
- [x] Cache headers applied to catalog endpoints only; per-user state
  kept off those payloads.
- [x] Tests cover all AC: lesson listing with pagination + language +
  cache; detail with nested questions; answer CRUD happy and cross-
  user paths; highlight toggle + 422 on unparseable passage;
  favorite toggle with and without `segment_id`; 401 on missing
  auth for every caller-data endpoint.
- [x] Owner-`authorize()` tripwire (§7 at 4/5) unaffected — the new
  Form Requests scope ownership at the query layer, not via
  `user_id` equality in `authorize()`, so the counter does not
  advance.
- [x] `make stan` clean, `make test filter=SabbathSchool` clean.
- [x] Public API contract: no constant-under-scope fields detected.
  `whole_lesson` on favorites is derivable but varies per row.
