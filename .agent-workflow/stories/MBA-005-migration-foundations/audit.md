# Audit: MBA-005-migration-foundations

**Verdict:** PASS
**Audited at:** dc7c5a2 (+ audit fixes)

## Dimensions covered

Architecture compliance, code quality, API design, security, performance, test coverage. Livewire dimension → N/A per `.agent-workflow/CLAUDE.md`.

## Issues

| # | Issue | Location | Severity | Status | Resolution |
|---|---|---|---|---|---|
| 1 | Published `config/auth.php` retained the default `web` session guard and set it as `defaults.guard`. Violates CLAUDE.md Code-Reviewer rule #4 (unused stateful guards/drivers in published config must be trimmed) for this API-only project. Review missed this. | `config/auth.php:18-45` | Warning | Fixed | Removed the `web` session guard; left a minimal `sanctum` entry (Sanctum's service provider fills the driver, confirmed in `vendor/laravel/sanctum/src/SanctumServiceProvider.php:23-28`). Set `defaults.guard` default to `sanctum`. Verified no `auth()`/`Auth::`/`->guard(` call sites outside the explicit `auth:sanctum` middleware. |
| 2 | `expireMinutes` rebuilds a config path via `Arr::get(config('auth.defaults'), 'passwords')` — unnecessarily indirect. | `app/Domain/Auth/Notifications/PasswordResetNotification.php:35-38` | Suggestion | Fixed | Replaced with `config('auth.defaults.passwords')` + string interpolation; dropped the `Arr` import. |
| 3 | Justification comment claimed the uniform response prevents enumeration "via response shape or timing difference"; the timing claim is overstated (known emails still perform a DB insert, unknown ones short-circuit). | `app/Domain/Auth/Actions/RequestPasswordResetAction.php:12-17` | Suggestion | Fixed | Rewrote the comment to describe the residual timing channel as accepted rather than denied. |
| 4 | `password_reset_url` default pointed at the API host (`http://api.mybible.local/reset-password`), which hosts no reset UI — obscures that this key must be overridden per-env. | `config/auth.php:128` | Suggestion | Fixed | Changed default to `http://localhost/reset-password`. |
| 5 | `PasswordResetNotification::via()` / `toMail()` narrow param type to `User` rather than the framework's `object`/`mixed`. | `app/Domain/Auth/Notifications/PasswordResetNotification.php:26,31` | Suggestion | Skipped | All call sites notify `User` instances today; tightening the signature is a deliberate typing choice. Revisit if the notification is ever dispatched via `Notification::route()` (anonymous notifiable). |
| 6 | Email copy hardcoded in English despite the preserved `users.language` column. | `app/Domain/Auth/Notifications/PasswordResetNotification.php:41-46` | Suggestion | Deferred | Out of scope here; tracked for MBA-018 (profile / per-user locale for transactional mail) per Review. |
| 7 | Reconcile-path `last_login` / `created_at` / `updated_at` inherit DATETIME from Symfony; fresh-path creates TIMESTAMP. | `database/migrations/2026_04_22_100000_reconcile_symfony_user_table.php:23-30` | Suggestion | Deferred | Functionally equivalent for Laravel casts today. Flag for any future migration using `timestamp(...)->change()` against the shared DB. Noted in Review. |
| 8 | No dedicated unit tests for `RequestPasswordResetData` / `ResetPasswordData` (existing convention has them for `LoginUserData`, `RegisterUserData`). | `app/Domain/Auth/DataTransferObjects/{RequestPasswordResetData,ResetPasswordData}.php` | Suggestion | Skipped | Both DTOs are trivial shape constructors with no derived state; feature tests (`ForgotPasswordTest`, `ResetPasswordTest`) plus `ResetPasswordActionTest` exercise both `::from()` paths end-to-end. Adding DTO-only tests would be round-trip coverage with no new branches. |

No Critical findings. All Warning/Suggestion items resolved, skipped with reason, or deferred with a pointer.

## Checks run

- `make lint-fix` — 155 files, PASS.
- `make stan` — 136 files, no errors.
- `make test` — **174 passed, 447 assertions, 2.44s**. No regressions.

## Verdict rationale

No Critical issues. One Warning (unused session guard in published config) fixed; three Review suggestions applied; three deferred or skipped with justification. Tests/lint/stan clean. Status → `done`.
