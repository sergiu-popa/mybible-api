<?php

declare(strict_types=1);

namespace App\Http\Controllers\Health;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

final class ShowReadinessController
{
    private const PROBE_KEY = 'ready:probe';

    private const PROBE_TTL_SECONDS = 5;

    private const TIMEOUT_SECONDS = 1.0;

    public function __invoke(): JsonResponse
    {
        $services = [];
        $dependency = null;

        [$redisOk, $redisElapsed] = $this->pingRedis();
        $services['redis'] = $redisOk;

        if (! $redisOk || $redisElapsed > self::TIMEOUT_SECONDS) {
            $dependency = 'redis';
            $services['redis'] = false;
        }

        [$dbOk, $dbElapsed] = $this->pingDatabase();
        $services['db'] = $dbOk;

        if ($dependency === null && (! $dbOk || $dbElapsed > self::TIMEOUT_SECONDS)) {
            $dependency = 'db';
            $services['db'] = false;
        }

        if ($dependency !== null) {
            return response()->json([
                'status' => 'unready',
                'dependency' => $dependency,
                'services' => $services,
            ], 503);
        }

        return response()->json([
            'status' => 'ready',
            'services' => $services,
        ]);
    }

    /**
     * @return array{bool, float}
     */
    private function pingRedis(): array
    {
        $start = microtime(true);
        try {
            Redis::connection('cache')->setex(self::PROBE_KEY, self::PROBE_TTL_SECONDS, '1');
            Redis::connection('cache')->get(self::PROBE_KEY);

            return [true, microtime(true) - $start];
        } catch (Throwable) {
            return [false, microtime(true) - $start];
        }
    }

    /**
     * @return array{bool, float}
     */
    private function pingDatabase(): array
    {
        $start = microtime(true);
        try {
            DB::select('SELECT 1');

            return [true, microtime(true) - $start];
        } catch (Throwable) {
            return [false, microtime(true) - $start];
        }
    }
}
