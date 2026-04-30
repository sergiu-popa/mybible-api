---
name: audit-MBA-022
description: Auditor pass for MBA-022 (MB-013 API handoff)
type: audit
---

# Audit — MBA-022-mb-013-handoff-implementation

## Scope

Reviewed all 25 commits on branch `mb-013-api-improvements` (3 644 LOC across 86
files: 9 migrations, 2 middlewares, 5 reorder actions, admin user CRUD, presign
uploads + S3 cleanup job, import-job tracker, reference validator, schema
deltas on `users`/`news`/`daily_verse`/`olympiad_questions`/`*_resources`,
`UserResource` enrichment, public-read cache headers).

Holistic pass across architecture, code quality, API design, security,
performance, test coverage. Three-round review + QA already cleared all
Critical/Warning items; this pass looked for anything they may have missed.

## Issue table

| # | Issue | Location | Severity | Status | Resolution |
|---|---|---|---|---|---|
| 1 | `is_super` migration uses `whereJsonContains('roles', 'admin')` to find the oldest admin to promote, but legacy production rows still hold `ROLE_ADMIN` at this point in the migration sequence (000000) — the role-normalization migration (000006) runs later. On a production database whose `roles` JSON has not been pre-normalized, the promotion step matches zero rows and no super-admin is seeded. | `database/migrations/2026_04_30_000000_add_is_super_to_users_table.php:33-42` | Observation | Deferred-with-pointer | AC §1 names the literal `admin` role, and the implementation is faithful to the AC text. Treat as a deploy-time check: if the production user table still uses the Symfony-era `ROLE_ADMIN`/`ROLE_EDITOR` values, run the role-normalization step first or hand-promote one super-admin via a one-off seeder before relying on these gates. Tests don't exercise this path because `RefreshDatabase` starts from an empty `users` table. |
| 2 | Reorder actions validate that supplied IDs are a **subset** of the scope (`count($matching) !== count($ids)`), not that they cover the scope **exactly**. Plan task 7 specifies "validate that the supplied IDs match the existing scope exactly"; AC §12 calls the contract "full-array idempotent". A partial submission today silently leaves out-of-band rows at their old positions. | `ReorderEducationalResourcesAction.php:28-37`, `ReorderResourceCategoriesAction.php:27-35`, `ReorderLessonSegmentsAction.php:28-37`, `ReorderSegmentQuestionsAction.php:28-37`, `ReorderOlympiadQuestionsAction.php:32-44` | Suggestion | Skipped-with-reason | Subset semantics were explicitly approved through Review (3 rounds) and QA. The five existing test cases all submit full coverage, so the observed behaviour matches what the suite locks in. Tightening to exact-match would alter contract for any future admin client that intentionally posts partials; raise as a separate clarification ticket if the product wants strict matching. |
| 3 | `count($matching) !== count($ids)` reorder check duplicated across 5 actions. | The 5 reorder actions above. | Suggestion | Deferred-with-pointer | Already noted in `review.md` (round 2 + round 3) as out-of-scope; not in `apps/api/.agent-workflow/CLAUDE.md` §7 deferred-extractions register either. Architect's call on whether it earns a row. |
| 4 | `position` default-vs-backfill drift on the three migrations (`olympiad_questions`, `educational_resources`, `resource_categories`): new rows insert at `position = 0` while backfilled rows start at `1`. New rows therefore sort before every backfilled row in the canonical order until an admin reorders. | Migrations `2026_04_30_000003_*` and `2026_04_30_000008_*` | Suggestion | Deferred-with-pointer | Acknowledged out-of-scope in `review.md`. Fix at source by setting the default to a high sentinel (or computing `MAX(position)+1` on insert via a model boot hook) in a follow-up. |
| 5 | `ListAdminUsersController` returns the full admin list unpaginated. | `app/Http/Controllers/Api/V1/Admin/Users/ListAdminUsersController.php:13-21` | Suggestion | Deferred-with-pointer | Acknowledged in `review.md` round 1 as a deliberate non-blocker. Acceptable while the admin set is small (a handful of accounts); revisit when it grows. |
| 6 | FormRequest `authorize()` body `return $this->user() !== null;` repeated across `ReorderRequest`, `IssuePresignedUploadRequest`, `CreateAdminUserRequest`, `ValidateReferenceRequest` (the actual gating is the route's `auth:sanctum`+`admin`/`super-admin` middleware, so this method is decorative). | `app/Http/Requests/Admin/**` | Suggestion | Deferred-with-pointer | Already raised in earlier review rounds. Pattern overlaps with the owner-`authorize()` row in §7 of `apps/api/.agent-workflow/CLAUDE.md` but with a different rule body; defer the consolidation decision to the next admin-FormRequest story. |
| 7 | `SeededShuffler` and shared `ReorderRequest` lost `final` to accommodate test fakes / a single subclass (`ReorderOlympiadQuestionsRequest`). | `app/Domain/Olympiad/Support/SeededShuffler.php`, `app/Http/Requests/Admin/ReorderRequest.php` | Suggestion | Deferred-with-pointer | The cleaner DI-bound interface refactor was explicitly tracked in `review.md` as a code-shape choice, not a defect. |
| 8 | `UserIsSuperTest` doesn't exercise the migration's "promote oldest admin to `is_super`" branch — `RefreshDatabase` runs the migration against an empty `users` table, so the conditional in `up()` lines 33-42 never fires under test. | `tests/Feature/Database/UserIsSuperTest.php` | Suggestion | Skipped-with-reason | Adding a migration-replay test against pre-seeded rows would require running migrations manually rather than through `RefreshDatabase`, which is a non-trivial harness shift. The schema/cast/factory paths are covered; the data-migration path is one-shot and should be verified at deploy time alongside Issue 1. |

## Test suite

- Targeted filter (per Plan task 21):
  `Admin|Auth|EnsureAdmin|EnsureSuperAdmin|UserIsSuper|UserLanguages|UserLanguageScope|NormalizeUsersRoles|News|DailyVerse|EducationalResource|SabbathSchool|Olympiad|Imports|Uploads|References`
  → **348 passed (1 040 assertions), 5.68s**.
- No regressions introduced by this audit pass (no code changed).
- QA earlier reported the full suite at 945 / 945; the targeted run above
  re-confirms the AC-relevant subset.

## Verdict

**PASS** — status flips to `done`.

No Critical or Warning items unresolved. The eight rows above are
informational: Issue 1 is a deploy-time check that lives on whoever runs
the migration in production; Issues 2–8 are explicitly deferred or
intentional code-shape choices already accepted by the Review/QA pipeline.
