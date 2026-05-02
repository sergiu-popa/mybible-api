# Review — MBA-024-commentary-domain

> Reference `FILE:LINE` for every finding. Add severity section headers only when the section has findings.
> Severity sections: `## Critical`, `## Warning`, `## Suggestion`. Each finding is a GitHub checkbox: `- [ ] FILE:LINE — issue and concrete fix.`
> Acknowledge-only Warnings: tick the box and append `— acknowledged: <one-line reason>`. No unchecked Warning may remain in an APPROVED review.

## Warning

- [x] `app/Http/Resources/Commentary/CommentaryResource.php:31` + `app/Http/Controllers/Api/V1/Commentary/ListPublishedCommentariesController.php:20` — Public `source_commentary` field is gated by `whenLoaded('sourceCommentary')`, but no public controller eager-loads the relation. Net effect: the contract documented in story AC §6 / story.md L177 ("`source_commentary` (nested resource when present, indicating a translation)") is never satisfied — the field always renders missing. Add `->with('sourceCommentary')` to the `Commentary::query()` chain in `ListPublishedCommentariesController`, or set `protected $with = ['sourceCommentary']` on the model. The translation-indicator is the only public-facing signal that distinguishes a translated commentary from an original one, so frontend MB-019 will be unable to render the "translated from" notice without it.

- [x] `database/migrations/2026_05_03_001000_add_publication_metadata_to_commentaries_table.php:22` vs `database/migrations/2026_04_22_230000_create_commentaries_and_commentary_texts_tables.php:35` — Schema parity gap. The fresh-create migration adds `commentaries_language_published_idx` on `(language, is_published)`; the production reconcile-then-evolve path never adds it. The hot path here is `ListPublishedCommentariesController` and `CommentaryQueryBuilder::published()->forLanguage(...)` — both filter exactly on `(language, is_published)`. Production rows will table-scan. Add an `ensureLanguagePublishedIndex()` step to `2026_05_03_001000` that introspects existing indexes (mirroring `ensureUniquePosition()` in the sibling migration) and adds the composite if missing.

- [x] `app/Http/Resources/Commentary/CommentaryResource.php:30` — `language` field is constant under the public-list query scope. `ListPublishedCommentariesController` always applies `forLanguage($requestLanguage)`, so every row in `data[]` carries the same `language` value as the request — no information delta. Per CLAUDE.md (§Code Reviewer "Public API contract"): "flag any response field whose value is constant under the query scope". Either drop `language` from the public `CommentaryResource` (admin keeps it via `AdminCommentaryResource`), or document why it must stay (e.g. clients may receive cached responses across languages). The chapter/verse endpoints don't return Commentary at all so they're unaffected.

## Suggestion

- [x] `app/Domain/Commentary/Actions/CreateCommentaryAction.php:15` — When `slug` is absent, the action computes `Str::slug(strtolower($abbreviation))` and inserts. There is no uniqueness retry: if an existing row already holds that slug, the insert raises a 500 (the `unique:commentaries,slug` validation in `CreateCommentaryRequest` only runs when slug is *provided*). Either (a) require slug in the request, (b) add a `-2`/`-3` suffix loop in the action mirroring the migration backfill (`ensureSlugColumn`), or (c) wrap in a try/catch that surfaces a 422 instead of a 500. Admin-only blast radius is small but the failure mode is unfriendly.

- [x] `app/Http/Controllers/Api/V1/Commentary/ListCommentaryChapterTextsController.php:21` — `$commentary->texts()` already orders by `position` (declared in `Commentary::texts()`); the controller's `->orderBy('position')` is a redundant second `ORDER BY position`. Drop the controller-level orderBy. Same harmless duplication in `ListCommentaryTextsController.php:20` and `ListCommentaryVerseTextsController.php:20`.

- [x] `app/Http/Controllers/Api/V1/Admin/Commentary/ListCommentariesController.php:16` — `->orderBy('language')` is wasted work when `forLanguage($language)` is also applied (every row will share the same value). Reorder so language sort is conditional on the absence of the language filter.

- [x] `tests/Feature/Domain/Commentary/CommentaryTextQueryBuilderTest.php:1` — Plan task #23 calls this a "unit test" (`tests/Unit/...`), but it lives under `tests/Feature/Domain/...`. It does run (uses `RefreshDatabase`), so this is a categorization nit only. Move to `tests/Unit/Domain/Commentary/` if you want to honour the plan's classification, otherwise update the plan note.

- [x] `app/Http/Requests/Admin/Commentary/CreateCommentaryRequest.php:25` — `alpha_dash` allows underscores, but `Str::slug()` (used by the create action when slug is omitted, and by the migration backfill) never produces underscores. Operators who type `my_slug` will pass validation but break URL conventions established by `reading_plans.slug` / `hymnal_books.slug`. Tighten to a regex `^[a-z0-9-]+$` for parity with the auto-generated shape.

- [x] `tests/Feature/Api/V1/Admin/Commentary/AdminCommentariesTest.php:17` + `tests/Feature/Api/V1/Admin/Commentary/AdminCommentaryTextsTest.php:17` — `actingAsSuper()` / `actingAsAdmin()` are duplicated here and across `Admin/Imports/ShowImportJobTest`, `Admin/Olympiad/ReorderOlympiadQuestionsTest`, `Admin/References/ValidateReferenceTest`. The repo already has `Tests\Concerns\InteractsWithAuthentication` for the basic case. Per CLAUDE.md §6 ("when the same setUp boilerplate appears in a second feature test, extract it"), extending the trait with `givenSuperAdmin()` / `givenAdmin()` would consolidate four+ copies. Out of scope for this story but worth raising as a small follow-up. — acknowledged: reviewer flagged as out-of-scope follow-up, not a story finding.

## Verdict

REQUEST CHANGES
