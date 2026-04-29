<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Replaces the framework default `/up` health endpoint so deploys + DO load
 * balancer health checks fail fast when the cache (Valkey) is unreachable.
 *
 * Returns `200 {ok: true, services: {redis, db}}` when both upstreams
 * respond, `503 {ok: false, services: {...}}` otherwise. Per-service
 * booleans let dashboards distinguish a Redis outage from a MySQL one.
 */
final class HealthCheckController
{
    public function __invoke(): JsonResponse
    {
        $redisOk = $this->pingRedis();
        $dbOk = $this->pingDatabase();

        $ok = $redisOk && $dbOk;

        return response()->json([
            'ok' => $ok,
            'services' => [
                'redis' => $redisOk,
                'db' => $dbOk,
            ],
        ], $ok ? 200 : 503);
    }

    private function pingRedis(): bool
    {
        try {
            Redis::connection('cache')->ping();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function pingDatabase(): bool
    {
        try {
            DB::select('SELECT 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
