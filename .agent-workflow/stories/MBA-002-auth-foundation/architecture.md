# Architecture: MBA-002 — Auth Foundation

## Overview

Introduce two independent authentication mechanisms plus a combined gate:
**Laravel Sanctum** personal access tokens for end users (register/login/logout/me),
and **env-injected API keys** for trusted client apps (mobile, admin, frontend)
verified by a custom `api-key` middleware. A third alias
`api-key-or-sanctum` prefers a Sanctum bearer token when present (hard-failing
on invalid tokens) and otherwise requires a valid API key. Auth errors flow
through the existing JSON exception handler in `bootstrap/app.php` — no
changes there.

A new `App\Domain\Auth` bounded context owns registration, login, and
logout Actions. The `User` model stays at `App\Models\User` because
`config/auth.php` and the framework's auth guard reference it there; we
only add the `HasApiTokens` trait and a factory already exists.

---

## Resolved Open Questions

1. **Sanctum token TTL:** `60 * 24 * 14` minutes (14 days), set via
   `config/sanctum.php` `expiration` and sourced from
   `SANCTUM_TOKEN_EXPIRATION` in `.env` with the 14-day default.
2. **Combined middleware on invalid bearer:** **hard-fail `401`**. A client
   that sends `Authorization: Bearer …` is asserting user identity; silently
   falling back to API-key auth would mask client bugs.
3. **Config file:** standalone `config/api_keys.php`. Keeps the framework's
   `config/auth.php` untouched and makes the rotation story obvious.

---

## Domain Changes

### Domain layout

```
app/Domain/
└── Auth/
    ├── Actions/
    │   ├── RegisterUserAction.php
    │   ├── LoginUserAction.php
    │   └── LogoutCurrentTokenAction.php
    ├── DataTransferObjects/
    │   ├── RegisterUserData.php
    │   ├── LoginUserData.php
    │   └── AuthTokenData.php           // { User $user, string $plainTextToken }
    └── Exceptions/
        └── InvalidCredentialsException.php  // extends AuthenticationException
```

### Model changes

- `App\Models\User`: add `use Laravel\Sanctum\HasApiTokens;`. No other changes.
  Existing fillable/hidden/casts stay as-is.

### Migrations

- Publish Sanctum's `personal_access_tokens` migration via
  `php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"`.
  No custom migrations.

### QueryBuilder

None.

---

## Actions & DTOs

### DTOs

All DTOs are `final readonly class` with promoted constructor properties and a
`static from(array $payload): self` named constructor.

- `RegisterUserData` — `{ string $name, string $email, string $password }`.
  Built from `RegisterUserRequest::validated()`.
- `LoginUserData` — `{ string $email, string $password }`. Built from
  `LoginUserRequest::validated()`.
- `AuthTokenData` — `{ User $user, string $plainTextToken }`. Returned by
  `RegisterUserAction` and `LoginUserAction` so controllers can render both
  the user and the one-time plain token in the response.

### Actions

All Actions expose a single `execute()` method, are `final`, have explicit
return types, and contain no HTTP-layer concerns.

