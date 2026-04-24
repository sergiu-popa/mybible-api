# Audit: MBA-018 — User profile

**Commit audited:** `289df9e` (branch `mba-018`)
**Prior verdicts consumed:** Review APPROVE (3 non-blocking suggestions);
QA PASSED (676 tests, 2069 assertions).

## Verdict: PASS

All Critical / Warning items are resolved. Every carried-forward Suggestion is
accounted for. Final gate is green.

## Dimension scoreboard

| Dimension | Notes |
|---|---|
| Architecture compliance | Matches plan: domain sub-tree under `App\Domain\User\Profile\*`, invokable controllers under `App\Http\Controllers\Api\V1\Profile`, `avatars` filesystem disk abstraction, event-before-soft-delete ordering, primitive-only `UserAccountDeleted` payload. |
| Code quality | Strict types everywhere, `final` classes, `readonly` DTOs, `#[\SensitiveParameter]` on password/password inputs, named routes under `profile.*`, shared `Password::defaults()` policy centralised in `AppServiceProvider::boot()`. |
| API Design | Verbs/statuses consistent: `PATCH` profile, `DELETE` profile (204), `POST` change-password (200), `POST`/`DELETE` avatar (200). JSON error envelope preserved via `ValidationException` inheritance. All routes gated by `auth:sanctum`. |
| Security | `Hash::check` before every mutation; change-password revokes all *other* Sanctum tokens; delete-account revokes *all* Sanctum tokens; soft-deleted row excluded from `Rule::unique` on re-registration; composite `(email, deleted_at)` unique index permits re-registration after soft-delete. |
| Performance | Avatar replace ordering writes the new object before deleting the previous (atomic from the caller's perspective on S3 failure). `Schema::hasTable` guard removed from `UpdateUserProfileRequest` — eliminates an `information_schema` hit on every profile update. |
| Test coverage | 56 Profile tests / 161 assertions covering happy/sad/auth paths, replace-flow, soft-delete + token revocation, event dispatch, re-registration after delete. |

## Issues

| # | Issue | Location | Severity | Status | Resolution |
|---|---|---|---|---|---|
| 1 | `Schema::hasTable('bible_versions')` guard is dead weight; MBA-007 has shipped and the plan prescribed removing this guard once that happened. Each profile update incurred two `information_schema` queries (once in `rules()`, once in `messages()`). | `app/Http/Requests/Profile/UpdateUserProfileRequest.php:30–45` (pre-fix) | Warning | Fixed | Removed the `array_merge` + `hasTable` check, the conditional `messages()` override, and the now-unused `Illuminate\Support\Facades\Schema` import. `preferred_version` now validates via a plain `Rule::exists('bible_versions', 'abbreviation')`. Feature coverage in `UpdateUserProfileTest::test_it_accepts_a_known_preferred_version` / `test_it_rejects_an_unknown_preferred_version` still passes. |
| 2 | `IncorrectCurrentPasswordException::forField` reconstructed itself from the parent's factory output. `parent::withMessages()` uses `new static(...)` internally, so late static binding already produces the subclass — the extra `new self(...)` was redundant. | `app/Domain/User/Profile/Exceptions/IncorrectCurrentPasswordException.php:22-29` | Suggestion | Fixed | Simplified `forField()` to return `parent::withMessages([...])` directly. Added a PHPDoc note explaining LSB behaviour. Unit tests (`ChangeUserPasswordActionTest`, `DeleteUserAccountActionTest`) and feature tests still assert `assertJsonValidationErrors(['current_password'])` / `['password']`. |
| 3 | `defaultHeaders` Authorization-flush duplicated across Profile feature tests. | `tests/Feature/Api/V1/Profile/ChangeUserPasswordTest.php:46`; `tests/Feature/Api/V1/Profile/DeleteUserAccountTest.php:70,91` | Suggestion | Deferred | Copy-count 3 of 4 per Review's extraction threshold — extract a `clearAuthenticationHeader()` helper on `Tests\Concerns\InteractsWithAuthentication` on the next copy. Not a §7 tripwire entry (testing concern). |
| 4 | Multi-step Actions are not wrapped in a DB transaction. `DeleteUserAccountAction` revokes tokens → dispatches event → soft-deletes; `ChangeUserPasswordAction` saves password → deletes other tokens. If a middle step fails, state is partially mutated. | `app/Domain/User/Profile/Actions/DeleteUserAccountAction.php:21-28`; `app/Domain/User/Profile/Actions/ChangeUserPasswordAction.php:26-34` | Suggestion | Skipped | No precedent in the codebase — existing lifecycle Actions (Reading Plans, Favorites, Notes) also run without `DB::transaction`. Token-revocation is idempotent (deleted rows stay deleted) and the event carries primitives, so a retry of the user-facing request is safe. Event-before-mutation ordering is an explicit plan decision. Revisit when the codebase introduces a shared transactional-action pattern. |
| 5 | `RemoveUserAvatarAction` calls `$disk->exists($path)` before `$disk->delete($path)`, doubling the S3 round-trip on the common case. Flysystem's `delete()` is a no-op if the object is absent. | `app/Domain/User/Profile/Actions/RemoveUserAvatarAction.php:21-23` | Suggestion | Skipped | Defensive check mirrors the replace-path in `UploadUserAvatarAction` and matches the "idempotent when no file on disk" unit test's assertion. Network-round-trip cost is marginal vs. the image-upload path; optimise when an explicit performance budget justifies it. |

## Final gate

- `make lint` — PASS (500 files).
- `make stan` — PASS (480 files, 0 errors).
- `make test filter=Profile` — PASS (56 tests, 161 assertions).
- `make test` — PASS (676 tests, 2069 assertions, 11.40s).

Status → `done`.
