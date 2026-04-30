# Plan — MBA-022-mb-013-handoff-implementation

## Approach

Deliver the API-side subset of the MB-013 handoff backlog
(`apps/admin/.agent-workflow/stories/MB-013-api-db-improvements/improvements.md`)
as **one item per commit** on branch `mb-013-api-improvements`. Each task
below maps 1:1 to a commit and to an `improvements.md` ID (S-NN, E-NN,
P-NN, Perf-NN, C-NN). Commits are **chronological** — earlier commits land
the schema/model foundation that later commits then consume. Reviewers can
walk the branch top-down and cross-reference each item.

Three structural decisions worth flagging:

1. **No new domain bases.** Admin features land under
   `App\Domain\Admin\<Subdomain>` (`Admin\Users`, `Admin\Imports`,
   `Admin\Uploads`) and `App\Http\Controllers\Api\V1\Admin\…` —
   consistent with the existing Beyond-CRUD layout. Reorder Actions for
   Resources / Sabbath School / Olympiad live next to their existing
   domain code (no `Admin\…` namespace for them, since they mutate
   public-domain models).
2. **Single shared `ReorderRequest`** under
   `App\Http\Requests\Admin\ReorderRequest`. Started life under
   `Admin\EducationalResources\ReorderRequest` and was promoted up a
   level in commit `7c0c60c` when the second consumer (Sabbath School)
   landed — extraction at copy-2 because the rule is identical
   (`array<int>` of positive integers, full-array idempotent).
3. **Middleware → controller pipeline.** Admin write endpoints stack
   `auth:sanctum` + `admin` (or `super-admin`). Public read endpoints
   stay unauthed; `Cache-Control` headers are added at the route level
   in `routes/api.php` rather than per-controller — keeps the perf
   layer auditable in one place.

## Deliverables

| Artifact | Location | Purpose |
|---|---|---|
| 9 migrations | `database/migrations/2026_04_30_000000…000008_*.php` | Schema deltas (S-01, S-04, S-07, S-08, S-09, S-10, C-01) + backings (`ui_locale`/`is_active`, `import_jobs`, `olympiad_questions.position`) |
| 2 middleware classes | `app/Http/Middleware/Ensure{Admin,SuperAdmin}.php` | P-02, P-03 |
| 2 `User` helpers | `app/Models/User.php` | P-01: `canManageLanguage()`, `canManageLanguageless()` |
| 5 admin user CRUD endpoints | `App\Http\Controllers\Api\V1\Admin\Users\…` | E-09 |
| 5 reorder endpoints | `App\Http\Controllers\Api\V1\Admin\{EducationalResources,SabbathSchool,Olympiad}\…` | E-02 (Resources / SS / Olympiad) |
| 1 reference validation endpoint | `App\Http\Controllers\Api\V1\Admin\References\ValidateReferenceController` | E-03 |
| 1 import-job polling endpoint + model | `App\Domain\Admin\Imports\…`, controller + resource | E-04 |
| 1 presigned upload endpoint + S3 cleanup job | `App\Domain\Admin\Uploads\…` | E-06, C-03 |
| Enriched `/auth/me` payload | `App\Http\Resources\Auth\UserResource` | E-07 |
| Olympiad theme aggregation contract | `ListOlympiadThemesController`, `ShowOlympiadThemeController` (PHPDoc) | E-10 |
| `Cache-Control` on public read routes | `routes/api.php` | Perf-02 |
| Feature + unit tests | `tests/Feature/…`, `tests/Unit/…` | One test file per shipped capability |

## `improvements.md` mapping

