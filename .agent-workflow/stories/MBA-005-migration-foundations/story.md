# Story: MBA-005-migration-foundations

## Title
Migration foundations — User schema reconciliation, Argon2id hashing, password reset

## Status
`done`

## Description
First migration story. Establishes the bridge between the existing Symfony
database and this Laravel codebase so subsequent domain migrations can run
against the shared MySQL instance without a schema fork.

Three things need to land before any Symfony domain can be ported:

1. The `users` table used by Laravel must line up with the Symfony `user`
   table that holds all production accounts. The schemas diverge (name, columns,
   naming conventions) and we are sharing one database during the migration.
2. Existing Symfony passwords are Argon2id-hashed (memory cost 65536, 4 iterations).
   Laravel's default bcrypt hasher will reject them on login. Swap the default
   hasher so existing passwords verify without a rehash cycle.
3. Symfony stores password reset tokens inline on `user.resetToken` + `resetDate`.
   We replace both with the Laravel-standard `password_reset_tokens` table so
   `Illuminate\Auth\Passwords` plugs in cleanly. Any Symfony reset email already
   sent becomes invalid at cutover — accepted.

This story does NOT introduce any API endpoints of its own. It unblocks MBA-006+.

## Acceptance Criteria

### Users table reconciliation
1. A migration renames the existing Symfony `user` table to `users` (or takes
   the opposite path — see Open Questions) so the Laravel `User` model resolves
   by convention. If the rename is taken, Symfony must be unreachable for the
   duration of the migration window.
2. Symfony-era columns preserved: `email`, `password`, `roles` (JSON),
   `language` (string), `avatar` (nullable string), `last_login` (nullable
   datetime).
3. Legacy `salt` column is dropped.
4. Laravel standard columns added if absent: `email_verified_at` (nullable
   datetime), `remember_token` (nullable string 100), `created_at`/`updated_at`.
5. `App\Models\User` declares the expanded `$fillable`, `$hidden`, and `$casts`
   arrays reflecting the new columns. `roles` casts to `array`.

### Password hashing compatibility
6. `config/hashing.php` sets `driver => 'argon2id'` with the same cost
   parameters Symfony used (memory 65536, time 4, threads 1 — verify in
   `security.yaml`).
7. A user whose password was hashed under Symfony can log in through
   `POST /api/v1/auth/login` without any password reset.
8. New registrations hash with Argon2id going forward.

### Password reset table
9. Migration creates `password_reset_tokens` table using Laravel's standard
   schema (`email` PK, `token`, `created_at`).
10. Migration drops `user.resetToken` and `user.resetDate` columns (after
    confirming no in-flight resets that matter — product decision).
11. `POST /api/v1/auth/forgot-password` and `POST /api/v1/auth/reset-password`
    endpoints wired to `Illuminate\Auth\Passwords\PasswordBroker`. Email
    delivery uses the existing Mailer configuration.

### Documentation
12. `.agent-workflow/CLAUDE.md` auth section updated to replace the stale
    "Auth: none yet" line with the actual current state (Sanctum +
    api-key-or-sanctum, Argon2id).

## Scope

### In Scope
- Migrations for schema reconciliation (`users` rename + column changes +
  `password_reset_tokens`).
- Hashing driver swap.
- Password reset endpoints + Notification for the reset email.
- Update to `App\Models\User` and the auth tests to cover Argon2id.
- Doc update in `.agent-workflow/CLAUDE.md`.

### Out of Scope
- Avatar file storage flip to Flysystem S3 (handled in MBA-018).
- Profile update / change password / delete account endpoints (MBA-018).
- Forced global logout at cutover (MBA-020).
- Touching any domain table other than `user` and `password_reset_tokens`.

## Technical Notes

### Column naming
Symfony uses camelCase on columns (`resetToken`, `lastLogin`, `passwordHash`).
Laravel convention is snake_case. Two options:

- **A:** Rename columns in the migration (`lastLogin` → `last_login`). Breaks
  Symfony immediately; requires coordinated downtime.
- **B:** Keep camelCase at the DB level, map them in the Eloquent model via
  `protected $casts` / accessor-mutator pairs.

**Recommendation:** option A during this story. We are mid-migration and will
take downtime for the table rename anyway; carrying camelCase forward in a
Laravel model is noise that every future contributor stubs their toe on.

### Argon2id config
Laravel's `Illuminate\Hashing\Argon2IdHasher` expects `memory`, `time`,
`threads` in `config/hashing.php`. Pull the exact values from
`config/packages/security.yaml` in the Symfony repo under `password_hashers`.

### Password reset flow
Prefer `Password::broker()->sendResetLink()` + `Password::broker()->reset()`
over a custom `resetToken` column. The `password_reset_tokens` table is the
Laravel-standard landing pad.

## Dependencies
- None. This story is the root of the migration tree.

## Open Questions for Architect
1. **Table rename direction.** Do we rename `user` → `users` (Laravel
   convention) or configure `$table = 'user'` on the model (preserve Symfony
   name, no downtime)? Recommendation above is rename; needs product sign-off
   because Symfony breaks at that moment.
2. **Column rename window.** If we rename columns (camelCase → snake_case),
   when does the Symfony deploy stop? Likely must coincide with MBA-020 cutover
   unless Symfony can be put behind a read-only flag earlier.
3. **Existing `remember_token` / `email_verified_at`.** Does the Symfony
   schema already have these (some older Doctrine setups do)? Confirm before
   writing the `ALTER TABLE` migration.
4. **In-flight password resets.** Any Symfony-issued reset emails pending?
   Safe to invalidate?
