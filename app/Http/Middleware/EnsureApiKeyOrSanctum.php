<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureApiKeyOrSanctum
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->bearerToken() !== null) {
            return app(Authenticate::class)->handle($request, $next, 'sanctum');
        }

        return app(EnsureValidApiKey::class)->handle($request, $next);
    }
}