- `RegisterUserAction::execute(RegisterUserData $data): AuthTokenData`
  Creates the `User` (password hashing is handled by the model's `hashed` cast),
  issues a Sanctum token via `$user->createToken('auth')`, and returns
  `{ user, plainTextToken }`.
- `LoginUserAction::execute(LoginUserData $data): AuthTokenData`
  Looks up the user by email, verifies the password via `Hash::check`, issues
  a fresh Sanctum token. Throws `InvalidCredentialsException` (rendered as
  `401`) on mismatch — constant-time-ish by always hashing a dummy when the
  user does not exist, to avoid timing hints.
- `LogoutCurrentTokenAction::execute(User $user): void`
  Calls `$user->currentAccessToken()->delete()`. Guards against the transient
  token (shouldn't happen under `auth:sanctum`, but explicit early return
  keeps the Action safe for other callers).

### Exceptions

- `InvalidCredentialsException` extends `Illuminate\Auth\AuthenticationException`
  with the fixed message `"Invalid credentials."`. Because the existing
  exception handler renders `AuthenticationException` as
  `{ "message": "..." }` with `401`, no handler changes are needed.

---

## Events & Listeners

None in v1. A future story may dispatch `UserRegistered` / `UserLoggedIn` for
audit logging; explicitly deferred to keep this story lean.

---

## HTTP Endpoints

All routes live under `Route::prefix('v1')` in `routes/api.php`. Auth routes
are additionally grouped under the `auth` URI prefix and the `auth.` name
prefix.

| Method | Path | Controller | Form Request | Resource | Middleware |
|---|---|---|---|---|---|
| POST | `/auth/register` | `RegisterController` | `RegisterUserRequest` | `UserResource` + token string | _(none — public)_ |
| POST | `/auth/login` | `LoginController` | `LoginUserRequest` | `UserResource` + token string | _(none — public)_ |
| POST | `/auth/logout` | `LogoutController` | _(none)_ | 204 No Content | `auth:sanctum` |
| GET  | `/auth/me` | `MeController` | _(none)_ | `UserResource` | `auth:sanctum` |

Controllers are single-action invokable classes under
`App\Http\Controllers\Api\V1\Auth\…`.

### Form Requests

- `RegisterUserRequest` — `App\Http\Requests\Auth\RegisterUserRequest`
  - `name`: `required|string|max:255`
  - `email`: `required|string|email|max:255|unique:users,email`
  - `password`: `required|string|min:8|confirmed` (expects `password_confirmation`)
  - `authorize(): bool` → `true`.
- `LoginUserRequest` — `App\Http\Requests\Auth\LoginUserRequest`
  - `email`: `required|string|email`
  - `password`: `required|string`
  - `authorize(): bool` → `true`.

Both requests expose a `toData(): RegisterUserData|LoginUserData` helper so
controllers never touch the `validated()` array directly.

### API Resources

- `UserResource` — `App\Http\Resources\Auth\UserResource`
  - Shape: `{ id, name, email, created_at }`. `email_verified_at` deferred to
    the email-verification story; not exposed here.

### Response shapes

- `POST /auth/register` → `201`
  ```json
  { "data": { "user": { ... }, "token": "plain-text-token" } }
  ```
- `POST /auth/login` → `200` (same shape as register).
- `POST /auth/logout` → `204 No Content` (no body).
- `GET /auth/me` → `200` → `{ "data": { id, name, email, created_at } }`.
- Auth failures → `401 { "message": "Unauthenticated." }` (existing handler).
- Validation failures → `422 { "message": "...", "errors": { "field": [...] } }`
  (existing handler).

---

## Middleware

Three aliases registered in `bootstrap/app.php` via
`$middleware->alias([...])`.

### `api-key` — `App\Http\Middleware\EnsureValidApiKey`

- Reads `X-Api-Key` header. Missing → throw `AuthenticationException` (→ 401).
- Iterates `config('api_keys.clients')` (a `{ name => key }` map) and compares
  each entry via `hash_equals()`. No match → throw `AuthenticationException`.
- On match: `$request->attributes->set('api_client', $name)` so downstream
  code can read the caller identity.
- Includes a `// TODO(MBA-later): per-client rate limiting` marker per story
  out-of-scope note.

### `auth:sanctum`

Provided by Sanctum itself — no custom code. Added to `bootstrap/app.php`
only if the guard needs explicit registration (Laravel 13 discovers it via
the service provider).

### `api-key-or-sanctum` — `App\Http\Middleware\EnsureApiKeyOrSanctum`

Pseudocode:

```php
public function handle(Request $request, Closure $next): mixed
{
    if ($request->bearerToken() !== null) {
        // Delegate to Laravel's Authenticate middleware with the sanctum guard.
        // Throws AuthenticationException on missing/invalid/expired tokens
        // → hard-fail 401 per resolved question #2.
        return app(Authenticate::class)->handle($request, $next, 'sanctum');
    }

    return app(EnsureValidApiKey::class)->handle($request, $next);
}
```

This yields:
- Valid bearer → `$request->user()` is the Sanctum-resolved user; `api_client`
  attribute is **not** set.
- No bearer + valid API key → anonymous request with `api_client` attribute set.
- Missing both → `401`.
- Bearer present but invalid/expired → `401` (hard-fail).

### Registration in `bootstrap/app.php`

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->alias([
        'api-key'            => EnsureValidApiKey::class,
        'api-key-or-sanctum' => EnsureApiKeyOrSanctum::class,
    ]);
})
```

---

## Configuration

### `config/api_keys.php` (new)

```php
return [
    'header' => 'X-Api-Key',

    'clients' => array_filter([
        'mobile'   => env('API_KEY_MOBILE'),
        'admin'    => env('API_KEY_ADMIN'),
        'frontend' => env('API_KEY_FRONTEND'),
    ]),
];
```

`array_filter` drops `null` entries so a missing env variable is equivalent
to that client not being registered. The `header` key keeps the canonical
header name out of the middleware.

### `config/sanctum.php` (published)

Key settings after `php artisan vendor:publish --tag=sanctum-config`:

- `expiration` → `env('SANCTUM_TOKEN_EXPIRATION', 60 * 24 * 14)` (14 days in
  minutes). Publishing re-exports the file; we edit this single value.
- `guard` → `[]` (we are not using cookie/session guard fallback — pure token
  auth for this API). Confirm and set to `[]` if the published default differs.

### `.env.example` additions

```env
# Auth — Sanctum
SANCTUM_TOKEN_EXPIRATION=20160

