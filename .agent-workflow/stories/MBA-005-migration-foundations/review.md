# Code Review: MBA-005-migration-foundations

**Verdict:** APPROVE
**Reviewed at:** dc7c5a2

## Scope checked

All files touched by commit `dc7c5a2`:

- Migrations: `0001_01_01_000000_create_users_table.php` (rewrite), `2026_04_22_100000_reconcile_symfony_user_table.php` (new), `2026_04_18_170829_create_reading_plan_subscriptions_table.php` (FK width fix).
- Config: `config/hashing.php` (new), `config/auth.php` (new key).
- Domain: `RequestPasswordResetAction`, `ResetPasswordAction`, `RequestPasswordResetData`, `ResetPasswordData`, `InvalidPasswordResetTokenException`, `PasswordResetNotification`.
- HTTP: `RequestPasswordResetController`, `ResetPasswordController`, `RequestPasswordResetRequest`, `ResetPasswordRequest`, route additions.
- App: `User` model, `RegisterUserAction` (+`roles: []`), `UserFactory` (new defaults), `bootstrap/app.php` renderer.
- Tests: `ForgotPasswordTest`, `ResetPasswordTest`, `ResetPasswordActionTest`, `PasswordResetNotificationTest`, `LoginUserTest::test_it_logs_in_a_user_with_a_symfony_argon2id_hash`.
- Infra/docs: `phpunit.xml` (argon cost env), `.agent-workflow/CLAUDE.md` auth line.

Story acceptance criteria 1–12 all land in the diff.

## Findings

### Suggestions

- [ ] `app/Domain/Auth/Actions/RequestPasswordResetAction.php:12-17` — the comment claims the uniform response prevents enumeration "via response shape or timing difference". The response-shape claim is accurate; the timing claim is overstated because `sendResetLink` still performs a synchronous DB insert into `password_reset_tokens` for known emails and returns `INVALID_USER` immediately for unknown ones, leaving a measurable latency delta. Consider trimming the comment to "response shape" only, or document the residual timing channel as accepted. Not blocking — the behavior is correct; only the justification is imprecise.

- [ ] `app/Domain/Auth/Notifications/PasswordResetNotification.php:35-38` — `$expireMinutes = (int) config('auth.passwords.' . Arr::get(config('auth.defaults'), 'passwords') . '.expire', 60);` manually reconstructs a config path that Laravel exposes more cleanly. `config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60)` (or a small helper) reads identically without the `Arr::get` wrapper. Fine to leave as-is.

- [ ] `app/Domain/Auth/Notifications/PasswordResetNotification.php:26,31` — `via(User $notifiable)` / `toMail(User $notifiable)` narrow the parameter type to `User`. Current call sites only notify `User` instances, so this works, but Laravel's signature is `mixed`/`object`; if the notification is ever dispatched via `Notification::route('mail', …)`, an `AnonymousNotifiable` will break those signatures. Acceptable today; worth revisiting if the notification gets reused.

- [ ] `app/Domain/Auth/Notifications/PasswordResetNotification.php:41-46` — email copy is hardcoded English. The `users.language` column is preserved (Symfony used it for localization). Out of scope for this story, but flag in MBA-018 (profile) if per-user locale is expected for transactional mail.

- [ ] `database/migrations/2026_04_22_100000_reconcile_symfony_user_table.php:23-30` — after reconcile, prod `last_login` / `created_at` / `updated_at` inherit Symfony's DATETIME type, whereas the fresh-CI path creates TIMESTAMP. Functionally equivalent for Laravel's casts, but a column-type drift between environments is worth noting — if a future migration uses `$table->timestamp(...)->change()` it will try to narrow DATETIME → TIMESTAMP on prod. Not a bug today.

- [ ] `config/auth.php:128` — default fallback `'http://api.mybible.local/reset-password'` points to the API host, which has no reset UI. Harmless in dev (env is set), but a `null` default (or a more obvious `http://localhost/reset-password`) makes the "must be overridden" intent clearer.

## What looked right

- The two-migration strategy (initial short-circuits on prod, reconcile short-circuits on fresh) converges on an identical schema on both paths — verified by walking the conditions.
- `increments('id')` on the fresh path matches Symfony's INT width, and the sibling fix in `create_reading_plan_subscriptions_table` (`unsignedInteger('user_id')` + explicit `foreign(...)`) keeps the FK compatible. Good catch.
- Argon2id swap with rehash-on-login intact, and the pre-baked Symfony hash literal in `LoginUserTest::test_it_logs_in_a_user_with_a_symfony_argon2id_hash` locks in the compatibility claim from AC #7.
- `ForgotPasswordTest` covers the no-enumeration path via `Notification::assertNothingSent()`; `ResetPasswordActionTest` covers every non-success broker branch with a hand-rolled stub (no DB) — the feature test handles the happy path, so no duplicated work.
- `config/hashing.php` cost values mirror the Symfony `security.yaml` numbers (m=65536, t=4, p=1); `phpunit.xml` overrides argon cost via env only (no hardcoded CI-specific values in `config/`), matching the plan's risk mitigation.
- Exception renderer registered next to the existing domain renderers; no try/catch sprinkled into controllers.
- Model drops `#[Fillable]`/`#[Hidden]` attributes in favor of `$fillable`/`$hidden`/`casts()` per the plan; `sendPasswordResetNotification` override in place.

## Verdict rationale

No Critical or Warning findings. Suggestions are cosmetic or follow-on-story items. Status → `qa-ready`.
