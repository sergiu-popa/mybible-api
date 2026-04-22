# Plan: MBA-005-migration-foundations

## Overview
Reconcile Laravel's `users` table with the Symfony `user` schema, switch the default hasher to Argon2id so existing Symfony passwords verify without rehash, and wire Laravel's standard password-reset broker behind two new `/api/v1/auth` endpoints. No new domain tables beyond `password_reset_tokens`.

## Open-question resolutions
- **Table rename direction:** take the rename path (`user` → `users`, camelCase columns → snake_case). Ownership of the shared DB transfers to Laravel at cutover (MBA-020); Symfony is stopped before this migration runs in prod.
- **Column-rename window:** column renames ship in this story's migrations but only *run* against prod inside the MBA-020 cutover window. Dev/CI run them freely via `RefreshDatabase`.
- **Existing Laravel-only columns (`remember_token`, `email_verified_at`):** Symfony schema does not have them; this story adds both via the reconciliation migration.
- **In-flight Symfony password resets:** accepted as invalidated at cutover (`resetToken` / `resetDate` dropped). No dual-read window.

## Migration strategy
The Laravel initial migration (`0001_01_01_000000_create_users_table.php`) already creates `users` + `password_reset_tokens` + `sessions`. We are about to point Laravel at the shared prod DB where `user` (Symfony) exists but `users` does not. Two migrations, designed so one ordered `php artisan migrate` reaches the same terminal schema on both a fresh CI DB and the prod DB:

- **Initial migration (rewritten):** short-circuits (`return;`) when `Schema::hasTable('user')` is true — prod path is handled by the reconciliation migration below. On a fresh DB (no `user` table) it creates the final target schema directly. Also drop the `sessions` table creation from this file — the API is stateless JSON and has no session guard.
- **Reconciliation migration (new):** short-circuits when `Schema::hasTable('user')` is false (CI already has the final shape). Otherwise: rename `user` → `users`, rename camelCase columns, drop legacy columns, add Laravel columns.

Both paths converge on the same `users` schema.

## Target `users` schema

| Column | Type | Null | Source |
|---|---|---|---|
| id | unsigned int auto-increment PK | no | preserve Symfony `int` width — existing FKs elsewhere reference it |
| name | string(50) | no | existing |
| email | string(180) unique | no | existing (Symfony length 180) |
| email_verified_at | datetime | yes | **new** |
| password | string(255) | no | existing (Argon2id hash fits) |
| remember_token | string(100) | yes | **new** |
| roles | json | no | existing, default `[]` |
| language | string(3) | yes | existing |
| avatar | string(255) | yes | existing |
| last_login | datetime | yes | renamed from `lastLogin` |
| created_at | datetime | yes | renamed from `createdAt` |
| updated_at | datetime | yes | **new** |

Dropped: `salt`, `resetToken`, `resetDate` (with their unique index on `resetToken`).

## Model changes — `App\Models\User`
- Drop the `#[Fillable]` / `#[Hidden]` PHP attributes; use `protected $fillable`, `protected $hidden`, `protected function casts()` in one place so the expanded lists stay readable.
- `$fillable`: name, email, password, roles, language, avatar, last_login.
- `$hidden`: password, remember_token.
- `casts()`: email_verified_at → `datetime`, password → `hashed`, roles → `array`, last_login → `datetime`.
- Override `sendPasswordResetNotification(string $token): void` to dispatch `App\Domain\Auth\Notifications\PasswordResetNotification`.

## Hashing
Publish `config/hashing.php` with driver `argon2id` and `argon` options `memory=65536`, `time=4`, `threads=1` — matches the Symfony argon2id sample hash format (`$argon2id$v=19$m=65536,t=4,p=1$…`). Laravel's `Argon2IdHasher::check` accepts any `$argon2id$` hash; no per-user rehash cycle is needed.

## Domain — `App\Domain\Auth`

### DTOs
- `RequestPasswordResetData(string $email)` — built via `::from($validated)`, sibling to `LoginUserData`.
- `ResetPasswordData(string $email, string $token, string $password)` — built via `::from($validated)`.

### Actions
- `RequestPasswordResetAction::execute(RequestPasswordResetData $data): void` — delegates to `Password::broker()->sendResetLink(['email' => $data->email])`. Swallows the broker status so responses are uniform (no user-enumeration leak). Comment the *why* of the uniform response.
- `ResetPasswordAction::execute(ResetPasswordData $data): void` — delegates to `Password::broker()->reset(...)` with a closure that assigns `$user->password = $plain` (the `hashed` cast does the hashing) and saves. Throws `InvalidPasswordResetTokenException` on any non-`PASSWORD_RESET` status.

