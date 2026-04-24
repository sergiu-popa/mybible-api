# Audit: MBA-017-sabbath-school

**Auditor:** Auditor
**Branch:** `mba-017`
**Verdict:** PASS
**Status:** `done`

## Summary

Holistic pass over the Sabbath School port: six models + QueryBuilders,
four Actions with DTO/Result pairs, a sentinel support class, the
domain-specific `InvalidSabbathSchoolPassageException` wired into the JSON
handler, nine controllers, nine Form Requests, seven resources, six
migrations, six factories, and the full unit + feature test suite. Review
was APPROVE with zero Critical and zero Warnings; QA PASSED on 14/14 AC.
This audit addresses the only plan-vs-implementation drift (controller
bypassing the builder scope) and accounts for the remaining Suggestions.

## Gates

- `make lint` — clean (531 files).
- `make stan` — no errors (511 files).
- `make test filter=SabbathSchool` — 55 passed / 166 assertions / 1.24s.
- `make test` — 675 passed / 2071 assertions / 10.77s. No regressions.

## Findings

| # | Issue | Location | Severity | Status | Resolution |
|---|---|---|---|---|---|
| 1 | `ShowSabbathSchoolLessonController` called `$lesson->load(['segments.questions'])` directly, duplicating the relation list that `SabbathSchoolLessonQueryBuilder::withLessonDetail()` already owns — divergence from the plan's "eager-load via the builder scope". | `app/Http/Controllers/Api/V1/SabbathSchool/ShowSabbathSchoolLessonController.php:28`, `app/Domain/SabbathSchool/Models/SabbathSchoolLesson.php:60-68` | Warning | Fixed | Moved the eager-load into `SabbathSchoolLesson::resolveRouteBinding()` via `->withLessonDetail()`. Controller no longer touches the relation list; the builder is now the single source of truth for the lesson-detail graph. N+1 guard test still passes at 8 queries. |
| 2 | `SabbathSchoolHighlightQueryBuilder::forPassage(string)` is single-use (only consumed by `ToggleSabbathSchoolHighlightAction`). Review flagged as candidate for inlining. | `app/Domain/SabbathSchool/QueryBuilders/SabbathSchoolHighlightQueryBuilder.php:26-29` | Suggestion | Deferred | Keeps the action readable (`->forSegment()->forPassage()` chain reads as intent, not as a query). Revisit when a second call site appears or is deliberately removed. |
| 3 | `ListSabbathSchoolLessonsRequest` and `ShowSabbathSchoolLessonRequest` both duplicate the `language` whitelist that `ResolveRequestLanguage` also knows. | `app/Http/Requests/SabbathSchool/ListSabbathSchoolLessonsRequest.php:29-32`, `app/Http/Requests/SabbathSchool/ShowSabbathSchoolLessonRequest.php:24-27` | Suggestion | Skipped | Strict 422 on an unknown `language` is load-bearing for `test_it_validates_the_language_filter`. Extraction to a shared rule is warranted only when a third language-aware surface lands — same deferral posture as MBA-014. |
| 4 | `SabbathSchoolFavoriteResource` exposes both `segment_id` (nullable, sentinel mapped away) and `whole_lesson: bool` even though one is derivable from the other. | `app/Http/Resources/SabbathSchool/SabbathSchoolFavoriteResource.php:22-33` | Suggestion | Skipped | Reader-friendly dual surface. Removing `whole_lesson` is a public-API change; keeping it matches the explicit review guidance ("if a client already depends on it, leave it"). |
| 5 | `InvalidSabbathSchoolPassageException` re-exposes `$reason` from the wrapped reference exception (Review noted; no change expected). | `app/Domain/SabbathSchool/Exceptions/InvalidSabbathSchoolPassageException.php:17-33` | Suggestion | Skipped | Intentional — the handler renders `errors: { passage: [$e->reason] }`; the public readonly field is the contract. No code smell. |

## Checks performed

- [x] Architecture matches plan after Fix 1 — builder is the single eager-load source; controller is thin.
- [x] Layer separation: controllers delegate to Actions or the Form Request's `toData()`; no business logic leak.
- [x] All responses wrap models in API Resources; no raw Eloquent returned.
- [x] Validation via Form Requests exclusively; no inline `$request->validate()`.
- [x] Authorization in `authorize()` or via query-layer user scoping; cross-user GET/DELETE returns 404, not 403.
- [x] `InvalidSabbathSchoolPassageException` wired to `{ message, errors: { passage: [...] } }` at 422.
- [x] Route-model binding on `{lesson}` applies `published()` + now `withLessonDetail()`; drafts still 404.
- [x] N+1 guard (`test_it_avoids_n_plus_one_on_a_large_fixture`) still asserts ≤ 8 queries after moving the eager-load to the route binder.
- [x] Schema: unique `(user_id, question_id)` on answers; unique `(user_id, lesson_id, segment_id)` on favorites with segment_id=0 sentinel; FK intentionally omitted on favorite `segment_id` (commented).
- [x] `Cache-Control: public, max-age=3600` on both catalog endpoints; no per-user state on those payloads (safe for public caching).
- [x] Migrations all guard with `Schema::hasTable()` so replays are idempotent against the shared legacy DB.
- [x] Owner-`authorize()` tripwire (§7, 4/5) not advanced — new Form Requests scope ownership at the query layer, not via `user_id` equality in `authorize()`. Counter unchanged.
- [x] API design: 201 on highlight/favorite insert; 200 `{ deleted: true }` on delete; 201 on first answer save, 200 on overwrite, 204 on delete; 404 on missing caller answer; 422 on invalid passage — all consistent.
- [x] Versioning respected — every route under `/api/v1/sabbath-school`.

## Risks

None open. Migration set has not been deployed; the schema change from Fix 1
is purely application-layer.

## Verdict

All Critical + Warning findings resolved (Fix 1). Remaining Suggestions
accounted for (Deferred / Skipped with reason). Status advanced to `done`.
