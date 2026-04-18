# Tasks: MBA-002 — Auth Foundation

> Implementation order is dependency-driven: package install → config →
> domain → middleware → HTTP → tests. Run `make lint-fix && make stan &&
> make test` after each cluster. All commands run inside the
> `mybible-api-app` container via `make` or `docker exec`.

## Package & Schema

- [x] 1. Install Sanctum: `docker exec mybible-api-app composer require laravel/sanctum`.
- [x] 2. Publish Sanctum assets: `docker exec mybible-api-app php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider" --no-interaction`. Confirm `database/migrations/xxxx_create_personal_access_tokens_table.php` and `config/sanctum.php` appear.
- [x] 3. Edit `config/sanctum.php`: set `'expiration' => env('SANCTUM_TOKEN_EXPIRATION', 60 * 24 * 14)` and set `'guard' => []` (pure-token auth, no cookie fallback for this JSON API).
- [x] 4. Add `SANCTUM_TOKEN_EXPIRATION=20160`, `API_KEY_MOBILE=`, `API_KEY_ADMIN=`, `API_KEY_FRONTEND=` to `.env.example` with an "Auth" section comment.
- [x] 5. Run `make migrate` and confirm the `personal_access_tokens` table with the `database-schema` MCP tool.

## Config

- [x] 6. Create `config/api_keys.php` exporting `{ header: 'X-Api-Key', clients: array_filter([...env-based map...]) }` for the three client names (mobile/admin/frontend).

## Model

- [x] 7. Update `app/Models/User.php`: `use Laravel\Sanctum\HasApiTokens;` and add the trait to the class. No other changes.

## Domain — DTOs

- [x] 8. Create `app/Domain/Auth/DataTransferObjects/RegisterUserData.php` — `final readonly class` with `public string $name`, `public string $email`, `public string $password` and `static from(array $payload): self`.
- [x] 9. Create `app/Domain/Auth/DataTransferObjects/LoginUserData.php` — `final readonly class` with `public string $email`, `public string $password` and `static from(array $payload): self`.
- [x] 10. Create `app/Domain/Auth/DataTransferObjects/AuthTokenData.php` — `final readonly class` with `public User $user`, `public string $plainTextToken`.

## Domain — Exceptions

- [x] 11. Create `app/Domain/Auth/Exceptions/InvalidCredentialsException.php` extending `Illuminate\Auth\AuthenticationException` with a constructor that sets the message to `"Invalid credentials."`.

## Domain — Actions

- [x] 12. Create `app/Domain/Auth/Actions/RegisterUserAction.php` — `final class` with `execute(RegisterUserData $data): AuthTokenData` that creates a `User` and calls `$user->createToken('auth')->plainTextToken`. The model's `hashed` cast handles password hashing.
- [x] 13. Create `app/Domain/Auth/Actions/LoginUserAction.php` — `final class` with `execute(LoginUserData $data): AuthTokenData`. Look up user by email; use `Hash::check()` (run against a pre-hashed dummy when the user is not found to avoid trivial timing hints); on mismatch throw `InvalidCredentialsException`; on match issue a fresh token.
- [x] 14. Create `app/Domain/Auth/Actions/LogoutCurrentTokenAction.php` — `final class` with `execute(User $user): void` that calls `$user->currentAccessToken()->delete()` when the current token is a `PersonalAccessToken` instance (early return otherwise).

## HTTP — Form Requests

- [x] 15. Create `app/Http/Requests/Auth/RegisterUserRequest.php` with `authorize(): bool { return true; }`, rules `name|email|password(min:8, confirmed)|password_confirmation`, and `toData(): RegisterUserData` helper.
- [x] 16. Create `app/Http/Requests/Auth/LoginUserRequest.php` with `authorize(): bool { return true; }`, rules `email|password`, and `toData(): LoginUserData` helper.

## HTTP — Resources

- [x] 17. Create `app/Http/Resources/Auth/UserResource.php` returning `{ id, name, email, created_at }`. Explicitly omit `password`, `remember_token`, and `email_verified_at`.

## HTTP — Middleware

- [x] 18. Create `app/Http/Middleware/EnsureValidApiKey.php`: read header name from `config('api_keys.header')`, iterate `config('api_keys.clients')`, compare with `hash_equals()`, attach matched name via `$request->attributes->set('api_client', $name)`, throw `AuthenticationException` on miss/missing. Add the `// TODO(rate-limit): per-client rate limiting lives in a future story` marker.
- [x] 19. Create `app/Http/Middleware/EnsureApiKeyOrSanctum.php`: if `$request->bearerToken()` is not null, delegate to `app(\Illuminate\Auth\Middleware\Authenticate::class)->handle($request, $next, 'sanctum')`; otherwise delegate to `app(EnsureValidApiKey::class)->handle(...)`.
- [x] 20. Register both middleware in `bootstrap/app.php` as aliases `api-key` and `api-key-or-sanctum` inside the `withMiddleware` closure.

