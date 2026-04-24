# QA — MBA-016 Educational Resources

## Test runs

- `make test filter=EducationalResources` — 29 passed / 112 assertions / 0.84s.
- `make test` (full) — 649 passed / 2017 assertions / 8.94s. No regressions.
- `make lint` — clean (489 files).
- `make stan` — no errors (468 files).

## AC → Test coverage

| AC | Test file:line |
|---|---|
| 1. `GET /api/v1/resource-categories?language={iso2}` shape (`id, name, description?, language, resource_count`) | `tests/Feature/Api/V1/EducationalResources/ListResourceCategoriesTest.php:26`, `:61`, `:74`, `:89` |
| 1. Default 50/page, max 100 | `ListResourceCategoriesTest.php:103`, `:114` |
| 2. `api-key-or-sanctum` on categories | `ListResourceCategoriesTest.php:136`, `:143` |
| 3. `Cache-Control: public, max-age=3600` | `ListResourceCategoriesTest.php:122` |
| 4. `GET /api/v1/resource-categories/{category}/resources` shape (`uuid, type, title, summary?, thumbnail_url?, published_at`) | `tests/Feature/Api/V1/EducationalResources/ListResourcesByCategoryTest.php:26` |
| 4. `?type={article|video|pdf|audio}` filter + 422 on invalid | `ListResourcesByCategoryTest.php:73`, `:96` |
| 4. 25/page default, max 100 (cap shared with categories via Form Request) | `ListResourcesByCategoryTest.php:109` |
| 5. Sorted newest first by `published_at` | `ListResourcesByCategoryTest.php:54`; unit `tests/Unit/Domain/EducationalResources/QueryBuilders/EducationalResourceQueryBuilderTest.php:38` |
| 6. `GET /api/v1/resources/{resource:uuid}` full detail incl. nested `category: { id, name }` | `tests/Feature/Api/V1/EducationalResources/ShowEducationalResourceTest.php:31`, `:89` |
| 7. UUID route-model binding (not integer id); unknown uuid ⇒ 404 | `ShowEducationalResourceTest.php:104`, `:112` |
| 8. `api-key-or-sanctum` on detail | `ShowEducationalResourceTest.php:122` |
| 9. Feature tests: listings, type filter, detail by uuid, 404 paths, pagination defaults | All three feature files present + `ListResourcesByCategoryTest.php:121` (unknown category 404) |
| 10. Unit tests for domain helpers | `tests/Unit/Domain/EducationalResources/QueryBuilders/ResourceCategoryQueryBuilderTest.php:17`, `:31`; `EducationalResourceQueryBuilderTest.php:17`, `:38`; `tests/Unit/Domain/EducationalResources/Support/MediaUrlResolverTest.php:13`, `:18`, `:23`, `:33` |

## Findings

- All 10 acceptance criteria mapped to passing tests.
- Review verdict was APPROVE with 0 Critical / 0 Warning (3 Suggestions acknowledged, non-blocking).
- Full suite: no regressions introduced by this story.
- Language fallback to English covered (`ListResourceCategoriesTest.php:89`), null media paths covered (`ShowEducationalResourceTest.php:89`), integer-id lookup explicitly denied (`ShowEducationalResourceTest.php:112`).

## Verdict

QA PASSED
