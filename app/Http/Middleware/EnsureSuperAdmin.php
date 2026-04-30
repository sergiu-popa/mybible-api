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
 * Gates the platform-wide admin sections (Bible catalog, Mobile Versions,
 * Admins management). The admin UI hides these sections, but the API is
 * the security boundary — every restricted endpoint must check
 * `is_super` server-side and return 403 otherwise.
 */
final class EnsureSuperAdmin
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

        // See `EnsureAdmin`: re-check `is_active` so a disabled admin
        // can't ride a stale token into a super-admin endpoint.
        if (! $user->is_active) {
            throw new AuthorizationException('Admin access required.');
        }

        if (! $user->is_super) {
            throw new AuthorizationException('Super-admin access required.');
        }

        return $next($request);
    }
}
