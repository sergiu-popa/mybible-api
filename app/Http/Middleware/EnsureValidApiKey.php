<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureValidApiKey
{
    // TODO(rate-limit): per-client rate limiting lives in a future story.
    public function handle(Request $request, Closure $next): Response
    {
        /** @var string $header */
        $header = config('api_keys.header', 'X-Api-Key');

        $presentedKey = $request->header($header);

        if (! is_string($presentedKey) || $presentedKey === '') {
            throw new AuthenticationException;
        }

        /** @var array<string, mixed> $clients */
        $clients = config('api_keys.clients', []);

        foreach ($clients as $name => $configuredKey) {
            if (! is_string($configuredKey) || $configuredKey === '') {
                continue;
            }

            if (hash_equals($configuredKey, $presentedKey)) {
                $request->attributes->set('api_client', $name);

                return $next($request);
            }
        }

        throw new AuthenticationException;
    }
}
