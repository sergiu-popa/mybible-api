<?php

declare(strict_types=1);

namespace App\Support\Caching;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Wraps {@see Cache::tags()->flexible()} so every cached read shares the same
 * tagging, stale-while-revalidate window, miss-logging, and Sentry tagging.
 *
 * Actions inject this and call `read($key, $tags, $ttl, $build)` instead of
 * the facade chain. The `$build` closure is invoked only on a cold miss; the
 * `flexible` window allows one stale-but-warm read while the rebuild runs
 * inside an atomic lock so a hot key cannot stampede MySQL.
 */
final class CachedRead
{
    /**
     * @template T
     *
     * @param  array<int, string>  $tags
     * @param  Closure(): T  $build
     * @return T
     */
    public function read(string $key, array $tags, int $ttl, Closure $build): mixed
    {
        $miss = false;

        $value = Cache::tags($tags)->flexible(
            $key,
            [$ttl, $ttl + $this->graceSeconds()],
            function () use (&$miss, $build): mixed {
                $miss = true;

                return $build();
            },
        );

        if ($miss) {
            Log::info('cache.miss', ['key' => $key]);
        }

        $this->tagSentryScope($miss ? 'miss' : 'hit');

        return $value;
    }

    private function graceSeconds(): int
    {
        $value = config('cache.flexible_grace_seconds', 60);

        return is_numeric($value) ? (int) $value : 60;
    }

    /**
     * Best-effort Sentry tag for hit-rate dashboards. Silently no-ops when
     * `sentry/sentry-laravel` is not installed (CI / local without Sentry).
     * Also tags `route_name` so hit-rate can be filtered per route in Sentry.
     */
    private function tagSentryScope(string $status): void
    {
        if (! function_exists('Sentry\configureScope')) {
            return;
        }

        $routeName = request()->route()?->getName();

        \Sentry\configureScope(function ($scope) use ($status, $routeName): void {
            $scope->setTag('cache_status', $status);
            if ($routeName !== null) {
                $scope->setTag('route_name', $routeName);
            }
        });
    }
}