| Item ID | Commit | Status post-commit | Notes |
|---|---|---|---|
| S-10 | `ff3b7d9` | implemented | Migration promotes oldest `admin`-roled user to `is_super = true`. |
| S-01 | `b310bfe` | implemented | Legacy `language` column preserved; backfill maps 3-char → 2-char. |
| P-02 | `a0c4d57` | implemented | `EnsureAdmin` middleware (alias `admin`). |
| E-07 + backing | `80323b8` | implemented | Adds `users.ui_locale` + `users.is_active`. |
| P-03 | `eb3d3b5` | implemented | `EnsureSuperAdmin` middleware (alias `super-admin`). |
| E-01 + E-09 | `4102740` | implemented | `/api/v1/admin` group + admin user CRUD. |
| S-07 + S-08 + E-02 (Resources) | `32bbd10` | implemented | `position` columns + 2 reorder endpoints + composite indexes. |
| P-01 | `abdf641` | implemented | `User::canManageLanguage()` + `canManageLanguageless()` helpers. |
| S-04 | `77e2b98` | implemented | Renamed `news.image_path` → `news.image_url` (no new column). |
| S-09 | `4854ffa` | implemented | `daily_verse.language` nullable; existing rows stay NULL. |
| C-01 | `3f50b58` | implemented | Collapses `ROLE_EDITOR` into `admin`; deduplicates roles. |
| E-03 | `44afedf` | implemented | `POST /api/v1/admin/references/validate` (no side effects). |
| E-10 | `369d185` | implemented (docs) | PHPDoc on Olympiad public controllers documents aggregation contract. |
| E-04 + backing | `fde4266` | implemented | `import_jobs` table + `ImportJob` model + `GET /api/v1/admin/imports/{importJob}`. |
| E-06 | `8430db5` | implemented | `POST /api/v1/admin/uploads/presign`. |
| C-03 | `b9bf5f6` | implemented | `EducationalResource` deletion enqueues `DeleteUploadedObjectJob` for S3 cleanup (hard-deletes only). |
| E-02 (Sabbath School) | `7c0c60c` | implemented | Lesson-segment + segment-question reorder endpoints; `ReorderRequest` promoted to shared `Admin\ReorderRequest`. |
| E-02 (Olympiad) + backing | `2b81011` | implemented | `olympiad_questions.position` + theme-scoped reorder endpoint. |
| Perf-02 | `a22179f` | implemented | `Cache-Control: public, max-age=...` on public read routes (HTTP-cache only). |

Items unchanged by this branch: **S-02, S-03, E-08** (already shipped
earlier — confirmed during MB-013 audit) and **S-05, S-06, E-02
(Devotional types), E-05, Perf-01, C-02** (still `proposed`).

## Tasks

