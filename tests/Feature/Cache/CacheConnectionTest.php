<?php

declare(strict_types=1);

namespace Tests\Feature\Cache;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

/**
 * Round-trips set+get against the live `mybible-redis-test` instance.
 *
 * Catches a misconfigured Redis cluster on CI rather than after deploy:
 * a wrong host / port / scheme will fail this single test instead of
 * silently breaking every cached endpoint.
 */
final class CacheConnectionTest extends TestCase
{
    public function test_it_round_trips_a_set_and_get_against_the_real_redis(): void
    {
        // Force the redis driver for this test even though phpunit.xml sets
        // CACHE_STORE=array — we want to exercise the actual connection.
        Config::set('cache.default', 'redis');

        $key = 'cache-connection-test:' . bin2hex(random_bytes(8));
        $value = 'pong-' . uniqid();

        $store = cache()->store('redis');

        $store->put($key, $value, 30);

        $this->assertSame($value, $store->get($key));

        $store->forget($key);
    }

    public function test_it_can_ping_the_cache_redis_connection(): void
    {
        $result = Redis::connection('cache')->ping();

        // phpredis returns true; predis returns the literal "PONG".
        $this->assertTrue($result === true || $result === 'PONG');
    }
}
