<?php

declare(strict_types=1);

use Illuminate\Support\Str;

return [

    'name' => env('HORIZON_NAME'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Domain & Path
    |--------------------------------------------------------------------------
    | The dashboard mounts on the API host at `/horizon`; the route is gated
    | by `App\Providers\HorizonServiceProvider::gate()` (super-admin only).
    | Domain is intentionally null so we do not depend on a separate
    | sub-domain — the API host carries the dashboard.
    */

    'domain' => env('HORIZON_DOMAIN'),

    'path' => env('HORIZON_PATH', 'horizon'),

    'use' => 'default',

    'prefix' => env(
        'HORIZON_PREFIX',
        Str::slug(env('APP_NAME', 'laravel'), '_') . '_horizon:',
    ),

    /*
    |--------------------------------------------------------------------------
    | Horizon Route Middleware
    |--------------------------------------------------------------------------
    | `auth:sanctum` resolves the bearer token to a User; the `viewHorizon`
    | gate (defined in HorizonServiceProvider) then enforces super-admin.
    */

    'middleware' => ['auth:sanctum'],

    'waits' => [
        'redis:default' => 60,
        'redis:etl' => 600,
        'redis:cleanup' => 120,
    ],

    'trim' => [
        'recent' => 60,
        'pending' => 60,
        'completed' => 60,
        'recent_failed' => 10080,
        'failed' => 10080,
        'monitored' => 10080,
    ],

    'silenced' => [
        //
    ],

    'silenced_tags' => [
        //
    ],

    'metrics' => [
        'trim_snapshots' => [
            'job' => 24,
            'queue' => 24,
        ],
    ],

    'fast_termination' => false,

    'memory_limit' => 64,

    /*
    |--------------------------------------------------------------------------
    | Queue Worker Configuration
    |--------------------------------------------------------------------------
    | Three supervisors split work by purpose:
    |   - default: ordinary background jobs
    |   - etl: long-running Symfony→Laravel ETL sub-jobs (MBA-031)
    |   - cleanup: low-priority best-effort jobs (e.g. DeleteUploadedObjectJob)
    */

    'defaults' => [
        'supervisor-default' => [
            'connection' => 'redis',
            'queue' => ['default'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 1,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 128,
            'tries' => 1,
            'timeout' => 60,
            'nice' => 0,
        ],
        'supervisor-etl' => [
            'connection' => 'redis',
            'queue' => ['etl'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 1,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 256,
            'tries' => 1,
            'timeout' => 1800,
            'nice' => 0,
        ],
        'supervisor-cleanup' => [
            'connection' => 'redis',
            'queue' => ['cleanup'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 1,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 128,
            'tries' => 3,
            'timeout' => 120,
            'nice' => 0,
        ],
    ],

    'environments' => [
        'production' => [
            'supervisor-default' => [
                'maxProcesses' => 5,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],
            'supervisor-etl' => [
                'maxProcesses' => 5,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],
            'supervisor-cleanup' => [
                'maxProcesses' => 2,
            ],
        ],

        'local' => [
            'supervisor-default' => [
                'maxProcesses' => 2,
            ],
            'supervisor-etl' => [
                'maxProcesses' => 2,
            ],
            'supervisor-cleanup' => [
                'maxProcesses' => 1,
            ],
        ],
    ],

    'watch' => [
        'app',
        'bootstrap',
        'config/**/*.php',
        'database/**/*.php',
        'routes',
        'composer.lock',
        'composer.json',
        '.env',
    ],
];
