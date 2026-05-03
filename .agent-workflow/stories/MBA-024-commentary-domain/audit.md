# Audit — MBA-024-commentary-domain

Holistic pass over the commentary domain after Review/QA passed. Six
dimensions checked: architecture compliance, code quality, API design,
security/authorization, performance, and test coverage.

## Findings

| # | Issue | Location | Severity | Status | Resolution |
|---|---|---|---|---|---|
| 1 | `AdminCommentaryResource` resolves `name` to a single string via `parent::toArray()`, leaking only the request-language translation. Admin clients editing a commentary cannot read the other translations and would clobber them on PATCH. | `app/Http/Resources/Commentary/AdminCommentaryResource.php:22` | Warning | Fixed | Override `name` after the parent spread to expose the raw multilingual map. Added regression test `test_list_exposes_raw_multilingual_name_for_admins` asserting both `name.en` and `name.ro` are present in the admin list response. |
| 2 | `UpdateCommentaryRequest` accepts `source_commentary_id` equal to the commentary's own id, allowing a self-referential translation loop that breaks the conceptual model and would confuse the AI translation pipeline (MBA-029) consuming this column. | `app/Http/Requests/Admin/Commentary/UpdateCommentaryRequest.php:42-47` | Warning | Fixed | Added `Rule::notIn([$commentaryId])` to the validation chain. Added regression test `test_update_rejects_self_referential_source_commentary`. |
| 3 | `UpdateCommentaryTextRequest` only runs the `(commentary_id, book, chapter, position)` uniqueness check when `position` is in the payload. A PATCH that changes only `book` or `chapter` (keeping the same `position`) skips the validator and falls through to the DB UNIQUE constraint, surfacing as a 500 instead of a 422 if the destination tuple already has that position. | `app/Http/Requests/Admin/Commentary/UpdateCommentaryTextRequest.php:44-54` | Suggestion | Deferred | Edge case: admins typically don't move blocks across books/chapters; a 500 is recoverable by re-submitting with a free position. Fix would require restructuring validation to run the unique check whenever any of `book`/`chapter`/`position` is present. Pointer: revisit if MB-017 admin UI exposes a "move block" workflow. |
| 4 | `AdminCommentariesTest::actingAsSuper()` / `actingAsAdmin()` boilerplate duplicated across `AdminCommentariesTest`, `AdminCommentaryTextsTest`, and three other admin feature tests; per CLAUDE.md §6 a `Tests\Concerns` trait would consolidate. | `tests/Feature/Api/V1/Admin/Commentary/AdminCommentariesTest.php:17` + `AdminCommentaryTextsTest.php:17` | Suggestion | Deferred | Already flagged in the prior review round and acknowledged as out-of-scope for MBA-024. Pointer: extract `Tests\Concerns\InteractsWithAuthentication::givenSuperAdmin()` / `givenAdmin()` when a sixth admin feature test lands. |
| 5 | `CommentaryTextResource` exposes `book` and `chapter` even though they're URL-pinned on both public endpoints. | `app/Http/Resources/Commentary/CommentaryTextResource.php:22-23` | Suggestion | Skipped | Already deliberated in review: the values are row-intrinsic identifiers (not filter echoes); callers caching/round-tripping responses need them without re-deriving from the URL. |
| 6 | `CreateCommentaryAction::autoSlug()` has a TOCTOU race (concurrent admin creates with the same abbreviation could both pass `exists()` and the second hits the UNIQUE as a 500). | `app/Domain/Commentary/Actions/CreateCommentaryAction.php:29` | Suggestion | Skipped | Already acknowledged in review: super-admin endpoint with effectively zero concurrent writers; the UNIQUE constraint is the safety net and a 500 in that race is recoverable by re-submitting. |
| 7 | `CreateCommentaryRequest::name.*` and `UpdateCommentaryRequest::name.*` validate values but not array keys, so `{"name": {"xx": "..."}}` would persist a junk-language key that `LanguageResolver` silently ignores. | `app/Http/Requests/Admin/Commentary/CreateCommentaryRequest.php:27` + `UpdateCommentaryRequest.php:36` | Suggestion | Skipped | Same loose validation pattern is used by neighbour multi-language Resource writes (e.g. EducationalResources); a consistency sweep is a separate story per the review. |

## Dimension recap

- **Architecture compliance** — Beyond CRUD layout (Domain/Models, QueryBuilders, Actions, DTOs; Http/Controllers/Api/V1, Requests, Resources) is followed; controllers delegate to Actions; no business logic in HTTP layer.
- **Code quality** — `final` classes with strict types; PHPDoc-typed array shapes; no `else`; consistent naming; no magic strings (Language enum, BibleBookCatalog).
- **API design** — Correct verbs and status codes (201 on create, 204 on delete, 422 on validation, 401/403/404 paths covered); JSON error envelope from the global handler; `/api/v1` prefix; `cache.headers:public;max_age=3600;etag` on public reads; idempotent reorder.
- **Security/authorization** — All admin routes behind `auth:sanctum` + `super-admin`; public reads behind `api-key-or-sanctum` + `resolve-language` + `throttle:public-anon`; nested `{commentary}/{text}` wrapped in `Route::scopeBindings()` so cross-commentary text ids 404 (verified by feature test); slug-bound public routes hide drafts via `resolveRouteBinding` published-on-slug.
- **Performance** — Composite `(language, is_published)` index supports the hot public-list path; `(commentary_id, book, chapter, verse_from, verse_to)` index supports the per-verse modal hot path; `sourceCommentary` eager-loaded to avoid N+1; admin texts list is paginated.
- **Test coverage** — 40 commentary-scoped tests pass (38 original + 2 added by this audit); full suite 1120 passing (was 1118), 4100 assertions; lint and stan green.

## Verdict

**PASS** — all Critical/Warning resolved; status → `done`.
