# Review — MBA-024-commentary-domain

> Reference `FILE:LINE` for every finding. Add severity section headers only when the section has findings.
> Severity sections: `## Critical`, `## Warning`, `## Suggestion`. Each finding is a GitHub checkbox: `- [ ] FILE:LINE — issue and concrete fix.`
> Acknowledge-only Warnings: tick the box and append `— acknowledged: <one-line reason>`. No unchecked Warning may remain in an APPROVED review.

## Resolved in re-review (2026-05-03 against 5e64512)

Previous round (commit 28bc3f4) raised three Warnings + five Suggestions; all addressed by 5e64512 and re-verified:

- [x] `app/Http/Controllers/Api/V1/Commentary/ListPublishedCommentariesController.php:21` — `->with('sourceCommentary')` now eager-loads, so the documented `source_commentary` field actually renders.
- [x] `database/migrations/2026_05_03_001000_add_publication_metadata_to_commentaries_table.php:190` — `ensureLanguagePublishedIndex()` adds the `(language, is_published)` composite on the production-evolve path; matches the fresh-create migration at `2026_04_22_230000_…:35`.
- [x] `app/Http/Resources/Commentary/CommentaryResource.php:26` — `language` removed from the public shape (constant under request-language scope); `AdminCommentaryResource:23` re-adds it for the admin contract.
- [x] `app/Domain/Commentary/Actions/CreateCommentaryAction.php:29` — `autoSlug()` mirrors the migration backfill: tries `slug`, then `slug-{language}`, then `slug-{language}-{n}`. No more 500 on auto-derived collision.
- [x] Redundant `->orderBy('position')` removed from `ListCommentaryChapterTextsController`, `ListCommentaryVerseTextsController`, `ListCommentaryTextsController` (admin); the `Commentary::texts()` relation already orders.
- [x] `app/Http/Controllers/Api/V1/Admin/Commentary/ListCommentariesController.php:21` — `orderBy('language')` is now conditional on the absence of `?language=`.
- [x] `tests/Unit/Domain/Commentary/CommentaryTextQueryBuilderTest.php` — moved under `tests/Unit/…` per plan task #23.
- [x] `app/Http/Requests/Admin/Commentary/CreateCommentaryRequest.php:25` + `UpdateCommentaryRequest.php:32` — slug validation tightened to `regex:/^[a-z0-9-]+$/` for parity with `Str::slug` output.

Re-verified: 38 Commentary tests pass, lint + stan green.

## Suggestion

- [x] `app/Domain/Commentary/Actions/CreateCommentaryAction.php:29` — `autoSlug()` has a small TOCTOU race: two simultaneous creates can both pass the `exists()` check and only one will survive the unique constraint (raises a 500 instead of a 422). Admin endpoint, low concurrency, so pragmatically negligible; either wrap the insert in a `try { … } catch (QueryException $e) { /* retry */ }` or accept the risk. — acknowledged: super-admin-only endpoint with effectively no concurrent writers; the unique constraint is the safety net and a 500 in that race is recoverable by re-submitting.

- [x] `app/Http/Resources/Commentary/CommentaryTextResource.php:22-23` — `book` and `chapter` are constant under both public endpoints' query scope (the URL pins them). Per the API-contract reviewer rule, candidate for trimming. Counter-argument: these are intrinsic identifiers of the row that callers want when caching/round-tripping responses, not just filter echoes. — acknowledged: keep as-is; the data is row-intrinsic, not a filter echo, and the alternative (callers re-deriving from the URL) hurts client ergonomics.

- [x] `app/Http/Requests/Admin/Commentary/CreateCommentaryRequest.php:27` + `UpdateCommentaryRequest.php:36` — `name.*` validates `required|string|max:255` but does not constrain the array keys to ISO-2 language codes (`Language::cases()`). An operator could send `{ "name": { "xx": "…" } }` and persist a junk-key translation that `LanguageResolver` will silently ignore. — acknowledged: the same loose validation exists on neighbour multi-language Resource writes (e.g. `EducationalResources`); fixing it across the codebase is a separate consistency story, not MBA-024 scope.

- [x] `tests/Feature/Api/V1/Admin/Commentary/AdminCommentariesTest.php:17` + `AdminCommentaryTextsTest.php:17` — `actingAsSuper()` / `actingAsAdmin()` duplicated here; same boilerplate exists in `Admin/Imports/ShowImportJobTest`, `Admin/Olympiad/ReorderOlympiadQuestionsTest`, `Admin/References/ValidateReferenceTest`. Per CLAUDE.md §6, extending `Tests\Concerns\InteractsWithAuthentication` with `givenSuperAdmin()` / `givenAdmin()` would consolidate four+ copies. — acknowledged: out-of-scope follow-up first flagged in the prior review round; not gating MBA-024.

## Verdict

APPROVE
