# Plan: MBA-018-user-profile

## Approach

Introduce a `UserProfile` domain sub-tree under `App\Domain\User\Profile\*` that wraps the existing `App\Models\User` (owned by MBA-005). Six endpoints mount under `PATCH|DELETE /api/v1/profile`, `POST /api/v1/profile/change-password`, `POST|DELETE /api/v1/profile/avatar` — all behind `auth:sanctum`. Each endpoint follows the established pattern: FormRequest → DTO → Action → UserResource. Avatar persistence uses a dedicated `avatars` filesystem disk (a thin alias over the `s3` driver) so controllers and Resources stay disk-name-agnostic and `public/local` can substitute in tests via `Storage::fake('avatars')`. Soft-delete is added to `User`; the `users.email` unique index is recut as a composite `(email, deleted_at)` so a soft-deleted account does not block re-registration.

## Open questions — resolutions

1. **Email change.** Out of scope. Symfony's `updateProfile` does not touch email (email change in Symfony goes through a separate confirmation flow that is not part of this port). A follow-up story owns re-verification.
2. **Deletion grace period.** Soft-delete only in this story; the cascade/hard-delete job is deferred to the follow-up that owns data retention (config key `profile.deletion.grace_period_days` with default `30` will be introduced there, not now). The `UserAccountDeleted` event is the hand-off point.
3. **Avatar image processing.** Drop per story recommendation. Store the original upload; clients request a CDN-resized variant via query param if they need thumbnails.
4. **`preferred_version` column.** MBA-007 (bible_versions table) has not shipped yet. `preferred_version` validation is gated behind a `Rule::exists('bible_versions', 'abbreviation')` that only activates when the table exists. Until then, validation rejects the field with a clear "not yet available" message. See Risks.
5. **Supported-languages source.** Reuse the existing `App\Domain\Shared\Enums\Language` enum (cases `en`, `ro`, `hu`). No new config key. `ProfileUpdateRequest` validates against `Rule::enum(Language::class)`.
6. **Avatar disk.** Add an `avatars` disk in `config/filesystems.php` that proxies to the configured S3 bucket in production and to `public` in local/testing, so the Symfony files on S3 resolve under `Storage::disk('avatars')->url(...)` without hard-coding `s3`.

## Domain layout

```
app/Domain/User/Profile/
├── Actions/
│   ├── UpdateUserProfileAction.php
│   ├── ChangeUserPasswordAction.php
│   ├── DeleteUserAccountAction.php
│   ├── UploadUserAvatarAction.php
│   └── RemoveUserAvatarAction.php
├── DataTransferObjects/
│   ├── UpdateUserProfileData.php
│   ├── ChangeUserPasswordData.php
│   ├── DeleteUserAccountData.php
│   └── UploadUserAvatarData.php
├── Events/
│   └── UserAccountDeleted.php
└── Exceptions/
    └── IncorrectCurrentPasswordException.php

app/Http/Controllers/Api/V1/Profile/
├── UpdateUserProfileController.php
├── ChangeUserPasswordController.php
├── DeleteUserAccountController.php
├── UploadUserAvatarController.php
└── RemoveUserAvatarController.php

app/Http/Requests/Profile/
├── UpdateUserProfileRequest.php
├── ChangeUserPasswordRequest.php
├── DeleteUserAccountRequest.php
└── UploadUserAvatarRequest.php
```

The existing `App\Http\Resources\Auth\UserResource` is extended (see Key types); it stays in `Auth/` because `Auth/MeController` and `Auth/RegisterController` already depend on it.

## Key types

