# Story: MBA-002-auth-foundation

## Title
Auth foundation — Sanctum tokens for users, env-injected API keys for clients

## Status
`done`

## Description
The MyBible API serves two kinds of callers:

1. **Trusted client apps** (the official mobile app, the admin/frontend SPAs)
   that need anonymous, read-only access to public content. They authenticate
   with a long-lived **API key** sent in a header. Keys are injected at deploy
   time via environment variables (one per known client).
2. **End users** that need to start subscriptions, complete days, and manage
   their own data. They authenticate with a **Laravel Sanctum personal access
   token** issued at login.

This story establishes both mechanisms, the route middleware that enforces
them, and a minimal user-facing auth surface (register, login, logout, me).
It is a prerequisite for every story that needs to know "who is calling" —
including MBA-003 and MBA-004.

## Acceptance Criteria

### API key (env-injected)
1. The application reads a configured set of API keys from `.env` (e.g.
   `API_KEY_MOBILE`, `API_KEY_ADMIN`, `API_KEY_FRONTEND`) via a single
   `config/auth.php` (or `config/api_keys.php`) entry — one keyed map of
   `{ name => key }`.
2. An `api-key` middleware reads the key from the `X-Api-Key` header and
   compares it (constant-time) against the configured set. Missing/unknown
   keys → `401 Unauthorized` with the standard JSON envelope.
3. On a valid key, the matched **client name** is attached to the request
   (e.g. `$request->attributes->set('api_client', 'mobile')`) so downstream
   code can log/audit which client called.
4. There is **no** `api_keys` database table, no artisan management commands,
   and no per-key revocation API. Rotation is done by changing the env value
   and redeploying.

### Sanctum tokens (users)
5. `laravel/sanctum` is installed and configured. The `User` model uses the
   `HasApiTokens` trait. The `personal_access_tokens` migration is published
   and run.
6. `auth:sanctum` middleware verifies an `Authorization: Bearer <token>`
   header, resolves the `App\Models\User`, and rejects with `401` on
   missing/invalid tokens. The standard JSON envelope is returned.
7. `POST /api/v1/auth/register` creates a `User` (name, email, password) and
   returns `{ user, token }` on `201`.
8. `POST /api/v1/auth/login` validates credentials and returns `{ user, token }`
   on `200`; `401` on invalid credentials.
9. `POST /api/v1/auth/logout` revokes the **current** token (`$request->user()->currentAccessToken()->delete()`) and returns `204`.
10. `GET /api/v1/auth/me` returns the authenticated user (Sanctum required).
11. Tokens have a configurable expiration (default 14 days) via Sanctum's
    `expiration` config; **no refresh-token flow** for v1 — the client logs in
    again when the token expires.

### Combined access
12. A combined middleware alias `api-key-or-sanctum` is registered. When a
    route uses it: if a Sanctum bearer token is present **and valid**,
    `$request->user()` returns the user; otherwise the API-key middleware
    runs and the request proceeds anonymously. Missing both → `401`.

### Validation & error envelope
13. All auth errors render through the existing JSON exception handler
    (`{ "message": "..." }` for 401/403, full envelope with `errors` for 422).

## Scope

### In Scope
- Install and configure `laravel/sanctum`.
- `config/api_keys.php` (or merge into `config/auth.php`) reading from `.env`.
- Three middleware aliases registered in `bootstrap/app.php`: `api-key`,
  `auth:sanctum` (already provided by the package), and a custom
  `api-key-or-sanctum`.
- Auth routes under `/api/v1/auth` (register, login, logout, me).
- Form Requests for register and login.
- Feature tests for every endpoint and middleware path (valid key, invalid
  key, missing key, valid token, expired token, combined-access permutations).

### Out of Scope
- Password reset, email verification, MFA — separate stories.
- OAuth/social login.
- Per-user API tokens with custom abilities (Sanctum supports this; we'll
  add when a use case lands).
- Per-key rate limiting (separate story; add a TODO marker in the middleware).
- Database-managed API keys with a UI/CLI for rotation — keys live in `.env`.
- Refresh tokens.

## Technical Notes

### Why Sanctum (not JWT)
Sanctum is Laravel's first-party token library, ships with the framework's
auth integration, and matches the use case: opaque bearer tokens for
mobile + SPA clients with no need for cross-service token verification.
Avoids the third-party JWT package and keeps the dependency surface small.

### API-key storage
- Keys are generated **out-of-band** (e.g. `openssl rand -base64 32`) per
  client and added to the deployment environment.
- `config/api_keys.php` returns
  `[ 'mobile' => env('API_KEY_MOBILE'), 'admin' => env('API_KEY_ADMIN'), … ]`,
  filtered to drop null entries at boot time.
- Comparison uses `hash_equals()` against each configured value.
- Rotation: deploy a new `.env` value, restart workers/php-fpm. No code or
  DB changes needed.

### Sanctum token TTL
- Set in `config/sanctum.php` via `expiration` (minutes). Default `60 * 24 * 14`
  (14 days). Keep `null` only if the product wants effectively-immortal tokens.

### Combined middleware shape
Pseudocode:
```php
public function handle(Request $request, Closure $next): mixed
{
    if ($request->bearerToken()) {
        return app('auth')->guard('sanctum')->check()
            ? $next($request)
            : response()->json(['message' => 'Unauthenticated.'], 401);
    }

    return app(EnsureValidApiKey::class)->handle($request, $next);
}
```

## Dependencies
None. Blocks MBA-003 and MBA-004. **Should ship before** the MBA-001 routes
get the API-key middleware applied (currently public until this lands).

## Open Questions for Architect
1. Confirm Sanctum token TTL of **14 days**.
2. Should the combined middleware fall through to API-key when a Sanctum
   token is **present but invalid/expired**, or hard-fail with `401`?
   (Recommendation: hard-fail — if a client sends a bearer token, they're
   asserting user identity; falling back silently masks bugs.)
3. Naming: `config/api_keys.php` vs `config/auth.php` extension. Either is
   fine; preference?