### Exceptions
- `InvalidPasswordResetTokenException extends \RuntimeException` — rendered as 422 via `bootstrap/app.php`.

### Notifications
- `PasswordResetNotification` — queued, uses `MailMessage`, delivers the raw token (mobile-friendly) plus a reset URL derived from a new `config('auth.password_reset_url')` value. Purpose: API has no web route, so Laravel's default `ResetPassword` notification's `route('password.reset', …)` call would fail.
- New config key: add `'password_reset_url'` under `config/auth.php` (e.g. `env('AUTH_PASSWORD_RESET_URL')`). Plan only names the key — Engineer writes the default.

## HTTP layer

### Requests
- `RequestPasswordResetRequest` — `email: required|string|email`. `authorize(): true`. `toData(): RequestPasswordResetData`.
- `ResetPasswordRequest` — `email: required|string|email`, `token: required|string`, `password: required|string|min:8|confirmed`. `authorize(): true`. `toData(): ResetPasswordData`.

### Controllers (invokable)
- `RequestPasswordResetController` — `__invoke(RequestPasswordResetRequest, RequestPasswordResetAction)` → `response()->json(['message' => __('passwords.sent')])` always 200.
- `ResetPasswordController` — `__invoke(ResetPasswordRequest, ResetPasswordAction)` → `response()->json(['message' => __('passwords.reset')])` 200.

### Routes (`routes/api.php`, existing `v1/auth` group)
| Method | Path | Controller | Name | Auth |
|---|---|---|---|---|
| POST | `/api/v1/auth/forgot-password` | `RequestPasswordResetController` | `auth.forgot-password` | none |
| POST | `/api/v1/auth/reset-password` | `ResetPasswordController` | `auth.reset-password` | none |

### Exception render
Register a renderer in `bootstrap/app.php` that maps `InvalidPasswordResetTokenException` → 422 JSON `{ "message": "…" }`.

## Testing

### Feature tests
- `ForgotPasswordTest`: (a) returns 200 + dispatches `PasswordResetNotification` to a known user (use `Notification::fake`); (b) returns 200 without dispatching anything for an unknown email (no enumeration); (c) 422 on missing/invalid email.
- `ResetPasswordTest`: (a) happy path updates the hash and lets the user log in with the new password; (b) 422 on tampered token; (c) 422 on expired token (advance `Carbon::setTestNow` past `config('auth.passwords.users.expire')`); (d) 422 on password shorter than 8 / confirmation mismatch.
- Extend `LoginUserTest`: add `test_it_logs_in_a_user_with_a_symfony_argon2id_hash` — seed a user row with a hard-coded pre-baked `$argon2id$v=19$m=65536,t=4,p=1$…` hash and log in with the matching plaintext.

