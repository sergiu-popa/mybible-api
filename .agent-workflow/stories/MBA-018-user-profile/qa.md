# QA: MBA-018 — User profile

**Commit reviewed:** `d6c3602` (branch `mba-018`)
**Review verdict consumed:** APPROVE (no Critical, no Warnings, 3 non-blocking
Suggestions).

## Verdict: QA PASSED

## Test run

- `make test` → 676 passed, 2069 assertions, 9.09s, 0 failures.
- `make test filter=Profile` → 56 passed, 161 assertions, 1.05s.
- `make lint` → PASS (500 files).
- `make stan` → PASS (480 files, 0 errors).

## Acceptance criteria → test coverage

| AC | Covered by |
|---|---|
| 1. `PATCH /api/v1/profile`, Sanctum, one-of required | `UpdateUserProfileTest::test_it_accepts_a_partial_payload`, `test_it_updates_every_supplied_field`, `test_it_fails_when_all_fields_are_null`, `test_it_requires_authentication` |
| 2. `language` validated via `Language` enum | `UpdateUserProfileTest::test_it_accepts_supported_language`, `test_it_rejects_unsupported_language`; Request unit `test_it_accepts_supported_language`, `test_it_rejects_unknown_language` |
| 3. `preferred_version` validated via `bible_versions` | `UpdateUserProfileTest::test_it_accepts_a_known_preferred_version`, `test_it_rejects_an_unknown_preferred_version` |
| 4. `200` + UserResource | `UpdateUserProfileTest::test_it_updates_every_supplied_field` asserts `assertOk()` + resource shape |
| 5. `POST /profile/change-password` body shape | `ChangeUserPasswordTest::test_happy_path_changes_hash_and_revokes_other_tokens` |
| 6. Wrong current password → 422 | `ChangeUserPasswordTest::test_it_returns_422_on_wrong_current_password`; Action unit `test_it_throws_on_wrong_current_password_without_any_state_change` |
| 7. `Password::defaults()` on new_password | `ChangeUserPasswordTest::test_it_returns_422_on_weak_new_password`; Request unit `test_it_rejects_a_weak_new_password`, `test_it_rejects_mismatched_confirmation` |
| 8. Other tokens revoked, current retained | `ChangeUserPasswordTest::test_happy_path_changes_hash_and_revokes_other_tokens`; Action unit `test_it_changes_the_password_and_revokes_other_tokens` |
| 9. `DELETE /profile` with `password` body | `DeleteUserAccountTest::test_happy_path_soft_deletes_the_user_and_revokes_all_tokens` |
| 10. Wrong password → 422 | `DeleteUserAccountTest::test_it_returns_422_on_wrong_password`; Action unit `test_it_throws_on_wrong_password_and_leaves_state_untouched` |
| 11. Soft-delete + revoke-all + event + 204 | `DeleteUserAccountTest::test_happy_path_...`; Action unit `test_it_soft_deletes_revokes_tokens_and_dispatches_event` |
| 12. Deleted user cannot log in | `DeleteUserAccountTest::test_a_deleted_user_cannot_log_in_with_the_generic_401` |
| 13. Avatar upload (mime, size, resource) | `UploadUserAvatarTest::test_it_uploads_an_avatar_and_returns_the_resource`, `test_it_rejects_files_over_5_mb`, `test_it_rejects_non_jpeg_png_types`, `test_it_replaces_the_existing_avatar_and_deletes_the_old_file`; Request unit `test_it_passes_with_a_jpeg_image`, `test_it_rejects_a_gif`, `test_it_rejects_files_larger_than_5_mb`, `test_it_fails_when_avatar_is_missing` |
| 14. `DELETE /profile/avatar` removes file + clears column | `RemoveUserAvatarTest::test_it_removes_the_file_and_clears_the_column`, `test_it_is_idempotent_when_no_avatar_is_set`; Action unit `test_it_deletes_the_file_and_clears_the_column`, `test_it_is_noop_when_the_column_is_already_null` |
| 15. `avatar_url` accessor via `Storage::disk('avatars')` | Covered implicitly in upload/remove feature tests (resource payload asserts `avatar_url` value) |
| 16. Feature tests complete | All five Profile feature test files present; all pass |
| 17. Unit tests per Action | `tests/Unit/Domain/User/Profile/Actions` contains 5 Action tests + 4 DTO tests; all pass |

## Edge cases probed

- Unauthenticated 401 for each endpoint (feature tests include
  `test_it_requires_authentication` and Request unit tests assert
  `authorize()` returns false for a guest).
- `new_password === current_password` rejected (422) — covered by
  `test_it_returns_422_when_new_password_equals_current`.
- Password confirmation mismatch rejected — Request unit
  `test_it_rejects_mismatched_confirmation`.
- Re-registration with a reused email after soft-delete — covered by
  `test_re_registration_with_the_same_email_is_allowed` (regression for the
  composite `(email, deleted_at)` unique index).
- Avatar replace atomicity — `test_it_replaces_the_existing_avatar_and_deletes_the_old_file`
  verifies the previous object is removed after the new write.
- Idempotent avatar removal when the column is already null — feature +
  action unit tests both cover.

## Regressions

- Full suite is green (676 passed). Related suites exercised include Auth,
  Reading Plans, Notes, Favorites — no unexpected churn.
- `AppServiceProvider` now registers `Password::defaults()` (min 8 + mixed
  case + numbers + symbols). `RegisterUserRequest` and `ResetPasswordRequest`
  already updated to use it; their feature/request tests pass.

## Suggestions from review (non-blocking, carried forward)

1. Extract a shared `clearAuthenticationHeader()` helper on
   `Tests\Concerns\InteractsWithAuthentication` once the copy-count hits 4.
2. Remove the `Schema::hasTable('bible_versions')` guard in
   `UpdateUserProfileRequest` now that MBA-007 has shipped.
3. `IncorrectCurrentPasswordException::forField` can be simplified to a
   single `parent::__construct()` call — optional.

None block release.

Status → `qa-passed`.