- [x] 1. **S-10** (`ff3b7d9`). Add migration `2026_04_30_000000_add_is_super_to_users_table.php` introducing `users.is_super` (boolean, default `false`) and promoting the oldest `admin`-roled user to `is_super = true`. Add `is_super` to `User::$casts`. Add `UserFactory::admin()` and `UserFactory::super()` states. Cover with `tests/Feature/Database/UserIsSuperTest.php` (column exists, cast works, factory states behave, oldest-admin promotion fires once).
- [x] 2. **S-01** (`b310bfe`). Add migration `2026_04_30_000001_add_languages_to_users_table.php` introducing `users.languages` (JSON array) and backfilling from the legacy 3-char `users.language` (`rom` → `ro`, etc.). Cast `languages` to `array` on `User`; preserve legacy `language` column as-is. Extend `UserFactory` with a `languages([])` helper. Cover with `tests/Feature/Database/UserLanguagesTest.php` (backfill mapping, empty/unknown defaults, accessor shape).
- [x] 3. **P-02** (`a0c4d57`). Add `App\Http\Middleware\EnsureAdmin` (alias `admin`) — 401 if no Sanctum bearer, 403 if authenticated user lacks `"admin"` membership in `users.roles`. Register the alias in `bootstrap/app.php`. Cover with `tests/Feature/Http/Middleware/EnsureAdminTest.php` (anonymous, non-admin, admin).
- [x] 4. **E-07** (`80323b8`). Add migration `2026_04_30_000002_add_ui_locale_and_is_active_to_users_table.php` introducing `users.ui_locale` (string, nullable) and `users.is_active` (boolean, default `true`). Add the casts on `User`. Enrich `App\Http\Resources\Auth\UserResource` with `languages[]`, `ui_locale`, `is_super`, `active`; preserve legacy `language` field. Extend `UserFactory` with `inactive()` and `withUiLocale()` helpers. Update `tests/Unit/Http/Resources/Auth/UserResourceTest.php` and `tests/Feature/Api/V1/Auth/MeTest.php` for the new shape.
- [x] 5. **P-03** (`eb3d3b5`). Add `App\Http\Middleware\EnsureSuperAdmin` (alias `super-admin`) — 401 if no bearer, 403 if not admin or `is_super = false`. Register the alias in `bootstrap/app.php`. Cover with `tests/Feature/Http/Middleware/EnsureSuperAdminTest.php` (anonymous, non-admin, admin-without-super, super-admin).
- [x] 6. **E-01 + E-09** (`4102740`). Mount `/api/v1/admin` route group under `auth:sanctum` + scoped admin middleware in `routes/api.php`. Implement admin user CRUD under `/api/v1/admin/users` (gated by `super-admin`):
  - `App\Domain\Admin\Users\Actions\{Create,Disable,Enable,SendAdminPasswordReset}AdminUserAction`.
  - `App\Domain\Admin\Users\DataTransferObjects\CreateAdminUserData`.
  - `App\Http\Controllers\Api\V1\Admin\Users\{Create,Disable,Enable,List,SendAdminPasswordReset}AdminUserController`.
  - `App\Http\Requests\Admin\Users\CreateAdminUserRequest`.
  - Routes: `GET /`, `POST /`, `PATCH /{user}/enable`, `PATCH /{user}/disable`, `POST /{user}/password-reset`.
  Cover with `tests/Feature/Api/V1/Admin/Users/AdminUsersTest.php` (auth, super gate, validation, create with random password, enable/disable lifecycle, disable revokes Sanctum tokens, password-reset email dispatched).
