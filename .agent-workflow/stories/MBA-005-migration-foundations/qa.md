# QA: MBA-005-migration-foundations

**Verdict:** QA PASSED
**Commit:** dc7c5a2

## Test run

- `make test`: **174 passed, 447 assertions, 2.68s**.
- Story-scoped filter (`ForgotPasswordTest|ResetPasswordTest|ResetPasswordActionTest|PasswordResetNotificationTest|test_it_logs_in_a_user_with_a_symfony_argon2id_hash`): **18 passed, 42 assertions**.
- No regressions in reading-plans / subscriptions / auth-login.

## Fresh-DB schema check

`php artisan migrate:fresh` on the test DB produced `users` with the 12 columns from plan's target schema in the expected order — `id, name, email, email_verified_at, password, remember_token, roles, language, avatar, last_login, created_at, updated_at`. The reconcile migration short-circuited (0.45ms) on the fresh path as designed.

## Acceptance criteria coverage

| AC | Evidence |
|---|---|
| 1. Rename `user` → `users` | Reconcile migration `Schema::rename('user','users')`; fresh-path short-circuit confirmed on `migrate:fresh`. |
| 2. Preserve email/password/roles/language/avatar/last_login | Column listing above; `UserFactory` sets all four new defaults; `Auth\RegisterUserAction` now writes `roles: []`. |
| 3. Drop legacy `salt` | Reconcile migration `dropColumn(['salt','resetToken','resetDate'])`. |
| 4. Add `email_verified_at`, `remember_token`, `created_at`/`updated_at` | Column listing; `UserFactory::definition` sets `email_verified_at`/`remember_token`. |
| 5. `User` model `$fillable`/`$hidden`/`casts()` with `roles → array` | `app/Models/User.php`; exercised implicitly by every auth test creating users. |
| 6. `config/hashing.php` `argon2id` with `memory=65536,time=4,threads=1` | File present; prod defaults in env fallbacks; `phpunit.xml` overrides only the cost (not the driver). |
| 7. Symfony-hashed user can log in | `LoginUserTest::test_it_logs_in_a_user_with_a_symfony_argon2id_hash` passing (pre-baked `$argon2id$v=19$m=65536,t=4,p=1$…` literal). |
| 8. New registrations hash with Argon2id | `RegisterUserTest` suite green; `hashed` cast + default driver argon2id. |
| 9. `password_reset_tokens` table via Laravel standard schema | Created by initial migration; `ResetPasswordTest` happy-path reads/writes through `PasswordBroker`. |
| 10. Drop `resetToken` / `resetDate` | Reconcile migration. |
| 11. `POST /api/v1/auth/forgot-password` + `reset-password` endpoints | `ForgotPasswordTest` (known + unknown + 422 paths); `ResetPasswordTest` (happy + tampered + expired + short + mismatch + missing). |
| 12. `.agent-workflow/CLAUDE.md` auth line updated | Verified in commit diff and current file. |

## Edge cases probed

- Unknown-email forgot-password returns 200 without dispatching (`Notification::assertNothingSent()`). ✓
- Tampered reset token returns 422 via `InvalidPasswordResetTokenException` renderer. ✓
- Expired token path uses `Carbon::setTestNow()` beyond `auth.passwords.users.expire`. ✓
- `ResetPasswordActionTest` stubs the `PasswordBrokerFactory` and asserts the exception for every non-`PASSWORD_RESET` broker status (`INVALID_USER`, `INVALID_TOKEN`, `RESET_THROTTLED`). ✓
- Validation: missing email, malformed email, short password (<8), mismatched confirmation — all 422. ✓

## Review follow-through

Review left zero Critical/Warning items. All six Suggestions are cosmetic or cross-story and do not block QA.

## Regressions

None. The only code outside the new auth/migration surface is `create_reading_plan_subscriptions_table` switching `foreignId('user_id')` → `unsignedInteger('user_id')` + explicit `foreign(...)`; reading-plans feature tests (subscribe / complete-day / reschedule / finish / abandon) all still pass.