| Type | Role |
|---|---|
| `App\Models\User` (modify) | Add `SoftDeletes`. Add `avatar_url` accessor that returns `null` when `avatar` is null, otherwise `Storage::disk('avatars')->url($this->avatar)`. Add `preferred_version` to `$fillable`. Extend `$casts` if the DB column is nullable text. |
| `UpdateUserProfileData` (readonly) | `?string $name`, `?Language $language`, `?string $preferredVersion`. `from(array)` factory matching the established pattern. At least one field non-null (enforced in the Request, not the DTO). |
| `ChangeUserPasswordData` (readonly) | `#[SensitiveParameter] string $currentPassword`, `#[SensitiveParameter] string $newPassword`. |
| `DeleteUserAccountData` (readonly) | `#[SensitiveParameter] string $password`. |
| `UploadUserAvatarData` (readonly) | `\Illuminate\Http\UploadedFile $file`. DTO carries the file so the Action stays framework-light; validation (size/mime) already ran in the Request. |
| `UpdateUserProfileAction` | `execute(User $user, UpdateUserProfileData $data): User` — fills and saves non-null fields; returns the refreshed user. |
| `ChangeUserPasswordAction` | `execute(User $user, ChangeUserPasswordData $data, PersonalAccessToken $currentToken): void` — `Hash::check` the current password (throws `IncorrectCurrentPasswordException` on mismatch), sets `password = $new` (hashed cast persists), then deletes all tokens whose id != `$currentToken->id`. |
| `DeleteUserAccountAction` | `execute(User $user, DeleteUserAccountData $data): void` — verify password, revoke ALL tokens, dispatch `UserAccountDeleted`, then `$user->delete()` (soft). Event dispatch runs before the soft-delete so the listener still sees a loaded user. |
| `UploadUserAvatarAction` | `execute(User $user, UploadUserAvatarData $data): User` — stores under `avatars/{user_id}/{ulid}.{ext}` on the `avatars` disk, deletes the previous file if the column was populated, persists the new relative path. |
| `RemoveUserAvatarAction` | `execute(User $user): User` — deletes the file on the `avatars` disk (only if present), nulls the column. |
| `UserAccountDeleted` (event) | `public readonly int $userId; public readonly string $email;` — primitives only, no serialized Eloquent model, so a queued cascade listener can run long after the soft-delete row is purged. |
| `IncorrectCurrentPasswordException` | Extends `\Illuminate\Validation\ValidationException` with a factory that attaches the error to the `current_password` (or `password` for the delete path) field so the standard 422 renderer in `bootstrap/app.php` ships the right JSON envelope — no new exception handler registration needed. |
| `UserResource` (modify) | Add `language`, `preferred_version`, `avatar_url` keys. Current `{id, name, email, created_at}` shape is preserved; additive only. |

## HTTP endpoints

| Method | Path | Controller | Request | Resource | Auth |
|---|---|---|---|---|---|
| PATCH | `/api/v1/profile` | `UpdateUserProfileController` | `UpdateUserProfileRequest` | `UserResource` (200) | `auth:sanctum` |
| POST | `/api/v1/profile/change-password` | `ChangeUserPasswordController` | `ChangeUserPasswordRequest` | `UserResource` (200) | `auth:sanctum` |
| DELETE | `/api/v1/profile` | `DeleteUserAccountController` | `DeleteUserAccountRequest` | — (204) | `auth:sanctum` |
| POST | `/api/v1/profile/avatar` | `UploadUserAvatarController` | `UploadUserAvatarRequest` | `UserResource` (200) | `auth:sanctum` |
| DELETE | `/api/v1/profile/avatar` | `RemoveUserAvatarController` | — (no body) | `UserResource` (200) | `auth:sanctum` |

All five sit in a single `Route::middleware('auth:sanctum')->prefix('profile')->name('profile.')->group(...)` block in `routes/api.php`, each with a named route (`profile.update`, `profile.change-password`, `profile.destroy`, `profile.avatar.store`, `profile.avatar.destroy`).

## Validation rules

| Request | Rules |
|---|---|
| `UpdateUserProfileRequest` | `name`: nullable, string, max 50 (matches `users.name` column). `language`: nullable, `Rule::enum(Language::class)`. `preferred_version`: nullable, string, `Rule::exists('bible_versions', 'abbreviation')` wrapped in a `Rule::when(Schema::hasTable('bible_versions'), ...)` guard (see Risks). A custom `after` closure rejects the payload when all three are null. |
| `ChangeUserPasswordRequest` | `current_password`: required, string. `new_password`: required, string, `Password::defaults()`, `confirmed`, `different:current_password`. Authorization: `$this->user() !== null`. |
| `DeleteUserAccountRequest` | `password`: required, string. Authorization: `$this->user() !== null`. |
| `UploadUserAvatarRequest` | `avatar`: required, `File::image()->max(5 * 1024)->types(['jpeg','png'])`. |

