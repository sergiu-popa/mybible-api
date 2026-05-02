<?php

declare(strict_types=1);

namespace App\Support\Observability;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Sentry\Breadcrumb;

/**
 * Logs slow queries to a dedicated channel and emits a Sentry breadcrumb so
 * production hot-spots surface in both Loki and the Sentry transaction trail.
 *
 * Disabled in `local`/`testing` envs to keep dev quiet and the test suite
 * deterministic — the listener is wired by AppServiceProvider::boot().
 */
final class SlowQueryListener
{
    public const int THRESHOLD_MS = 500;

    public static function register(): void
    {
        DB::listen(static function (QueryExecuted $query): void {
            self::handle($query);
        });
    }

    public static function handle(QueryExecuted $query): void
    {
        if ($query->time <= self::THRESHOLD_MS) {
            return;
        }

        Log::channel('slow_query')->warning('slow_query', [
            'sql' => $query->sql,
            'time_ms' => $query->time,
            'bindings' => $query->bindings,
        ]);

        if (function_exists('Sentry\addBreadcrumb') && class_exists(Breadcrumb::class)) {
            \Sentry\addBreadcrumb(new Breadcrumb(
                Breadcrumb::LEVEL_WARNING,
                Breadcrumb::TYPE_DEFAULT,
                'db.query',
                sprintf('Slow query (%dms): %s', (int) $query->time, $query->sql),
            ));
        }
    }
}