- [x] 7. **S-07 + S-08 + E-02 (Resources)** (`32bbd10`). Add migration `2026_04_30_000003_add_position_to_resource_categories_and_resources_tables.php` introducing `resource_categories.position` and `educational_resources.position` with composite indexes (`(language, position)` and `(resource_category_id, position)`); backfill by `id ASC` (per category for resources). Add `position` to the Eloquent models. Update `ListResourceCategoriesAction` to sort by `(position, id)` for admin reads. Implement `ReorderResourceCategoriesAction` and `ReorderEducationalResourcesAction` — full-array idempotent, transaction-wrapped, validate that the supplied IDs match the existing scope exactly. Add `App\Http\Requests\Admin\EducationalResources\ReorderRequest` (later promoted to shared in task 17). Add controllers `ReorderResourceCategoriesController` and `ReorderEducationalResourcesController` under `Api\V1\Admin\EducationalResources`. Routes: `POST /admin/resource-categories/reorder`, `POST /admin/resource-categories/{category}/resources/reorder`. Cover with `tests/Feature/Api/V1/Admin/EducationalResources/ReorderTest.php` (auth, validation 422 on mismatched IDs, happy-path order persisted, transaction rollback on failure).
- [x] 8. **P-01** (`abdf641`). Add `User::canManageLanguage(string $code): bool` (super-admins pass; otherwise checks per-user `languages[]` membership) and `User::canManageLanguageless(): bool` (super-only). Cover with `tests/Feature/Database/UserLanguageScopeTest.php` (super-admin passes both; admin-with-language passes only matching codes; admin-without-language fails both).
- [x] 9. **S-04** (`77e2b98`). Add migration `2026_04_30_000004_rename_news_image_path_to_image_url.php` renaming `news.image_path` → `news.image_url`. Update `App\Domain\News\Models\News`, `App\Http\Resources\News\NewsResource`, `database/factories/NewsFactory`, and the existing `tests/Unit/Domain/News/Models/NewsTest.php` + `tests/Unit/Http/Resources/News/NewsResourceTest.php` for the new column name. Resource still resolves the relative storage path to an absolute URL.
- [x] 10. **S-09** (`4854ffa`). Add migration `2026_04_30_000005_add_language_to_daily_verse_table.php` introducing `daily_verse.language` (char(2), nullable). Existing rows stay `NULL` (= "all languages") to preserve legacy behaviour. Update `App\Domain\Verses\Models\DailyVerse`, `DailyVerseResource`, and `DailyVerseFactory` to expose `language`.
- [x] 11. **C-01** (`3f50b58`). Add migration `2026_04_30_000006_normalize_users_roles.php` collapsing legacy `ROLE_EDITOR` into `admin` and deduplicating role arrays. Forward-only data normalization (no destructive `down()`). Cover with `tests/Feature/Database/NormalizeUsersRolesMigrationTest.php` (legacy users get `["admin"]`; existing admins unchanged; no-role users untouched).
- [x] 12. **E-03** (`44afedf`). Add `App\Http\Controllers\Api\V1\Admin\References\ValidateReferenceController` and `App\Http\Requests\Admin\References\ValidateReferenceRequest`. Endpoint `POST /admin/references/validate` mirrors existing reference parsing logic with no side effects. Cover with `tests/Feature/Api/V1/Admin/References/ValidateReferenceTest.php` (auth, valid reference, invalid reference 422, idempotency).
- [x] 13. **E-10** (`369d185`). Add PHPDoc on `ListOlympiadThemesController` and `ShowOlympiadThemeController` documenting the theme-aggregation contract (status, counts, scoring, traversal). Documentation-only; no test churn.
- [x] 14. **E-04 + backing** (`fde4266`). Add migration `2026_04_30_000007_create_import_jobs_table.php`, `App\Domain\Admin\Imports\Models\ImportJob`, `App\Domain\Admin\Imports\Enums\ImportJobStatus` (queued / running / completed / failed), `App\Http\Resources\Admin\Imports\ImportJobResource`, `database/factories/ImportJobFactory`, and `App\Http\Controllers\Api\V1\Admin\Imports\ShowImportJobController`. Route: `GET /admin/imports/{importJob}`. Cover with `tests/Feature/Api/V1/Admin/Imports/ShowImportJobTest.php` (auth, 404 for unknown job, payload shape, status enum values).
- [x] 15. **E-06** (`8430db5`). Add `App\Domain\Admin\Uploads\Actions\IssuePresignedUploadAction`, `App\Domain\Admin\Uploads\DataTransferObjects\{PresignedUploadRequest,PresignedUploadResult}`, `App\Http\Requests\Admin\Uploads\IssuePresignedUploadRequest`, and `App\Http\Controllers\Api\V1\Admin\Uploads\IssuePresignedUploadController`. Route: `POST /admin/uploads/presign`. Constrain content-type and max size on the presigned URL. Cover with `tests/Feature/Api/V1/Admin/Uploads/IssuePresignedUploadTest.php` (auth, validation per content-type/size, presigned URL shape).
- [x] 16. **C-03** (`b9bf5f6`). Add `App\Domain\Admin\Uploads\Jobs\DeleteUploadedObjectJob` and wire it from `EducationalResource`'s `deleting` model event so a hard delete schedules an S3 cleanup. Soft-deletes do **not** purge S3. Cover with `tests/Feature/Domain/EducationalResources/EducationalResourceDeletionCleanupTest.php` (hard delete dispatches the job; soft delete does not).
- [x] 17. **E-02 (Sabbath School)** (`7c0c60c`). Add `App\Domain\SabbathSchool\Actions\{ReorderLessonSegmentsAction,ReorderSegmentQuestionsAction}` and the matching controllers under `Api\V1\Admin\SabbathSchool`. Promote `App\Http\Requests\Admin\EducationalResources\ReorderRequest` to shared `App\Http\Requests\Admin\ReorderRequest` and update the two existing Resources controllers to use the new namespace. Routes: `POST /admin/sabbath-school/lessons/{lesson}/segments/reorder` and `POST /admin/sabbath-school/segments/{segment}/questions/reorder`. Cover with `tests/Feature/Api/V1/Admin/SabbathSchool/ReorderTest.php` (auth, validation, scope mismatch 422, happy path).
- [x] 18. **E-02 (Olympiad) + backing** (`2b81011`). Add migration `2026_04_30_000008_add_position_to_olympiad_questions_table.php` introducing `olympiad_questions.position` with composite index, backfilled by `id ASC` per `(book, chapters, language)` tuple. Add `App\Domain\Olympiad\Actions\ReorderOlympiadQuestionsAction` and `App\Http\Controllers\Api\V1\Admin\Olympiad\ReorderOlympiadQuestionsController`. Route: `POST /admin/olympiad/themes/{book}/{chapters}/{language}/questions/reorder`. Cover with `tests/Feature/Api/V1/Admin/Olympiad/ReorderOlympiadQuestionsTest.php` (auth, validation, scope mismatch 422, happy path).
- [x] 19. **Perf-02** (`a22179f`). Add `Cache-Control: public, max-age=…` on read-mostly public endpoints in `routes/api.php` (Bible catalog reads, daily-verse, public news listing, public Sabbath School / Olympiad / Educational Resources reads). HTTP-cache only — application-level Valkey caching lives in `MBA-021-public-read-caching`. No test churn (header presence is asserted by the existing endpoint tests via `BibleCacheHeaders` and per-route assertions where they exist).
- [x] 20. Update `apps/admin/.agent-workflow/stories/MB-013-api-db-improvements/improvements.md`: flip every shipped item's `Status` to `implemented`, set its `Tracking` field to the corresponding `apps/api` commit hash, append the dated entry under **Append log** summarising the branch.
- [x] 21. Run `make lint-fix` and `make stan` from `apps/api/`. Run `make test-api filter='Admin|Auth|EnsureAdmin|EnsureSuperAdmin|UserIsSuper|UserLanguages|UserLanguageScope|NormalizeUsersRoles|News|DailyVerse|EducationalResource|SabbathSchool|Olympiad|Imports|Uploads|References'` from the monorepo root. Confirm the full surface stays green before marking the story `in-review`.

