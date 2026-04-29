<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use Illuminate\Support\Facades\Config;
use Tests\TestCase;

final class HealthCheckTest extends TestCase
{
    public function test_it_returns_200_when_redis_and_db_are_healthy(): void
    {
        $response = $this->getJson('/up');

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('services.redis', true)
            ->assertJsonPath('services.db', true);
    }

    public function test_it_returns_503_when_redis_is_unreachable(): void
    {
        Config::set('database.redis.cache.host', '127.0.0.1');
        Config::set('database.redis.cache.port', 1);
        // Drop any cached Redis connection so the next ping rebuilds against
        // the bogus endpoint above.
        app('redis')->purge('cache');

        $response = $this->getJson('/up');

        $response->assertStatus(503)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('services.redis', false);
    }
}
