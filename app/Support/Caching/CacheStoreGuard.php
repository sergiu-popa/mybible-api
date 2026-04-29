<?php

declare(strict_types=1);

namespace App\Support\Caching;

use Illuminate\Cache\TaggableStore;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Boot-time check that the configured default cache store implements
 * {@see TaggableStore}. Tag-based invalidation is load-bearing across the
 * cached endpoints; a misconfigured `CACHE_STORE=database` would silently
 * break every `Cache::tags(...)->flush()` call. Failing fast at startup
 * surfaces the misconfiguration on deploy rather than at the first
 * write-side flush.
 */
final class CacheStoreGuard
{
    public static function ensureTaggable(): void
    {
        $store = Cache::store()->getStore();

        if ($store instanceof TaggableStore) {
            return;
        }

        $driver = config('cache.default');
        $class = $store::class;

        throw new RuntimeException(
            "Configured cache store [{$driver}] ({$class}) does not support tags. "
            . 'Set CACHE_STORE to a tag-capable driver (redis, memcached, array).',
        );
    }
}
