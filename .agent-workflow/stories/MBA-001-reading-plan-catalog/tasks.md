# Tasks: MBA-001 — Reading Plan Catalog

> Implementation order is dependency-driven: enums → schema → models →
> support → HTTP → tests. Run `make lint-fix && make stan && make test`
> after each cluster of tasks.

## Foundations

- [ ] 1. Create `app/Domain/Shared/Enums/Language.php` (`final enum Language: string { case En = 'en'; case Ro = 'ro'; case Hu = 'hu'; }`) with `fromRequest(?string $value, self $fallback = self::En): self`.
- [ ] 2. Create `app/Domain/ReadingPlans/` directory tree (`Models/`, `Enums/`, `QueryBuilders/`, `Support/`).
- [ ] 3. Create enums `ReadingPlanStatus` (`Draft`, `Published`) and `FragmentType` (`Html`, `References`). String-backed, `final`.

## Schema

- [ ] 4. Create migration `create_reading_plans_table` (json `name`/`description`/`image`/`thumbnail`, string `status`, nullable `published_at`, timestamps, softDeletes, index `(status, published_at)`).
- [ ] 5. Create migration `create_reading_plan_days_table` (FK `reading_plan_id` cascade, `position`, unique `(reading_plan_id, position)`). **No `name` column.**
- [ ] 6. Create migration `create_reading_plan_day_fragments_table` (FK `reading_plan_day_id` cascade, `position`, string `type`, json `content`, unique `(reading_plan_day_id, position)`).
- [ ] 7. Run `make migrate` and confirm schema with the `database-schema` MCP tool.

## Models & QueryBuilder

- [ ] 8. Create `ReadingPlan` model (`final`, soft deletes, casts `name|description|image|thumbnail` to json + `published_at` datetime + `status` to enum, `newEloquentBuilder()` returning `ReadingPlanQueryBuilder`, `days()` hasMany ordered by `position`).
- [ ] 9. Create `ReadingPlanDay` model (`belongsTo(ReadingPlan)`, `fragments()` hasMany ordered by `position`).
- [ ] 10. Create `ReadingPlanDayFragment` model (casts `content` json + `type` enum, `belongsTo(ReadingPlanDay)`).
- [ ] 11. Create `ReadingPlanQueryBuilder` with `published()` and `withDaysAndFragments()` scopes.

## Support

- [ ] 12. Create `LanguageResolver::resolve(array $map, Language $language, Language $fallback = Language::En): ?string`.

## Tests — Foundations

- [ ] 13. `tests/Unit/Domain/Shared/Enums/LanguageTest` (fromRequest with valid/invalid/null values).
- [ ] 14. `tests/Unit/Domain/ReadingPlans/Support/LanguageResolverTest` (matched, fallback to en, neither available returns null).
- [ ] 15. `tests/Unit/Domain/ReadingPlans/QueryBuilders/ReadingPlanQueryBuilderTest::test_published_excludes_drafts_and_unpublished`.

## Factories & Seeder

- [ ] 16. Generate factories for the three models (`php artisan make:factory --no-interaction`).
- [ ] 17. Implement factory states: `ReadingPlanFactory::published()`, `::draft()`; `ReadingPlanDayFragmentFactory::html()`, `::references()`. Multilingual fixtures populate `en` + `ro` only (Hungarian omitted; fallback path covered by tests).
- [ ] 18. Create `ReadingPlanSeeder` producing one 7-day published plan in `en` + `ro`, mixing `html` and `references` fragments. Wire into `DatabaseSeeder`.

## HTTP

- [ ] 19. Create `App\Http\Middleware\ResolveRequestLanguage` (reads `language` query param, binds resolved `Language` enum into the container as `reading-plans.language`).
- [ ] 20. Register the middleware on the `v1` route group in `routes/api.php` (or `bootstrap/app.php` as a named alias and apply to the group).
- [ ] 21. Create `ListReadingPlansRequest` (rules + `language(): Language` helper).
- [ ] 22. Create `ShowReadingPlanRequest` (rules + `language(): Language` helper).
- [ ] 23. Create `ReadingPlanDayFragmentResource` (resolves html via `LanguageResolver`; for references returns the raw array of strings as stored).
- [ ] 24. Create `ReadingPlanDayResource` (`{ id, position, fragments }`).
- [ ] 25. Create `ReadingPlanResource` (`{ id, slug, name, description, image, thumbnail, status, published_at, days? }` with language resolution; `days` only when relation loaded).
- [ ] 26. Create `ListReadingPlansController` (invokable, paginated via `ReadingPlanQueryBuilder::published()`, default `per_page = 15`).
- [ ] 27. Create `ShowReadingPlanController` (invokable, route-model bound on `slug`, eager-loads via `withDaysAndFragments()`).
- [ ] 28. Register both routes in `routes/api.php` inside the existing `v1` group with named routes (`reading-plans.index`, `reading-plans.show`).

## Tests — HTTP

- [ ] 29. `tests/Feature/Api/V1/ReadingPlans/ListReadingPlansTest`: returns published only, default `per_page=15`, `per_page` cap at 100, language fallback when requested language missing, drafts/unpublished excluded.
- [ ] 30. `tests/Feature/Api/V1/ReadingPlans/ShowReadingPlanTest`: full tree, language resolution + fallback per fragment, references returned as raw strings, 404 on unknown slug.

## Polish

- [ ] 31. Run `make lint-fix`.
- [ ] 32. Run `make stan` and resolve all PHPStan findings.
- [ ] 33. Run full `make test`; ensure green.
