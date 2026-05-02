<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use Illuminate\Support\Facades\Config;
use Tests\TestCase;

final class HealthCheckTest extends TestCase
{
    // --- Liveness (/up) ---

    public function test_liveness_always_returns_200_with_alive_status(): void
    {
        $response = $this->getJson('/up');

        $response->assertOk()
            ->assertJsonPath('status', 'alive')
            ->assertJsonStructure(['status', 'ts']);
    }

    public function test_liveness_returns_200_even_when_redis_is_unreachable(): void
    {
        Config::set('database.redis.cache.host', '127.0.0.1');
        Config::set('database.redis.cache.port', 1);
        app('redis')->purge('cache');

        $response = $this->getJson('/up');

        $response->assertOk()
            ->assertJsonPath('status', 'alive');
    }

    // --- Readiness (/ready) ---

    public function test_readiness_returns_403_from_non_vpc_ip(): void
    {
        // Default INTERNAL_OPS_CIDR is 10.114.0.0/20; test requests come from 127.0.0.1.
        config()->set('ops.internal_ops_cidr', '10.114.0.0/20');

        $this->getJson('/ready')
            ->assertForbidden()
            ->assertJsonPath('message', 'Internal endpoint.');
    }

    public function test_readiness_returns_200_from_vpc_ip(): void
    {
        // Allow loopback so the test runner can reach /ready.
        config()->set('ops.internal_ops_cidr', '127.0.0.1/32');

        $response = $this->getJson('/ready');

        $response->assertOk()
            ->assertJsonPath('status', 'ready')
            ->assertJsonStructure(['status', 'services' => ['redis', 'db']]);
    }

    public function test_readiness_returns_503_with_redis_dependency_when_redis_is_unreachable(): void
    {
        config()->set('ops.internal_ops_cidr', '127.0.0.1/32');
        Config::set('database.redis.cache.host', '127.0.0.1');
        Config::set('database.redis.cache.port', 1);
        app('redis')->purge('cache');

        $response = $this->getJson('/ready');

        $response->assertStatus(503)
            ->assertJsonPath('status', 'unready')
            ->assertJsonPath('dependency', 'redis')
            ->assertJsonPath('services.redis', false);
    }
}
