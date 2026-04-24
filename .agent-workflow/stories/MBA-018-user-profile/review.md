# Code Review: MBA-018 — User profile

**Commit reviewed:** `36c697d`
**Branch:** `mba-018`
**Scope checked:** 51 files, 5 endpoints, 2 migrations, shared `Password::defaults()`
rollout, `UserResource` additive changes, `avatars` filesystem disk.

## Verdict: APPROVE

- `make lint` — PASS (500 files)
- `make stan` — PASS (480 files, 0 errors)
- `make test filter=Profile` — PASS (56 tests, 161 assertions)

The implementation matches the plan (all 23 tasks), keeps the plan's guardrails
(avatar disk abstraction, composite unique index gated behind `deleted_at`,
event-before-soft-delete ordering, primitive-only event payload, shared
`Password::defaults()` in `AppServiceProvider::boot()`) and honours every
override in `.agent-workflow/CLAUDE.md` (JSON-only, Form Request → Action →
Resource pipeline, no inline validation, no Eloquent models returned directly,
named routes under the `profile.*` prefix group with `auth:sanctum`).

No Critical findings. No Warnings. Three Suggestions below, none blocking.

## Suggestions (non-blocking)

### 1. `defaultHeaders` flush is duplicated three times

- `tests/Feature/Api/V1/Profile/ChangeUserPasswordTest.php:46`
- `tests/Feature/Api/V1/Profile/DeleteUserAccountTest.php:70`
- `tests/Feature/Api/V1/Profile/DeleteUserAccountTest.php:91`

All three call `$this->defaultHeaders = array_diff_key($this->defaultHeaders,
['Authorization' => ''])` to drop the `Authorization` header after
`givenAnAuthenticatedUser()` so a subsequent login or public call runs as an
anonymous client. A fourth copy would be the right moment to extract a
`clearAuthenticationHeader()` helper on `Tests\Concerns\InteractsWithAuthentication`.
Below the §7 tripwire threshold — not a register entry.

### 2. `Schema::hasTable('bible_versions')` runs on every profile update

`app/Http/Requests/Profile/UpdateUserProfileRequest.php:30,45` — the guard is
evaluated twice per request (once in `rules()`, once in `messages()`), each
issuing an `information_schema` query. The risk described in the plan (table
hasn't shipped yet) has already resolved: MBA-007 merged and
`App\Domain\Bible\Models\BibleVersion` exists. The `hasTable` guard can be
deleted in a follow-up, as the plan prescribes ("When MBA-007 ships, delete the
`hasTable` guard."). Tracking here so it is not silently forgotten — acceptable
in-scope as-is.

### 3. `IncorrectCurrentPasswordException::forField` reconstructs itself twice

`app/Domain/User/Profile/Exceptions/IncorrectCurrentPasswordException.php:22-29`
builds a `ValidationException` via `parent::withMessages()` (which returns a
`new static(...)`) and then instantiates a fresh `self` from the parent's
public properties. Works because the parent constructor's three parameters are
all public. A slightly simpler variant would call `parent::__construct()`
directly on a pre-seeded validator — but the current form is explicit about the
factory contract and is covered by both the action-level unit tests and the
feature tests (`assertJsonValidationErrors(['current_password'])`,
`assertJsonValidationErrors(['password'])`). No change requested.

## Notes — why APPROVE

- **Security / authorization.** Every endpoint is behind `auth:sanctum`; every
  Form Request's `authorize()` returns `$this->user() !== null` so middleware
  gaps still fail closed. `DeleteUserAccountAction` and
  `ChangeUserPasswordAction` both route the password check through
  `Hash::check` before mutating state.
- **Token revocation.** `ChangeUserPasswordAction` retains only the current
  token (`->where('id', '!=', $currentToken->getKey())->delete()`);
  `DeleteUserAccountAction` revokes all. Feature tests cover both.
- **Soft-delete hygiene.** `RegisterUserRequest` now `Rule::unique('users',
  'email')->whereNull('deleted_at')`, so a re-registration with a reused email
  doesn't collide with the soft-deleted row.
  `test_re_registration_with_the_same_email_is_allowed` in
  `DeleteUserAccountTest` is the regression test for the composite index.
- **Avatar disk abstraction.** No Action or Resource reaches for
  `Storage::disk('s3')` — everything goes through `avatars`. `Storage::fake('avatars')`
  in tests substitutes cleanly.
- **Atomic replace ordering.** `UploadUserAvatarAction` writes the new object
  before deleting the previous, so an S3 error mid-upload leaves the user's
  previous avatar intact (column points to an existing file).
- **Event payload is primitive-only.** `UserAccountDeleted` carries `int
  $userId` and `string $email` — no serialized Eloquent model — so a queued
  cascade listener introduced in the follow-up story resolves cleanly even
  after the soft-deleted row is hard-deleted.
- **Password policy drift.** `AppServiceProvider::boot()` registers
  `Password::defaults()` once; `RegisterUserRequest` and
  `ResetPasswordRequest` both use `Password::defaults()`, as does
  `ChangeUserPasswordRequest`. Existing Feature / Request tests are updated to
  use passwords that satisfy min 8 + mixed case + numbers.
- **No public API contract churn risk.** `UserResource` additions (`language`,
  `preferred_version`, `avatar_url`) reflect underlying column values
  directly — none of them is constant under the query scope.
- **API Design dimension.** `PATCH` for partial profile update; `DELETE` for
  account and avatar removal; `POST` for change-password and avatar upload;
  `204` for account deletion, `200` + resource for the rest. Matches §
  Auditor "API Design" dimension.

Status → `qa-ready`.
