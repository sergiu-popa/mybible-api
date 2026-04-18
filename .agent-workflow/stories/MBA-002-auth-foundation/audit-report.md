# Audit Report — MBA-002-auth-foundation

## Summary

MBA-002 delivers a clean, correct auth foundation: Sanctum PATs for end
users (register/login/logout/me) with a 14-day env-driven expiration, a
custom `api-key` middleware backed by an env-injected `{ name => key }`
map with `hash_equals()` comparison, and a combined `api-key-or-sanctum`
alias that hard-fails on invalid bearer tokens (matching the resolved
open question). Domain layering is respected — Actions live under
`App\Domain\Auth\Actions`, DTOs are `final readonly`, controllers are
thin invokables that receive a Form Request, call an Action, and render
a Resource. All 49 tests pass (126 assertions), lint is clean, PHPStan
finds no errors, and every acceptance criterion has direct coverage.
The login Action even takes the extra step of burning a dummy bcrypt
compare on unknown-email lookups to remove a trivial timing oracle.
No Must-Fix issues. Confidence: **HIGH**.

## Scores

| Dimension | Score (1–5) | Notes |
|---|---|---|
| Architecture Compliance | 5 | Every file, namespace, and signature matches `architecture.md`. Domain/HTTP boundaries respected; controllers call Actions via DTOs; exception handler untouched. |
| Code Quality | 4 | `strict_types`, `final`, explicit return types, `readonly` DTOs, no `else`, one import per line. Only blemish: `DUMMY_HASH` is a literal `$2y$12$…` hash whose cost is decoupled from `config('hashing.bcrypt.rounds')`. |
| API Design | 5 | Correct verbs and status codes (201/200/204/401/422), versioned `/api/v1/auth/*` prefix, named routes, Form Requests for validation, `UserResource` wrapping, `{data: {…}}` envelope, JSON error envelope on all failure paths. |
| Security | 4 | `hash_equals` for API keys, Sanctum bearer TTL, `hashed` cast on password, hidden attributes on User, Resource whitelist, timing-uniformity on login, hard-fail combined middleware. Gap: no throttling on `auth.login` / `auth.register` (deferred by scope) and the dummy-hash rounds coupling noted above. |
| Performance | 5 | Single `WHERE email` lookup + single token insert on login; no N+1 surface; no list endpoints; Sanctum's published migration provides the indexes on `personal_access_tokens`. |
| Test Coverage | 5 | DTO + Action + Form Request + Resource unit tests; two middleware feature tests exercising every permutation (valid bearer, API-key-only, hard-fail-with-valid-API-key, expired bearer, missing both, bearer precedence); four HTTP feature tests covering happy paths + 401 + 422 + expired-token-via-`travel()` + multi-token revocation. |

## Issues Found

### Must Fix

_(none)_

### Should Fix

_(none)_

### Minor

- [ ] `app/Domain/Auth/Actions/LoginUserAction.php:20` — `DUMMY_HASH`
  is a frozen `$2y$12$…` bcrypt hash. If `BCRYPT_ROUNDS` is ever
  changed in `.env` (e.g. to 10 or 13), the dummy-path cost will
  diverge from the real-user-path cost, partially re-opening the
  timing oracle on unknown emails. Fix options: (a) derive rounds
  from `config('hashing.bcrypt.rounds')` and pre-hash at service
  boot into a memoized static, or (b) keep the literal and add a
  comment locking `BCRYPT_ROUNDS=12` as a deployment invariant.
  Not blocking — the obvious "instant-return" oracle is gone.
- [ ] `app/Http/Middleware/EnsureValidApiKey.php:14` —
  `TODO(rate-limit)` marker is a bare line between the class opener
  and `handle()`. Cosmetic; either inline the note at its logical
  point in `handle()` or promote it to a class-level docblock.
- [ ] `app/Domain/Auth/Actions/LogoutCurrentTokenAction.php:24` — The
  `@phpstan-ignore instanceof.alwaysTrue` docblock accurately describes
  the `TransientToken` case, but the actual runtime branch covered by
  `test_it_returns_silently_when_there_is_no_current_token` is the
  `null` path (no token set on the user). The `instanceof` guard
  already rejects `null`, so the behavior is correct — the docblock
  is just slightly narrower than reality. Consider adding `null` to
  the comment for accuracy.
- [ ] `tests/Feature/Api/V1/Auth/LoginUserTest.php:19` — Relies on the
  `User` model's `hashed` cast to hash `'secret-pass'` on factory
  create. Correct today, but a one-line comment would surface the
  implicit dependency for future readers.
- [ ] `auth.login` and `auth.register` have no brute-force throttling.
  Scoped out of MBA-002 per the story's rate-limiting deferral, but
  the exposure is real — make sure the follow-up rate-limit story
  explicitly covers these two routes (e.g. `throttle:6,1` on login,
  `throttle:3,10` on register) before MBA-003/004 ship.
- [ ] `config/sanctum.php:21` retains the default
  `SANCTUM_STATEFUL_DOMAINS` list (localhost:3000, 127.0.0.1:8000, …)
  from Sanctum's published config. With `'guard' => []` this is inert
  for the current API-only posture, but it's dead weight. Consider
  trimming the default list or pointing it at a single env var to
  avoid confusion if a future story flips cookie-based auth on.

## Recommendations

1. When the rate-limit story lands, treat `auth.login`, `auth.register`,
   and the `api-key` middleware together — the API key middleware
   already carries a `TODO(rate-limit)` marker, and you'll want a
   single `RateLimiter::for()` definition per caller identity (user,
   client, or IP fallback) rather than three different ones.
2. If a future story adds `UserRegistered` / `UserLoggedIn` domain
   events for audit logging (called out in architecture.md as
   deferred), the Actions are already the correct dispatch point —
   no refactor needed, just add the event classes under
   `App\Domain\Auth\Events` and dispatch from the Action tail.
3. Consider centralizing the `{ data: { user: …, token: … } }` shape
   emitted by `RegisterController` and `LoginController` into a
   shared `AuthTokenResource` once a third auth endpoint needs it
   (refresh, social login, etc.). Premature to extract now with only
   two call sites, but worth tracking.
4. The `app(Authenticate::class)->handle(...)` delegation inside
   `EnsureApiKeyOrSanctum` (architecture risk #3) is covered by the
   six-permutation middleware test, but because it depends on a
   framework middleware signature, add a comment on the `handle()`
   call linking to the middleware feature test so a future Laravel
   upgrade surfaces the coupling quickly.

## Verdict

**AUDIT PASSED** — 0 must-fix issues, 0 should-fix issues, 6 minor
notes. Story is ready to be marked `audited`.