### Unit tests (only where feature tests don't cover)
- `PasswordResetNotificationTest` — asserts `toMail()` builds the configured URL (feature tests fake the notification and assert class + recipient, not mail body).
- `ResetPasswordActionTest` — asserts `InvalidPasswordResetTokenException` is thrown for every non-success broker status (branch coverage the feature test doesn't enumerate).

### Factory
- `UserFactory`: add `roles` → `[]`, `language` → `null`, `avatar` → `null`, `last_login` → `null`. Leave `email_verified_at` as-is. Confirms new columns round-trip.

## Documentation
Update `.agent-workflow/CLAUDE.md`: replace the `Auth: none yet (Sanctum/Passport TBD — do not scaffold auth until decided)` line with `Auth: Laravel Sanctum bearer tokens + \`api-key-or-sanctum\` middleware for dual-auth public routes. Hash driver: Argon2id (memory 65536, time 4, threads 1).`

## Tasks

- [x] 1. Publish `config/hashing.php` with `driver=argon2id` and argon `memory=65536`, `time=4`, `threads=1`.
- [x] 2. Rewrite `0001_01_01_000000_create_users_table.php`: early-return when `Schema::hasTable('user')`; otherwise create the target `users` schema (columns in the table above, `name` length 50, `email` length 180, `id` as `$table->increments('id')`). Also early-return around `password_reset_tokens` when already present. Remove the `sessions` table block entirely.
- [x] 3. Add migration `reconcile_symfony_user_table.php` that early-returns if `user` does not exist; otherwise renames `user` → `users`, renames `lastLogin` → `last_login` and `createdAt` → `created_at`, drops `salt`/`resetToken`/`resetDate` (and the `resetToken` unique index), adds `email_verified_at`, `remember_token`, `updated_at`. Down path reverses.
- [x] 4. Update `App\Models\User`: remove `#[Fillable]`/`#[Hidden]` attributes, declare `$fillable`, `$hidden`, and `casts()` per the Model changes section; override `sendPasswordResetNotification()`.
- [x] 5. Update `UserFactory` to set `roles`, `language`, `avatar`, `last_login` defaults.
- [x] 6. Add `App\Domain\Auth\Notifications\PasswordResetNotification` with a queued mailable that surfaces the raw token and a URL built from `config('auth.password_reset_url')`.
- [x] 7. Add `'password_reset_url'` key (env-backed) under `config/auth.php`.
- [x] 8. Add `App\Domain\Auth\DataTransferObjects\RequestPasswordResetData` with `::from()` constructor, mirroring `LoginUserData`.
- [x] 9. Add `App\Domain\Auth\DataTransferObjects\ResetPasswordData` with `::from()` constructor.
- [x] 10. Add `App\Domain\Auth\Exceptions\InvalidPasswordResetTokenException`.
- [x] 11. Add `App\Domain\Auth\Actions\RequestPasswordResetAction` using `Password::broker()->sendResetLink()` and swallowing the status (comment the enumeration-leak reason).
- [x] 12. Add `App\Domain\Auth\Actions\ResetPasswordAction` using `Password::broker()->reset()`, throwing `InvalidPasswordResetTokenException` on non-`PASSWORD_RESET` status.
- [x] 13. Add `App\Http\Requests\Auth\RequestPasswordResetRequest` with rules per the Requests section and `toData()`.
- [x] 14. Add `App\Http\Requests\Auth\ResetPasswordRequest` with rules per the Requests section and `toData()`.
- [x] 15. Add `App\Http\Controllers\Api\V1\Auth\RequestPasswordResetController` (invokable) returning a static success JSON.
- [x] 16. Add `App\Http\Controllers\Api\V1\Auth\ResetPasswordController` (invokable) returning a static success JSON.
- [x] 17. Register `POST /api/v1/auth/forgot-password` (name `auth.forgot-password`) and `POST /api/v1/auth/reset-password` (name `auth.reset-password`) inside the existing `v1/auth` group in `routes/api.php`.
- [x] 18. Register an `InvalidPasswordResetTokenException` → 422 renderer in `bootstrap/app.php` next to the existing domain renderers.
- [x] 19. Add feature test `tests/Feature/Api/V1/Auth/ForgotPasswordTest` covering known-email, unknown-email (no-enumeration), and validation failure paths with `Notification::fake()`.
- [x] 20. Add feature test `tests/Feature/Api/V1/Auth/ResetPasswordTest` covering happy path, tampered token, expired token, and validation failure.
- [x] 21. Add unit test `tests/Unit/Domain/Auth/Notifications/PasswordResetNotificationTest` asserting the mail URL/body composition.
- [x] 22. Add unit test `tests/Unit/Domain/Auth/Actions/ResetPasswordActionTest` asserting `InvalidPasswordResetTokenException` for each non-success broker status.
- [x] 23. Add `test_it_logs_in_a_user_with_a_symfony_argon2id_hash` to `tests/Feature/Api/V1/Auth/LoginUserTest` using a pre-baked `$argon2id$v=19$m=65536,t=4,p=1$…` hash literal.
- [x] 24. Update `.agent-workflow/CLAUDE.md` auth line to reflect Sanctum + `api-key-or-sanctum` + Argon2id.
- [x] 25. Run `make lint-fix && make stan && make test` and fix any fallout.

## Risks
- **Shared-DB coordination.** In prod, migrations must only run after Symfony is stopped. The reconciliation migration's rename of `user` → `users` breaks any running Symfony process immediately. Deploy sequence is owned by MBA-020 — flag in release notes.
- **`name` length divergence.** CI/dev databases previously built under the old initial migration used `name` as string(255). Anyone on a stale branch should `migrate:fresh` after pulling; column-width narrowing otherwise fails.
- **Argon2id cost on constrained CI.** 64 MB per hash is heavy. If CI runtime degrades, override via `HASH_ARGON_MEMORY` env in `phpunit.xml` rather than hard-coding a smaller value in `config/hashing.php` (stays faithful to prod).
- **`email` length change (255 → 180).** If any dev DB had an email longer than 180 chars seeded locally, the narrowing would fail. Unlikely given `UserFactory` uses `safeEmail()`; call out in migration comment.

## Story status
Set status to `planned` after this plan lands.