# Auth — API keys (rotation = change value + redeploy)
API_KEY_MOBILE=
API_KEY_ADMIN=
API_KEY_FRONTEND=
```

---

## Testing Strategy

| Layer | Tests |
|---|---|
| DTO unit | `RegisterUserDataTest`, `LoginUserDataTest` — `from()` builds the DTO from a validated-array payload. |
| Action unit | `RegisterUserActionTest` (creates user, returns token), `LoginUserActionTest` (valid creds → token; wrong password → `InvalidCredentialsException`; unknown email → `InvalidCredentialsException` + verify no user-enumeration timing hint — assert exception class, not wall-clock timing), `LogoutCurrentTokenActionTest` (deletes current token only, leaves other tokens intact). |
| Form Request unit | `RegisterUserRequestTest` (required fields, email format, unique email, min password length, confirmed rule), `LoginUserRequestTest` (required fields, email format). |
| Resource unit | `UserResourceTest` (exact key shape + no `password`/`remember_token` leakage). |
| Middleware feature | `EnsureValidApiKeyTest` (valid key → next; missing header → 401; unknown key → 401; sets `api_client` attribute). `EnsureApiKeyOrSanctumTest` (valid bearer → user context; valid API key only → anonymous + api_client; invalid bearer → 401; expired bearer → 401; missing both → 401; bearer takes precedence over a valid API key). |
| HTTP feature | `RegisterUserTest` (201 with `{ data.user, data.token }`, 422 on missing fields, 422 on duplicate email, token is usable on `GET /auth/me`), `LoginUserTest` (200 with token, 401 on wrong password, 401 on unknown email, 422 on missing fields), `LogoutUserTest` (204, 401 without token, revokes only the current token when multiple exist), `MeTest` (200 with user payload, 401 without token, 401 with expired token — use `travel()` past `expiration`). |

All HTTP feature tests use `postJson` / `getJson` and
`assertJsonStructure` / `assertJsonPath`. Auth context is set via
`Sanctum::actingAs($user)` (no need to hit `/login` in tests for other
features).

---

## Risks & Open Questions

### Risks

1. **`config/sanctum.php` publishing is a one-time step** — it bakes the
   package's defaults into the repo. Re-publishing on a future Sanctum upgrade
   may clobber our `expiration` edit. Mitigation: tasks.md calls out the exact
   line to preserve; the value is env-driven so upgrades only touch structural
   keys.
2. **Constant-time API-key comparison is per-entry.** We iterate the
   configured map and `hash_equals()` each value. With 2–3 configured clients
   this is fine; if the list ever grows into the hundreds, the linear scan
   becomes a side-channel. Not a concern at the current scale — flagged for
   the future rate-limiting story.
3. **`Authenticate` middleware invocation pattern** inside
   `EnsureApiKeyOrSanctum` relies on Laravel's public middleware signature
   (`handle(Request, Closure, ...$guards)`). If Laravel changes that contract
   in a minor version, the delegation breaks. Covered by the middleware
   feature tests.
4. **`InvalidCredentialsException` leaks a different message than Laravel's
   default `Unauthenticated.`** That's intentional (user-facing "Invalid
   credentials" is clearer than the framework default) but means the string
   is not identical to the generic 401 used elsewhere. Tests assert against
   the literal `Invalid credentials.` to lock this.
5. **MBA-001 routes are currently public.** This story does not retrofit
   `api-key` onto them — that happens in a follow-up (or as an explicit task
   in MBA-001's close-out). Called out in the story's own "Blocks" section.

### Open questions (none blocking — all resolved above)

---

## Files Touched / Created

### New files

- `config/api_keys.php`
- `config/sanctum.php` (published by Sanctum, expiration value edited)
- `database/migrations/xxxx_xx_xx_create_personal_access_tokens_table.php` (published by Sanctum)
- `app/Domain/Auth/Actions/RegisterUserAction.php`
- `app/Domain/Auth/Actions/LoginUserAction.php`
- `app/Domain/Auth/Actions/LogoutCurrentTokenAction.php`
- `app/Domain/Auth/DataTransferObjects/RegisterUserData.php`
- `app/Domain/Auth/DataTransferObjects/LoginUserData.php`
- `app/Domain/Auth/DataTransferObjects/AuthTokenData.php`
- `app/Domain/Auth/Exceptions/InvalidCredentialsException.php`
- `app/Http/Middleware/EnsureValidApiKey.php`
- `app/Http/Middleware/EnsureApiKeyOrSanctum.php`
- `app/Http/Controllers/Api/V1/Auth/RegisterController.php`
- `app/Http/Controllers/Api/V1/Auth/LoginController.php`
- `app/Http/Controllers/Api/V1/Auth/LogoutController.php`
- `app/Http/Controllers/Api/V1/Auth/MeController.php`
- `app/Http/Requests/Auth/RegisterUserRequest.php`
- `app/Http/Requests/Auth/LoginUserRequest.php`
- `app/Http/Resources/Auth/UserResource.php`
- `tests/Unit/Domain/Auth/**` — DTO + Action tests per the testing table
- `tests/Unit/Http/Requests/Auth/**` — Form Request tests
- `tests/Unit/Http/Resources/Auth/**` — Resource test
- `tests/Feature/Api/V1/Auth/{RegisterUser,LoginUser,LogoutUser,Me}Test.php`
- `tests/Feature/Http/Middleware/{EnsureValidApiKey,EnsureApiKeyOrSanctum}Test.php`

### Modified files

- `composer.json` / `composer.lock` — add `laravel/sanctum` (via `require`).
- `app/Models/User.php` — add `HasApiTokens` trait.
- `bootstrap/app.php` — register the two custom middleware aliases.
- `routes/api.php` — add the `/auth` group with four routes.
- `.env.example` — document new keys.
