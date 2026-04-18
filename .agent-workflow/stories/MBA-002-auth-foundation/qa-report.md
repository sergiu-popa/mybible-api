# QA Report — MBA-002-auth-foundation

## Test Suite Results

- Total: 49 | Passed: 49 | Failed: 0 | Skipped: 0
- Assertions: 126
- Duration: 1.33s
- Lint: PASS (59 files)
- PHPStan: PASS (0 errors, 42 files)

## Acceptance Criteria Verification

| # | Criterion | Test exists | Status |
|---|-----------|-------------|--------|
| 1 | API keys read from `.env` via `config/api_keys.php` keyed `{ name => key }` map | ✅ `EnsureValidApiKeyTest` exercises the map | PASS |
| 2 | `api-key` middleware reads `X-Api-Key`, constant-time compares, 401 on miss | ✅ `EnsureValidApiKeyTest::test_it_rejects_missing_header`, `::test_it_rejects_an_unknown_key` | PASS |
| 3 | Matched client name attached to request via `api_client` attribute | ✅ `EnsureValidApiKeyTest::test_it_attaches_the_matched_client_name` | PASS |
| 4 | No DB table, no management commands, no revocation API | ✅ Verified by inspection — no migration, no artisan commands, rotation via env | PASS |
| 5 | Sanctum installed, `HasApiTokens` on `User`, `personal_access_tokens` migration run | ✅ Composer require present; `User` uses trait; migration ran (`make migrate` clean) | PASS |
| 6 | `auth:sanctum` verifies bearer, resolves `User`, 401 on missing/invalid | ✅ `MeTest::test_it_returns_401_without_a_token`, `::test_it_returns_401_with_an_expired_token` | PASS |
| 7 | `POST /api/v1/auth/register` → `201 { user, token }` | ✅ `RegisterUserTest::test_it_creates_a_user_and_returns_a_token` | PASS |
| 8 | `POST /api/v1/auth/login` → `200 { user, token }`; 401 on invalid creds | ✅ `LoginUserTest` (4 tests cover happy + 2 × 401 + 422) | PASS |
| 9 | `POST /api/v1/auth/logout` revokes current token → `204` | ✅ `LogoutUserTest::test_it_revokes_only_the_current_token` | PASS |
| 10 | `GET /api/v1/auth/me` returns authenticated user | ✅ `MeTest::test_it_returns_the_authenticated_user` | PASS |
| 11 | Configurable expiration (default 14 days), no refresh flow | ✅ `config/sanctum.php` uses `SANCTUM_TOKEN_EXPIRATION` default `20160`; expiry enforced in `MeTest` and combined middleware test | PASS |
| 12 | `api-key-or-sanctum`: valid bearer resolves user; else requires API key; both missing → 401; invalid bearer hard-fails | ✅ `EnsureApiKeyOrSanctumTest` (6 permutations incl. bearer-precedence and hard-fail with valid API key also present) | PASS |
| 13 | Errors flow through JSON exception handler (`{ message }` / `{ message, errors }`) | ✅ 401 tests assert JSON envelope; 422 tests use `assertJsonValidationErrors` | PASS |

All 13 acceptance criteria have direct test coverage and pass.

## Edge Cases Tested

| Case | Expected | Actual | Status |
|------|----------|--------|--------|
| Register with empty body | 422 on `name`, `email`, `password` | 422 with all three errors | PASS |
| Register with duplicate email | 422 on `email` | 422 validation error | PASS |
| Register with password confirmation mismatch | 422 on `password` | 422 validation error | PASS |
| Login with unknown email | 401 `Invalid credentials.` (no enumeration) | 401 with literal message | PASS |
| Login with wrong password | 401 `Invalid credentials.` | 401 with literal message | PASS |
| Login with empty body | 422 on `email`, `password` | 422 validation error | PASS |
| `/auth/me` without bearer | 401 | 401 | PASS |
| `/auth/me` with expired bearer | 401 (via `sanctum.expiration=1`, `travel(5)->minutes()`) | 401 | PASS |
| `/auth/logout` without bearer | 401 | 401 | PASS |
| `/auth/logout` revokes only current token | Other tokens untouched | Only the used token deleted; second token persists in DB | PASS |
| Registered token is immediately usable on `/auth/me` | 200 with correct user | 200 with matching email | PASS |
| `X-Api-Key` missing | 401 JSON envelope | 401 | PASS |
| `X-Api-Key` unknown | 401 JSON envelope | 401 | PASS |
| Combined middleware: valid bearer → user, `api_client` null | User resolved, client null | User resolved, client null | PASS |
| Combined middleware: valid API key only → anonymous + `api_client` set | No user, `api_client` = 'mobile' | As expected | PASS |
| Combined middleware: invalid bearer **plus** valid API key | 401 (hard-fail) | 401 | PASS |
| Combined middleware: expired bearer | 401 | 401 | PASS |
| Combined middleware: neither credential | 401 | 401 | PASS |
| Combined middleware: bearer takes precedence over API key | User resolved, `api_client` null | As expected | PASS |

## Regressions

None found. Full suite (49 tests / 126 assertions) green; lint and PHPStan clean.

## Notes (carried from code review — non-blocking)

- `DUMMY_HASH` in `LoginUserAction` is a hardcoded `$2y$12$…` bcrypt hash.
  Timing uniformity assumes `BCRYPT_ROUNDS=12`. If that env changes, the
  dummy/real hash cost will diverge. Tests still pass; behavior is correct.
- `TODO(rate-limit)` marker in `EnsureValidApiKey` is placed between the
  class opener and `handle()`. Cosmetic.
- `auth.login` / `auth.register` have no throttling yet — deferred per
  story scope to a future rate-limit story.

## Verdict

**QA PASSED** — all 13 acceptance criteria verified, 49/49 tests pass (126 assertions), lint and PHPStan clean, no regressions, no critical findings.