`Password::defaults()` is declared once in `AppServiceProvider::boot()` (min 8, mixed case, numbers) so the same rule set applies to registration and password reset (retroactive upgrade — flagged in Tasks).

## Data & migrations

| Migration | Purpose |
|---|---|
| `add_profile_fields_to_users_table` | Add `preferred_version` (nullable string, length 16) after `language`. Add `deleted_at` (softDeletes) after `updated_at`. Both operations are idempotent via column-exists checks to stay safe against the shared prod DB. |
| `change_users_email_unique_to_composite_with_deleted_at` | Drop the current `users_email_unique` index and replace it with a unique `(email, deleted_at)` composite index. `down()` reverses to the single-column unique index. Runs only if the unique index currently exists (idempotent). |

No new table for profile — all profile columns live on `users`.

## Tasks

- [ ] 1. Create migration `add_profile_fields_to_users_table` adding `preferred_version` and `deleted_at` columns; guard each column with a `Schema::hasColumn` check so the migration is safe against the shared Symfony DB.
- [ ] 2. Create migration `change_users_email_unique_to_composite_with_deleted_at` that swaps the unique index; guard via `doctrine/dbal`-free `Schema::hasIndex` check and cover both fresh and reconciled paths.
- [ ] 3. Add the `avatars` filesystem disk to `config/filesystems.php` — S3-backed via the same env vars as `s3`, with `root` set to a configurable prefix (`AVATAR_DISK_ROOT`, default `avatars`) so Symfony-era paths continue to resolve.
- [ ] 4. Update `App\Models\User`: add `SoftDeletes`, add `preferred_version` to `$fillable`, add `avatar_url` accessor reading from `Storage::disk('avatars')`.
- [ ] 5. Update `App\Http\Resources\Auth\UserResource` to expose `language`, `preferred_version`, `avatar_url` keys alongside the existing fields.
- [ ] 6. Update `App\Domain\Auth\Actions\LoginUserAction` to treat a soft-deleted user the same as a non-existent one (the `User::where('email', ...)->first()` already excludes soft-deleted rows by default — verify and add a regression feature test in task 18).
- [ ] 7. Register `Password::defaults()` in `AppServiceProvider::boot()` with min 8 + mixed case + numbers + symbols; update `RegisterUserRequest` and `ResetPasswordRequest` to use `Password::defaults()` in place of the current `min:8`.
- [ ] 8. Create `App\Domain\User\Profile\Events\UserAccountDeleted` with primitive `userId` and `email` public readonly properties and a TODO comment pointing to the deferred cascade listener story.
- [ ] 9. Create `App\Domain\User\Profile\Exceptions\IncorrectCurrentPasswordException` extending `ValidationException` with a `forField(string $field): self` factory that seeds the validator's error bag for that field.
- [ ] 10. Create the four DTOs in `App\Domain\User\Profile\DataTransferObjects` (`UpdateUserProfileData`, `ChangeUserPasswordData`, `DeleteUserAccountData`, `UploadUserAvatarData`) each with a `from(array)` factory.
- [ ] 11. Create `UpdateUserProfileAction` + matching PHPUnit unit test covering all-fields-set, single-field-set, enum language cast, and preferred_version persisted.
- [ ] 12. Create `ChangeUserPasswordAction` + unit test: happy path (password replaced, other tokens deleted, current token retained), wrong current password (throws `IncorrectCurrentPasswordException`, no state change), same-password guard (Request handles it, but action-level sanity test for the hash-change assertion).
- [ ] 13. Create `DeleteUserAccountAction` + unit test: happy path (tokens revoked, event dispatched via `Event::fake()`, user soft-deleted), wrong password (throws, no tokens revoked, no event, no soft-delete).
- [ ] 14. Create `UploadUserAvatarAction` + unit test using `Storage::fake('avatars')`: new upload stores the file, previous avatar is removed when replaced, user's `avatar` column stores the relative path.
- [ ] 15. Create `RemoveUserAvatarAction` + unit test using `Storage::fake('avatars')`: file is removed, column nulled, no-op when `avatar` was already null.
- [ ] 16. Create the four Form Requests in `App\Http\Requests\Profile` (`UpdateUserProfileRequest`, `ChangeUserPasswordRequest`, `DeleteUserAccountRequest`, `UploadUserAvatarRequest`) + unit tests on validation rules including the "at least one field" cross-field rule and the `bible_versions` conditional `exists` guard.
- [ ] 17. Create the five controllers in `App\Http\Controllers\Api\V1\Profile` (single-action invokables) and wire the route group in `routes/api.php` with named routes under `profile.*`.
- [ ] 18. Feature test `UpdateUserProfileTest`: partial payload, full payload, missing-all-fields 422, invalid language 422, unknown preferred_version 422, unauthenticated 401.
- [ ] 19. Feature test `ChangeUserPasswordTest`: happy path (200 + all-other-tokens revoked + current token still works + login with new password succeeds), wrong current password 422, weak new password 422, new-equals-current 422.
- [ ] 20. Feature test `DeleteUserAccountTest`: happy path (204 + all tokens revoked + user soft-deleted + `UserAccountDeleted` event dispatched via `Event::fake`), wrong password 422 + account untouched, login after delete returns the standard 401 "invalid credentials" shape, re-registration with the same email succeeds (covers the composite unique index).
- [ ] 21. Feature test `UploadUserAvatarTest` using `Storage::fake('avatars')` + `UploadedFile::fake()->image()`: happy path, >5 MB rejected 422, gif rejected 422, replace flow removes the old file.
- [ ] 22. Feature test `RemoveUserAvatarTest`: happy path deletes the file and nulls the column, no-op when the column is already null still returns 200.
- [ ] 23. Run `make lint-fix`, `make stan`, then `make test filter=Profile`; finally `make test` before marking the story ready for review.

