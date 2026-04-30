# QA — MBA-022-mb-013-handoff-implementation

## Summary

**All acceptance criteria verified. Full test suite passes with no regressions.**

### Test Execution

**Focused AC test suite:**
- Filter: `Admin|Auth|EnsureAdmin|EnsureSuperAdmin|UserIsSuper|UserLanguages|UserLanguageScope|NormalizeUsersRoles|News|DailyVerse|EducationalResource|SabbathSchool|Olympiad|Imports|Uploads|References`
- Result: **348 tests passed** (1040 assertions) — 5.49s
- Coverage: All new endpoints (admin users CRUD, reorder, imports, uploads, references, auth enrichment), all new middleware (EnsureAdmin, EnsureSuperAdmin), all schema deltas (languages, is_super, is_active, ui_locale, position columns), role normalization, and related feature regressions.

**Full test suite:**
- Result: **945 tests passed** (2828 assertions) — 14.43s
- No regressions in existing features.

### Acceptance Criteria Verification

All 26 acceptance criteria verified:

#### Schema (AC §1–9)
✅ `users.is_super` boolean added, cast on `User`, factory state `super()` works, oldest `admin` user promoted.
✅ `users.languages` JSON array added, 3-char→2-char backfill, legacy `language` column preserved, accessor works.
✅ `users.ui_locale` and `users.is_active` columns added, casts applied.
✅ `resource_categories.position` and `educational_resources.position` added with composite indexes, backfilled by id, public list still surfaces newest-first, position is admin concern.
✅ `news.image_path` → `news.image_url` rename, resource still resolves relative path to absolute URL.
✅ `daily_verse.language` (nullable, 2-char) added, existing rows stay NULL.
✅ `users.roles` normalized: legacy ROLE_EDITOR→admin, deduplicated, reversible migration (forward-only).
✅ `import_jobs` table created with all required fields (id, type, status, progress, payload, error, timestamps).
✅ `olympiad_questions.position` added with index, backfilled by id per theme tuple.

#### Endpoints (AC §10–18)
✅ `/api/v1/admin/*` prefix mounted under `auth:sanctum` + scoped admin middleware.
✅ `/api/v1/admin/users` CRUD endpoints:
  - `GET /` — list admins, `super-admin` gated
  - `POST /` — create admin with random bcrypt password, `super-admin` gated
  - `PATCH /{user}/enable`, `PATCH /{user}/disable` — disable revokes active Sanctum tokens, `super-admin` gated
  - `POST /{user}/password-reset` — sends reset email, `super-admin` gated
✅ Reorder endpoints (full-array idempotent, transaction-wrapped):
  - `POST /api/v1/admin/resource-categories/reorder` and `/admin/resource-categories/{category}/resources/reorder`
  - `POST /api/v1/admin/sabbath-school/lessons/{lesson}/segments/reorder` and `/admin/sabbath-school/segments/{segment}/questions/reorder`
  - `POST /api/v1/admin/olympiad/themes/{book}/{chapters}/{language}/questions/reorder`
✅ `GET /api/v1/auth/me` enriched with `languages[]`, `ui_locale`, `is_super`, `active`; legacy `language` field preserved.
✅ `POST /api/v1/admin/references/validate` — validation-only, no side effects.
✅ `GET /api/v1/admin/imports/{importJob}` — import-job polling endpoint, standardized response shape.
✅ `POST /api/v1/admin/uploads/presign` — presigned S3 URLs with content-type and size constraints.
✅ Olympiad public endpoints (`GET /api/v1/olympiad/themes`, `GET /api/v1/olympiad/themes/{book}/{chapters}/{language}`) honor `position` column for question ordering.
✅ Olympiad theme aggregation contract documented via PHPDoc.

#### Policies & Authorization (AC §19–21)
✅ `EnsureAdmin` middleware (alias `admin`): 401 without Sanctum bearer, 403 for non-admins (tests: anonymous, non-admin, admin all behave correctly).
✅ `EnsureSuperAdmin` middleware (alias `super-admin`): 401 without bearer, 403 for non-admins or admins with `is_super = false` (tests: anonymous, non-admin, admin-without-super, super-admin all behave correctly).
✅ `User::canManageLanguage(string $code): bool` and `User::canManageLanguageless(): bool` helpers added (tests: super-admins pass both; non-super admins gated by per-user `languages[]` set).

#### Performance (AC §22)
✅ `Cache-Control: public, max-age=...` response headers added on public read endpoints (HTTP-cache only; application-level caching is MBA-021).

#### Cleanup / Tech Debt (AC §23)
✅ `EducationalResource` hard deletion schedules `DeleteUploadedObjectJob` for S3 cleanup; soft deletes do not.

#### Tests (AC §24–26)
✅ Each new endpoint has feature tests covering auth, authorization, validation errors, happy path, edge cases (reorder with mismatched IDs returns 422, disable revokes tokens, `is_super=false` gets 403).
✅ Each schema delta has migration/model tests (UserIsSuperTest, UserLanguagesTest, UserLanguageScopeTest, NormalizeUsersRolesMigrationTest, NewsImageUrlTest, DailyVerseLanguageTest).
✅ Middleware tests cover four states: missing bearer (401), wrong role (403), correct role (200), and (for `EnsureSuperAdmin`) admin-without-super (403).

### Edge Cases & Regressions

✅ Olympiad reorder invalidates theme-scoped cache (verified in commit `8bd5cb0` review: `ReorderOlympiadQuestionsTest::test_reorder_invalidates_the_public_read_cache` primes cache, performs reorder, asserts cache is gone).
✅ Reorder validation rejects mismatched ID sets (returns 422).
✅ Admin token revocation works correctly (disable action revokes all active Sanctum tokens for the target user).
✅ Legacy `language` field still present on `GET /auth/me` for backward compatibility.
✅ S3 cleanup job only fires on hard delete, not soft delete.
✅ Factory helpers (`super()`, `inactive()`, `withUiLocale()`) work as expected.
✅ No regressions in existing feature tests (945/945 pass).

## Verdict

**QA PASSED** — All acceptance criteria verified, all tests passing, no regressions. The story is production-ready.

Status flips to `qa-passed`.
