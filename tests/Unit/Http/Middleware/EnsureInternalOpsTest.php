<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\EnsureInternalOps;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Tests\TestCase;

final class EnsureInternalOpsTest extends TestCase
{
    public function test_vpc_ip_within_default_cidr_is_allowed(): void
    {
        config()->set('ops.internal_ops_cidr', '10.114.0.0/20');

        $request = Request::create('/ready', 'GET');
        $request->server->set('REMOTE_ADDR', '10.114.0.42');

        $response = (new EnsureInternalOps)->handle(
            $request,
            static fn (): JsonResponse => new JsonResponse(['ok' => true], 200),
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['ok' => true], json_decode((string) $response->getContent(), true));
    }

    public function test_public_ip_outside_cidr_is_rejected_with_403(): void
    {
        config()->set('ops.internal_ops_cidr', '10.114.0.0/20');

        $request = Request::create('/ready', 'GET');
        $request->server->set('REMOTE_ADDR', '203.0.113.7');

        $response = (new EnsureInternalOps)->handle(
            $request,
            static fn (): JsonResponse => new JsonResponse(['ok' => true], 200),
        );

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame(
            ['message' => 'Internal endpoint.'],
            json_decode((string) $response->getContent(), true),
        );
    }

    public function test_multiple_comma_separated_cidrs_are_supported(): void
    {
        config()->set('ops.internal_ops_cidr', '10.114.0.0/20, 127.0.0.1/32, 172.16.0.0/12');

        $next = static fn (): JsonResponse => new JsonResponse(['ok' => true], 200);

        foreach (['10.114.5.5', '127.0.0.1', '172.20.0.5'] as $ip) {
            $request = Request::create('/ready', 'GET');
            $request->server->set('REMOTE_ADDR', $ip);

            $this->assertSame(
                200,
                (new EnsureInternalOps)->handle($request, $next)->getStatusCode(),
                "IP {$ip} should be allowed by one of the comma-separated CIDRs.",
            );
        }

        $request = Request::create('/ready', 'GET');
        $request->server->set('REMOTE_ADDR', '8.8.8.8');
        $this->assertSame(
            403,
            (new EnsureInternalOps)->handle($request, $next)->getStatusCode(),
        );
    }
}