## Risks & notes

- **MBA-007 dependency — `bible_versions` table.** MBA-007 has not shipped. The `preferred_version` validation in `UpdateUserProfileRequest` gates the `Rule::exists('bible_versions', 'abbreviation')` behind `Schema::hasTable('bible_versions')`. Until MBA-007 lands, a payload carrying `preferred_version` is rejected with a clear "preferred version is not yet available" validator message. The column is still added in task 1 so existing Symfony data (if any) round-trips untouched. When MBA-007 ships, delete the `hasTable` guard.
- **Soft-delete + unique email.** The composite `(email, deleted_at)` index in task 2 lets a user re-register after account deletion. MySQL treats `NULL` values as distinct in unique indexes, so multiple soft-deleted rows with the same email coexist; only one live row per email is allowed. Document this in the migration PHPDoc.
- **Cascade deletion is deferred.** `UserAccountDeleted` carries primitives only, so a queued listener introduced in the follow-up story can cascade notes, favorites, reading-plan subscriptions, etc. without re-loading a soft-deleted user. Until that story lands, orphan data accumulates — acceptable per product's "30-day grace period" direction, flagged in CHANGELOG / story.md.
- **Forced logout coverage.** `ChangeUserPasswordAction` only revokes other-device tokens; `DeleteUserAccountAction` revokes all. Confirm with MBA-005 that `currentAccessToken()` under `auth:sanctum` always resolves to a `PersonalAccessToken` (it does per the existing `LogoutCurrentTokenAction` guard comment). Tests cover this.
- **Avatar disk abstraction.** Introducing an `avatars` disk (over raw `s3`) is the right abstraction for testing and for the eventual CDN cutover. Do not reach for `Storage::disk('s3')` directly in any Action or Resource — always via `avatars`.
- **`Password::defaults()` retroactive change.** Strengthening registration and reset password rules is a mild backwards-incompatible nudge. Acceptable because accounts created with weaker passwords still log in — the rule only gates new/changed passwords.
- **No new exception handler.** `IncorrectCurrentPasswordException extends ValidationException`, so the 422 rendering path in `bootstrap/app.php` already handles it. No `$exceptions->render(...)` registration is needed.
- **Sibling name parity.** `UpdateUserProfile` prefix is used across Action/Request/Controller/Data/Test. Same for `ChangeUserPassword`, `DeleteUserAccount`, `UploadUserAvatar`, `RemoveUserAvatar`. No shortened variants.
- **No route-model binding with scopes.** All five endpoints operate on `$request->user()`; no `{user}` / `{profile}` path binding is involved.
- **Deferred Extractions register.** No extraction triggers fire here — these endpoints do not use the owner-gated FormRequest pattern (they all operate on `$request->user()` implicitly) and they are not lifecycle Actions on a subscription aggregate. Tripwire untouched.
