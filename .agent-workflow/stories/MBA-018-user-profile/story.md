# Story: MBA-018-user-profile

## Title
User profile — update profile, change password, delete account, avatar

## Status
`qa-ready`

## Description
Self-service account management for authenticated users. Covers:
display name and language update, password change (with current
password confirmation), account deletion, and avatar upload/remove.

Symfony source:
- `UserController::updateProfile()`
- `UserController::changePassword()`
- `UserController::deleteAccount()`
- Avatar handling is inside UserController (upload to S3 via
  `async-aws/s3`)

## Acceptance Criteria

### Profile
1. `PATCH /api/v1/profile` — body `{ name?, language?,
   preferred_version? }`. At least one field required. Sanctum only.
2. `language` is validated against the supported languages list from
   `config/app.supported_languages` (or similar).
3. `preferred_version` is validated against the `bible_versions` table
   (referenced in MBA-007 / MBA-008).
4. Returns `200` with the updated user resource.

### Change password
5. `POST /api/v1/profile/change-password` — body `{ current_password,
   new_password, new_password_confirmation }`.
6. `current_password` must match the stored hash; mismatch returns
   `422`.
7. `new_password` uses the app's password rule (min 8, mixed case,
   number — use `Password::defaults()` if configured).
8. On success, returns `200` and all other Sanctum tokens for the user
   are revoked — only the current token remains. Client-facing
   behavior: "logout on other devices after password change."

### Delete account
9. `DELETE /api/v1/profile` — body `{ password }` confirming the
   caller's password.
10. On password mismatch, `422`.
11. On success:
    - Soft-delete the user (add `deleted_at` via `SoftDeletes` trait).
    - Revoke ALL Sanctum tokens for the user.
    - Fire a `UserAccountDeleted` event (listener handles cascade
      scheduling — out of scope for this story; a TODO is acceptable).
    - Return `204`.
12. A deleted user cannot log in (LoginAction checks `deleted_at` is
    null and returns the generic "invalid credentials" response).

### Avatar
13. `POST /api/v1/profile/avatar` — multipart upload.
    - `avatar` required image, max 5 MB, JPEG or PNG.
    - Stored on the `s3` disk (or whatever disk `config/filesystems`
      exposes as `avatars`).
    - Returns `200` with the user resource (`avatar_url` populated).
14. `DELETE /api/v1/profile/avatar` — removes the avatar file and
    clears the user's `avatar` column.
15. Existing avatars from Symfony continue to resolve — the `avatar`
    column stores a relative path, and the `avatar_url` accessor
    builds the absolute URL via `Storage::disk('avatars')->url(...)`.

### Tests
16. Feature tests: profile update (partial + full payloads), invalid
    language, change password (happy path, wrong current, weak new),
    delete account (happy path, wrong password, login after delete),
    avatar upload (happy path, size limit, file-type limit), avatar
    delete.
17. Unit tests for each Action.

## Scope

### In Scope
- Six endpoints listed above.
- Adds `SoftDeletes` to `User` model.
- `UserAccountDeleted` event.
- Avatar upload via Flysystem S3 driver
  (`league/flysystem-aws-s3-v3`).
- Actions, DTOs, Form Requests, API Resources, Feature tests.

### Out of Scope
- Full cascade on account deletion (notes, favorites, progress).
  Handle in a follow-up story driven by product's data retention
  policy. Emit `UserAccountDeleted` as a placeholder.
- Two-factor authentication.
- Email change (Symfony has it? If yes, add as a sub-criterion;
  otherwise defer).
- Admin user management endpoints.

## Technical Notes

### Avatar storage migration
Symfony uses `async-aws/s3`. Laravel uses
`league/flysystem-aws-s3-v3`. Both talk to the same bucket. The
existing files stay in place; we just switch the PHP client. Verify
by listing a known existing avatar via the Laravel Storage disk
during architecture phase.

### Token revocation on password change
```
$user->tokens()
    ->where('id', '!=', $currentAccessToken->id)
    ->delete();
```

### Soft-delete + unique email
If `users.email` has a unique index and a user deletes and re-registers
with the same email, the unique index will reject the new insert. Two
options:
- Change the unique index to `(email, deleted_at)` (composite with the
  soft-delete column).
- Hard-delete after a grace period (job scheduled for N days later).

Recommend the composite index — simpler and the grace-period
hard-delete can come with the follow-up cascade story.

## Dependencies
- **MBA-005** (user schema + auth).
- **MBA-007** (bible_versions table for preferred_version validation).

## Open Questions for Architect
1. **Email change.** In Symfony? If yes, include under profile update
   with email re-verification (new verification email triggers, new
   email only takes effect when confirmed). If no, skip.
2. **Deletion grace period.** How long between soft-delete and hard-
   delete (with data cascade)? Recommend 30 days, configurable.
3. **Avatar image processing.** Generate thumbnails? Symfony has
   `liip/imagine` for this. Keep or drop? Recommend drop — deliver
   the original, let clients request via CDN with resize query
   params if needed.