## Risks

- **Out-of-band schema migration ordering.** Nine migrations land on the
  same calendar day (`2026_04_30_*`). Ordering between them is encoded
  in the filename suffix `000000…000008` — preserve that ordering on
  rebase. The role-normalization migration (`000006`) reads
  `users.roles`; if `S-10` (`000000`) ever moves later, the
  super-admin promotion still keys off `roles`, so they are independent
  but must both run before any code path consumes `is_super`.
- **`improvements.md` lives in the admin repo.** Status flips and
  Tracking fields require a commit in `apps/admin`, not `apps/api`.
  Track that as a dependent admin-side commit so the handoff backlog
  stays in sync — it's already done as of the last `improvements.md`
  read (entries reference the API commit hashes shipping in this
  branch).
- **`canManageLanguage()` not yet called.** The helpers ship without a
  consumer. That's intentional (the consuming controllers don't exist
  yet), but reviewers should not request that we wire them into
  controllers in this story — it's covered by future per-domain admin
  endpoints. Tests assert the helper behaviour directly.
- **Reorder full-array vs. delta.** Chosen full-array because it's
  idempotent. If reorder becomes a hot path under real admin usage,
  the next iteration is fractional indexing (item Perf-01). Don't
  preemptively optimise here.
- **Cache-Control without backend cache.** Adding `max-age` headers
  without an upstream Cloudflare / LB respecting them is a no-op for
  hot misses. Acceptable: this story ships HTTP-cache only; backend
  cache is `MBA-021`'s problem.
- **S3 cleanup job runs after hard delete.** If a soft-delete is later
  promoted to hard-delete via a separate path that bypasses the
  Eloquent `deleting` event, the S3 object is leaked. Mitigation: the
  test asserts the dispatch path; future cleanup paths must use the
  model API, not raw `DB::table()->delete()`.
