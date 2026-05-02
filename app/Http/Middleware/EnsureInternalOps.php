<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

final class EnsureInternalOps
{
    public function handle(Request $request, Closure $next): Response
    {
        $cidr = (string) config('ops.internal_ops_cidr', '10.114.0.0/20');
        $allowedCidrs = array_map('trim', explode(',', $cidr));

        if (! IpUtils::checkIp((string) $request->ip(), $allowedCidrs)) {
            return response()->json(['message' => 'Internal endpoint.'], 403);
        }

        return $next($request);
    }
}