## HTTP — Controllers & Routes

- [x] 21. Create `app/Http/Controllers/Api/V1/Auth/RegisterController.php` (invokable) — accepts `RegisterUserRequest`, calls `RegisterUserAction`, returns `201` with `{ data: { user: UserResource, token: plainTextToken } }`.
- [x] 22. Create `app/Http/Controllers/Api/V1/Auth/LoginController.php` (invokable) — accepts `LoginUserRequest`, calls `LoginUserAction`, returns `200` with the same shape as register.
- [x] 23. Create `app/Http/Controllers/Api/V1/Auth/LogoutController.php` (invokable) — accepts `Request`, calls `LogoutCurrentTokenAction` with `$request->user()`, returns `204` (empty body).
- [x] 24. Create `app/Http/Controllers/Api/V1/Auth/MeController.php` (invokable) — returns `UserResource::make($request->user())` with `200`.
- [x] 25. Add the four routes in `routes/api.php` inside the existing `v1` prefix, grouped under `->prefix('auth')->name('auth.')`:
  - `POST /register` → `RegisterController`, name `auth.register` (public).
  - `POST /login` → `LoginController`, name `auth.login` (public).
  - `POST /logout` → `LogoutController`, name `auth.logout`, middleware `auth:sanctum`.
  - `GET /me` → `MeController`, name `auth.me`, middleware `auth:sanctum`.

## Tests — Unit (DTOs)

- [x] 26. `tests/Unit/Domain/Auth/DataTransferObjects/RegisterUserDataTest` — `from()` builds the DTO correctly from a validated-array payload.
- [x] 27. `tests/Unit/Domain/Auth/DataTransferObjects/LoginUserDataTest` — same.

## Tests — Unit (Actions)

- [x] 28. `tests/Unit/Domain/Auth/Actions/RegisterUserActionTest` — creates a `User` with hashed password, returns `AuthTokenData` with a non-empty `plainTextToken`, the token persists in `personal_access_tokens`.
- [x] 29. `tests/Unit/Domain/Auth/Actions/LoginUserActionTest` — valid credentials yield `AuthTokenData`; wrong password throws `InvalidCredentialsException`; unknown email also throws `InvalidCredentialsException`.
- [x] 30. `tests/Unit/Domain/Auth/Actions/LogoutCurrentTokenActionTest` — deletes the `currentAccessToken`, leaves other tokens on the user intact (seed two tokens via `createToken`, simulate `currentAccessToken` via `withAccessToken`).

## Tests — Unit (HTTP layer)

- [x] 31. `tests/Unit/Http/Requests/Auth/RegisterUserRequestTest` — validation rules fire correctly (required, email format, unique email, min password length, confirmation mismatch).
- [x] 32. `tests/Unit/Http/Requests/Auth/LoginUserRequestTest` — required fields + email format.
- [x] 33. `tests/Unit/Http/Resources/Auth/UserResourceTest` — response array keys are exactly `[id, name, email, created_at]`; `password` and `remember_token` never appear.

## Tests — Feature (Middleware)

- [x] 34. `tests/Feature/Http/Middleware/EnsureValidApiKeyTest` — register a throwaway test route bound to the `api-key` middleware, then assert: valid key passes, unknown key → 401 JSON envelope, missing header → 401, matched client name is attached to `$request->attributes`.
- [x] 35. `tests/Feature/Http/Middleware/EnsureApiKeyOrSanctumTest` — permutations: valid bearer (user resolved), valid API key only (anonymous + api_client attribute set), invalid bearer hard-fails 401 (even when a valid API key is also present), expired bearer 401, missing both 401, bearer takes precedence.

## Tests — Feature (HTTP endpoints)

- [x] 36. `tests/Feature/Api/V1/Auth/RegisterUserTest` — 201 with `data.user` + `data.token`, 422 on missing fields / duplicate email / password confirmation mismatch, the returned token works on `GET /auth/me`.
- [x] 37. `tests/Feature/Api/V1/Auth/LoginUserTest` — 200 with token, 401 on wrong password, 401 on unknown email, 422 on missing fields.
- [x] 38. `tests/Feature/Api/V1/Auth/LogoutUserTest` — 204, 401 without token, revokes only the current token when two tokens exist on the user.
- [x] 39. `tests/Feature/Api/V1/Auth/MeTest` — 200 with user payload via `Sanctum::actingAs`, 401 without a token, 401 when the token is past `expiration` (use `travel()` helper).

## Polish

- [x] 40. Run `make lint-fix`.
- [x] 41. Run `make stan` and resolve all PHPStan findings.
- [x] 42. Run `make test` and ensure green.
- [x] 43. Update story status in `story.md` from `draft` to `architected`.
