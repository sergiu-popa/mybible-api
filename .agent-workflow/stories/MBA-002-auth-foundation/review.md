# Code Review — MBA-002-auth-foundation

## Summary

The implementation delivers the story faithfully and cleanly. Sanctum is
installed and wired to a 14-day env-driven expiration; the env-injected
API-key middleware compares with `hash_equals` and attaches the matched
client name to the request; the combined `api-key-or-sanctum` middleware
hard-fails on invalid bearer tokens per the resolved architectural
question; and the four auth endpoints (register, login, logout, me) are
all present with correct status codes, response shapes, and JSON error
envelope behavior. Domain layering is respected (Actions + DTOs under
`App\Domain\Auth`, controllers are thin, Form Requests validate,
`UserResource` shapes the output). All 49 tests pass, lint is clean,
PHPStan finds no errors. No Critical findings.

## Findings

### Critical (must fix before merge)

_(none)_

### Warning (should fix)

_(none)_

### Suggestion (nice to have)

- [ ] `app/Domain/Auth/Actions/LoginUserAction.php:20` — The hardcoded
  `DUMMY_HASH` is a `$2y$12$…` bcrypt hash. If `BCRYPT_ROUNDS` is ever
  changed from 12 in `.env`, the dummy-hash timing will diverge from the
  real-user hash timing, partially defeating the timing-uniformity goal.
  Suggested fix: compute the dummy once at boot with
  `Hash::make('dummy-password')` stored in a memoized static, or derive
  the rounds from `config('hashing.bcrypt.rounds')`. Not blocking — the
  current form still removes the obvious "instant-return on unknown
  email" oracle.
- [ ] `app/Http/Middleware/EnsureValidApiKey.php:14` — The
  `TODO(rate-limit)` marker sits on a bare line between the class opener
  and the `handle` method. It reads fine, but conventional placement
  would be either inside `handle()` or on a class-level docblock. Cosmetic.
- [ ] `app/Domain/Auth/Actions/LogoutCurrentTokenAction.php:22-26` — The
  `@phpstan-ignore instanceof.alwaysTrue` is justified by the docblock
  (defensive guard for non-Sanctum callers), but `currentAccessToken()`
  can return `null` on unauthenticated callers too. The `instanceof`
  guard already handles `null`, so this is correct — just worth noting
  that the `null` path is what the second Action unit test actually
  exercises, not a `TransientToken`.
- [ ] `tests/Feature/Api/V1/Auth/LoginUserTest.php:19` — Relies on the
  `hashed` cast to hash `'secret-pass'` during factory create. This
  works today because `UserFactory` does not override the cast, but a
  short comment (or switching to `Hash::make('secret-pass')` explicitly)
  would make the intent clearer. Minor.
- [ ] Login/register endpoints have no brute-force throttling. Out of
  scope per the story's rate-limiting deferral, but worth tracking in
  the follow-up rate-limit story so `auth.login` gets an explicit
  `throttle:6,1` or similar.

## Checklist

- [x] All acceptance criteria from story.md are met
- [x] Architecture matches architecture.md
- [x] All tasks in tasks.md are completed
- [x] Tests exist for all new code
- [x] Tests pass (49 passed, 126 assertions)
- [x] No security issues found
- [x] No performance issues found
- [x] Livewire 4 conventions followed — **N/A** (JSON-only API per
      `.agent-workflow/CLAUDE.md`)
- [x] Code style matches guidelines (strict types, final classes,
      explicit return types, no `else`, no magic strings, one import
      per line, DTOs are `final readonly`, Actions use `execute()`)
- [x] Controllers contain no business logic (delegate to Actions)
- [x] No Eloquent models returned directly from controllers
      (wrapped in `UserResource`)
- [x] No inline `$request->validate()` (Form Requests used)
- [x] Feature tests assert JSON structure + status codes
- [x] Exception paths return the JSON error envelope

## Verdict

APPROVE
