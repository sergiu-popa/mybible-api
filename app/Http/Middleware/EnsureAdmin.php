<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates `/api/v1/admin/*` routes. Resolves the Sanctum bearer first, then
 * rejects authenticated users that don't carry the `admin` role. Mounting
 * downstream `auth:sanctum` is implied — if a route under this middleware
 * is hit without a token, we fail with 401.
 */
final class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        if (! in_array('admin', $user->roles, true)) {
            throw new AuthorizationException('Admin access required.');
        }

        return $next($request);
    }
}
